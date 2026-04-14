<?php

namespace App\Services\PingPost;

use App\Models\Field;
use App\Models\Integration;

class EligibilityCheckerService
{
  /**
   * Check if an integration is eligible for the given lead data
   * by evaluating all its eligibility rules (AND logic).
   */
  public function isEligible(Integration $integration, array $leadData): bool
  {
    $rules = $integration->eligibilityRules;

    if ($rules->isEmpty()) {
      return true;
    }

    $leadData = $this->resolveArrayFields($leadData);

    foreach ($rules as $rule) {
      if (!$rule->evaluate($leadData)) {
        return false;
      }
    }

    return true;
  }

  /**
   * Return the first failing rule description, or null if all pass.
   */
  public function getSkipReason(Integration $integration, array $leadData): ?string
  {
    $leadData = $this->resolveArrayFields($leadData);

    foreach ($integration->eligibilityRules as $rule) {
      if (!$rule->evaluate($leadData)) {
        return "Rule failed: field={$rule->field} operator={$rule->operator}";
      }
    }

    return null;
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
