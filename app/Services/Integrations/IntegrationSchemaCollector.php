<?php

namespace App\Services\Integrations;

use App\Models\Field;
use App\Models\Integration;
use App\Models\IntegrationFieldMapping;
use App\Models\Workflow;
use Illuminate\Support\Collection;

/**
 * Collects the validation schema of every field referenced across the request
 * bodies of an integration (or, aggregated, of a whole workflow).
 *
 * The source of truth are the {$<field_id>} tokens of the request bodies (same
 * as IntegrationMappingReconciler / UnmappedValueMappingScanner), NOT the
 * integration_field_mappings rows.
 *
 * Two output shapes:
 *  - integration: per-field rules with `required`/`default`/`rules` (string).
 *  - workflow: aggregated catalog with only `array`/`enum` (+ `note`); no
 *    `required`/`default`/type, since those are not exclusive once several
 *    integrations are merged.
 */
class IntegrationSchemaCollector
{
  /**
   * Disclaimer emitted as `note` on every array field, in both modes.
   */
  public const ARRAY_FIELD_NOTE = 'Array fields must be sent from the SDK as a single string with values separated by `;` (e.g. value1;value2;value3).';

  /**
   * Build the per-field validation schema for a single integration.
   *
   * @return array{meta: array<string, mixed>, schema: array<string, array<string, mixed>>}
   */
  public function forIntegration(Integration $integration): array
  {
    $tokens = $integration->getAllRequestBodyTokens();
    $fields = Field::query()->whereIn('id', $tokens)->get()->keyBy('id');
    $mappings = $integration->tokenMappings()->get()->keyBy('field_id');

    $schema = [];
    $warnings = [];

    foreach ($tokens as $fieldId) {
      $field = $fields->get($fieldId);

      if ($field === null) {
        $warnings[] = "References missing field #{$fieldId}";
        continue;
      }

      $schema[$field->name] = $this->integrationFieldRule($field, $mappings->get($fieldId));
    }

    ksort($schema);

    return [
      'meta' => $this->meta(
        [
          'source' => 'integration',
          'id' => $integration->id,
          'name' => $integration->name,
        ],
        $schema,
        $warnings,
      ),
      'schema' => $schema,
    ];
  }

  /**
   * Build the aggregated schema for every integration in a workflow.
   *
   * Includes ALL integrations of the workflow (no `is_active` pivot filter).
   *
   * @return array{meta: array<string, mixed>, schema: array<string, array<string, mixed>>}
   */
  public function forWorkflow(Workflow $workflow): array
  {
    $integrations = $workflow->integrations()->with('environments')->get();

    return $this->forIntegrations($integrations, [
      'source' => 'workflow',
      'id' => $workflow->id,
      'name' => $workflow->name,
      'integration_count' => $integrations->count(),
      'integrations' => $integrations->map(fn(Integration $i) => ['id' => $i->id, 'name' => $i->name])->values()->all(),
    ]);
  }

  /**
   * Composable core: aggregate the workflow-shaped schema across N integrations.
   *
   * forWorkflow() delegates here; a future higher level (e.g. a set of
   * workflows) can reuse the same primitive.
   *
   * @param Collection<int, Integration> $integrations
   * @param array<string, mixed> $meta
   * @return array{meta: array<string, mixed>, schema: array<string, array<string, mixed>>}
   */
  public function forIntegrations(Collection $integrations, array $meta = []): array
  {
    $schema = [];
    $warnings = [];

    foreach ($integrations as $integration) {
      $tokens = $integration->getAllRequestBodyTokens();
      $fields = Field::query()->whereIn('id', $tokens)->get()->keyBy('id');

      foreach ($tokens as $fieldId) {
        $field = $fields->get($fieldId);

        if ($field === null) {
          $warnings[] = "Integration #{$integration->id} references missing field #{$fieldId}";
          continue;
        }

        $entry = $this->workflowFieldEntry($field);

        $schema[$field->name] = isset($schema[$field->name]) ? $this->mergeWorkflowFields($schema[$field->name], $entry) : $entry;
      }
    }

    ksort($schema);

    return [
      'meta' => $this->meta($meta, $schema, $warnings),
      'schema' => $schema,
    ];
  }

  /**
   * Per-field rule for the integration shape (with required/default/rules).
   *
   * @return array<string, mixed>
   */
  private function integrationFieldRule(Field $field, ?IntegrationFieldMapping $mapping): array
  {
    $hasDefault = $mapping !== null && $mapping->default_value !== null;
    $isArray = (bool) $field->is_array;
    $enum = $this->enumValues($field);
    $base = $hasDefault ? 'nullable' : 'required';

    $entry = [
      'required' => !$hasDefault,
      'array' => $isArray,
    ];

    if ($enum !== null) {
      $entry['enum'] = $enum;
    }

    if ($hasDefault) {
      $entry['default'] = $mapping->default_value;
    }

    if ($isArray) {
      $entry['rules'] = "{$base}|array";

      $each = ['string'];
      if ($enum !== null) {
        $each[] = 'in:' . implode(',', $enum);
      }
      $entry['each'] = implode('|', $each);
      $entry['note'] = self::ARRAY_FIELD_NOTE;
    } else {
      $rules = [$base, 'string'];
      if ($enum !== null) {
        $rules[] = 'in:' . implode(',', $enum);
      }
      $entry['rules'] = implode('|', $rules);
    }

    return $entry;
  }

  /**
   * Per-field entry for the workflow shape (only array/enum/note).
   *
   * @return array<string, mixed>
   */
  private function workflowFieldEntry(Field $field): array
  {
    $entry = ['array' => (bool) $field->is_array];

    $enum = $this->enumValues($field);
    if ($enum !== null) {
      $entry['enum'] = $enum;
    }

    if ($field->is_array) {
      $entry['note'] = self::ARRAY_FIELD_NOTE;
    }

    return $entry;
  }

  /**
   * Merge two workflow-shaped entries for the same field name: union of enum,
   * OR of array. No type conflicts (there is no type at workflow level).
   *
   * @param array<string, mixed> $a
   * @param array<string, mixed> $b
   * @return array<string, mixed>
   */
  private function mergeWorkflowFields(array $a, array $b): array
  {
    $isArray = ($a['array'] ?? false) || ($b['array'] ?? false);
    $merged = ['array' => $isArray];

    $enum = array_values(array_unique(array_merge($a['enum'] ?? [], $b['enum'] ?? [])));
    if (!empty($enum)) {
      $merged['enum'] = $enum;
    }

    if ($isArray) {
      $merged['note'] = self::ARRAY_FIELD_NOTE;
    }

    return $merged;
  }

  /**
   * Normalize possible_values to a non-empty list, or null when absent/empty.
   *
   * @return array<int, mixed>|null
   */
  private function enumValues(Field $field): ?array
  {
    $values = $field->possible_values;

    if (!is_array($values) || count($values) === 0) {
      return null;
    }

    return array_values($values);
  }

  /**
   * Build the meta block, dropping null/empty optionals.
   *
   * @param array<string, mixed> $base
   * @param array<string, mixed> $schema
   * @param array<int, string> $warnings
   * @return array<string, mixed>
   */
  private function meta(array $base, array $schema, array $warnings): array
  {
    return array_merge(
      $base,
      array_filter(
        [
          'field_count' => count($schema),
          'warnings' => $warnings ?: null,
        ],
        fn($value) => $value !== null,
      ),
    );
  }
}
