<?php

namespace App\Services\PingPost;

use App\Models\BuyerEligibilityRule;
use App\Models\Field;
use App\Models\Integration;
use Illuminate\Support\Collection;

class EligibilityCheckerService
{
  /**
   * Check if an integration is eligible for the given lead data.
   *
   * Rules are grouped by `group_index`. Within a group, every rule must pass
   * (AND). Across groups, at least one group must fully pass (OR).
   * No rules → eligible.
   */
  public function isEligible(Integration $integration, array $leadData): bool
  {
    $groups = $this->groupedRules($integration);

    if ($groups->isEmpty()) {
      return true;
    }

    $leadData = $this->resolveArrayFields($leadData);

    return $groups->contains(function (Collection $rules) use ($leadData): bool {
      return $rules->every(fn(BuyerEligibilityRule $rule): bool => $rule->evaluate($leadData));
    });
  }

  /**
   * Return a human-readable reason describing why every group failed,
   * or null if any group passed (eligible).
   *
   * Format: "Set 1 failed at field=X operator=Y actual=V; Set 2 failed at ..."
   * The set number is 1-based; the actual value is formatted via formatActualValue().
   */
  public function getSkipReason(Integration $integration, array $leadData): ?string
  {
    $groups = $this->groupedRules($integration);

    if ($groups->isEmpty()) {
      return null;
    }

    $leadData = $this->resolveArrayFields($leadData);
    $parts = [];
    $setNumber = 0;

    foreach ($groups as $rules) {
      $setNumber++;
      $firstFailure = null;

      foreach ($rules as $rule) {
        if (!$rule->evaluate($leadData)) {
          $firstFailure ??= $rule;
        }
      }

      if ($firstFailure === null) {
        return null;
      }

      $actual = $this->formatActualValue($leadData, $firstFailure->field);
      $parts[] = "Set {$setNumber} failed at field={$firstFailure->field} operator={$firstFailure->operator} actual={$actual}";
    }

    return implode('; ', $parts);
  }

  /**
   * Group an integration's eligibility rules by their group_index, preserving
   * the original sort_order within each group.
   *
   * @return Collection<int, Collection<int, BuyerEligibilityRule>>
   */
  private function groupedRules(Integration $integration): Collection
  {
    return $integration->eligibilityRules->sortBy('sort_order')->groupBy('group_index')->sortKeys()->values();
  }

  /**
   * Render a lead value as a short human-readable string for skip reasons.
   * Differentiates between null, empty string, false, missing key, arrays, etc.
   */
  private function formatActualValue(array $leadData, string $field): string
  {
    if (!array_key_exists($field, $leadData)) {
      return '(missing)';
    }

    $value = $leadData[$field];

    if ($value === null) {
      return 'null';
    }

    if ($value === '') {
      return '(empty)';
    }

    if (is_bool($value)) {
      return $value ? 'true' : 'false';
    }

    if (is_array($value)) {
      $encoded = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

      return $encoded === false ? '(unencodable)' : $encoded;
    }

    return (string) $value;
  }

  /**
   * Split semicolon-delimited string values into arrays
   * for fields marked as is_array in the Field model.
   */
  private function resolveArrayFields(array $leadData): array
  {
    $arrayFieldNames = Field::where('is_array', true)->pluck('name')->all();

    foreach ($arrayFieldNames as $name) {
      if (isset($leadData[$name]) && is_string($leadData[$name])) {
        $leadData[$name] = array_values(array_filter(array_map('trim', explode(';', $leadData[$name])), fn(string $v): bool => $v !== ''));
      }
    }

    return $leadData;
  }
}
