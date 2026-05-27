<?php

namespace App\Services\Integrations;

use App\Models\Field;
use App\Models\Integration;
use App\Models\IntegrationMappingFinding;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Detects, per active integration, fields with possible_values that are used as
 * {$<id>} tokens in a request_body but have no value_mapping configured. Those let
 * our raw enumerated values reach the buyer API unmapped (rejections, wasted leads).
 *
 * Runs a full scan each invocation and reconciles the integration_mapping_findings
 * table: opens new findings, refreshes last_seen, auto-resolves the ones that were
 * fixed, and respects manually ignored ones.
 */
class UnmappedValueMappingScanner
{
  /**
   * Run the scan and reconcile the findings table.
   *
   * @return array{new: array<int, array{integration_id: int, integration: string, company: string, field: string}>, new_count: int, resolved: int, open: int}
   */
  public function scan(): array
  {
    $now = Carbon::now();
    $detected = $this->detect();
    $detectedKeys = $detected->keys()->all();

    $new = [];

    foreach ($detected as $pair) {
      $finding = IntegrationMappingFinding::query()->where('integration_id', $pair['integration_id'])->where('field_id', $pair['field_id'])->first();

      // Manually ignored: respect the decision, do not reopen nor report.
      if ($finding && $finding->status === IntegrationMappingFinding::STATUS_IGNORED) {
        continue;
      }

      $wasNotOpen = !$finding || $finding->status !== IntegrationMappingFinding::STATUS_OPEN;

      if (!$finding) {
        $finding = new IntegrationMappingFinding([
          'integration_id' => $pair['integration_id'],
          'field_id' => $pair['field_id'],
          'first_detected_at' => $now,
        ]);
      }

      $finding->status = IntegrationMappingFinding::STATUS_OPEN;
      $finding->last_seen_at = $now;
      $finding->resolved_at = null;
      $finding->first_detected_at = $finding->first_detected_at ?? $now;
      $finding->save();

      if ($wasNotOpen) {
        $new[] = [
          'integration_id' => $pair['integration_id'],
          'integration' => $pair['integration_name'],
          'company' => $pair['company_name'],
          'field' => $pair['field_name'],
        ];
      }
    }

    // Auto-resolve open findings whose problem is no longer detected.
    $resolvedCount = $this->resolveStaleFindings($detectedKeys, $now);

    return [
      'new' => $new,
      'new_count' => count($new),
      'resolved' => $resolvedCount,
      'open' => IntegrationMappingFinding::query()->where('status', IntegrationMappingFinding::STATUS_OPEN)->count(),
    ];
  }

  /**
   * Current set of (integration, field) pairs that need a value mapping, keyed by
   * "integrationId-fieldId".
   *
   * The bodies are the source of truth (mirrors the frontend banner and the
   * reconciler): for every {$<id>} token in any request_body of an active
   * integration, if the field has possible_values and no value_mapping is
   * configured (no mapping row, or a row with empty value_mapping), it is a finding.
   * JSON columns are read in PHP for cross-DB safety (no JSONB operators).
   *
   * @return Collection<string, array{integration_id: int, field_id: int, integration_name: string, field_name: string}>
   */
  private function detect(): Collection
  {
    $integrations = Integration::query()
      ->where('is_active', true)
      ->with(['company:id,name', 'environments:id,integration_id,request_body', 'tokenMappings:id,integration_id,field_id,value_mapping'])
      ->get();

    // Collect every field_id referenced as a token across all bodies, then load the
    // fields once to know which ones carry possible_values.
    $tokensByIntegration = [];
    $allFieldIds = [];
    foreach ($integrations as $integration) {
      $tokens = $integration->getAllRequestBodyTokens();
      $tokensByIntegration[$integration->id] = $tokens;
      foreach ($tokens as $fieldId) {
        $allFieldIds[$fieldId] = true;
      }
    }

    $fields = Field::query()
      ->whereIn('id', array_keys($allFieldIds))
      ->get(['id', 'name', 'possible_values'])
      ->keyBy('id');

    $result = collect();

    foreach ($integrations as $integration) {
      $mappings = $integration->tokenMappings->keyBy('field_id');

      foreach ($tokensByIntegration[$integration->id] as $fieldId) {
        $field = $fields->get($fieldId);
        if (!$field) {
          continue;
        }

        $possibleValues = is_array($field->possible_values) ? array_filter($field->possible_values, fn($v) => $v !== null && $v !== '') : [];
        if (count($possibleValues) === 0) {
          continue;
        }

        $mapping = $mappings->get($fieldId);
        if ($mapping && !empty($mapping->value_mapping)) {
          continue;
        }

        $result->put("{$integration->id}-{$fieldId}", [
          'integration_id' => $integration->id,
          'field_id' => $fieldId,
          'integration_name' => $integration->name,
          'company_name' => $integration->company?->name ?? '—',
          'field_name' => $field->name,
        ]);
      }
    }

    return $result;
  }

  /**
   * Mark as resolved every open finding whose (integration, field) is not in the
   * current detected set.
   *
   * @param  array<int, string>  $detectedKeys
   */
  private function resolveStaleFindings(array $detectedKeys, Carbon $now): int
  {
    $count = 0;

    IntegrationMappingFinding::query()
      ->where('status', IntegrationMappingFinding::STATUS_OPEN)
      ->get()
      ->each(function (IntegrationMappingFinding $finding) use ($detectedKeys, $now, &$count) {
        $key = "{$finding->integration_id}-{$finding->field_id}";
        if (in_array($key, $detectedKeys, true)) {
          return;
        }
        $finding->update(['status' => IntegrationMappingFinding::STATUS_RESOLVED, 'resolved_at' => $now]);
        $count++;
      });

    return $count;
  }
}
