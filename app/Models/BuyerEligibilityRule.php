<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BuyerEligibilityRule extends Model
{
  protected $fillable = ['integration_id', 'field', 'operator', 'value', 'sort_order'];

  protected $casts = [
    'value' => 'array',
  ];

  public function integration(): BelongsTo
  {
    return $this->belongsTo(Integration::class);
  }

  /**
   * Evaluate this rule against the given lead data.
   * Supports array field values (pre-resolved by EligibilityCheckerService).
   */
  public function evaluate(array $leadData): bool
  {
    $fieldValue = $leadData[$this->field] ?? null;
    $ruleValue = $this->value;

    if (is_array($fieldValue)) {
      return $this->evaluateArray($fieldValue, $ruleValue);
    }

    return match ($this->operator) {
      'eq' => $fieldValue == $ruleValue,
      'neq' => $fieldValue != $ruleValue,
      'gt' => is_numeric($fieldValue) && $fieldValue > $ruleValue,
      'gte' => is_numeric($fieldValue) && $fieldValue >= $ruleValue,
      'lt' => is_numeric($fieldValue) && $fieldValue < $ruleValue,
      'lte' => is_numeric($fieldValue) && $fieldValue <= $ruleValue,
      'in' => in_array($fieldValue, (array) $ruleValue),
      'not_in' => !in_array($fieldValue, (array) $ruleValue),
      'is_empty' => $fieldValue === null || $fieldValue === '',
      'is_not_empty' => $fieldValue !== null && $fieldValue !== '',
      default => false,
    };
  }

  /**
   * Evaluate this rule when the field value is an array (e.g. semicolon-delimited fields).
   */
  private function evaluateArray(array $fieldValue, mixed $ruleValue): bool
  {
    return match ($this->operator) {
      'is_empty' => count($fieldValue) === 0,
      'is_not_empty' => count($fieldValue) > 0,
      'eq' => in_array($ruleValue, $fieldValue),
      'neq' => !in_array($ruleValue, $fieldValue),
      'in' => count(array_intersect($fieldValue, (array) $ruleValue)) > 0,
      'not_in' => count(array_intersect($fieldValue, (array) $ruleValue)) === 0,
      'gt', 'gte', 'lt', 'lte' => false,
      default => false,
    };
  }
}
