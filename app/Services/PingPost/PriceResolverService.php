<?php

namespace App\Services\PingPost;

use App\Enums\PriceSource;
use App\Models\BuyerConfig;

class PriceResolverService
{
  /**
   * Resolve the final price for a given bid, returning null if the bid should be rejected.
   */
  public function resolvePrice(BuyerConfig $config, float $bidPrice): ?float
  {
    if ($config->price_source === null) {
      throw new \RuntimeException("Buyer config #{$config->id} (integration #{$config->integration_id}) has no price_source configured.");
    }

    return match ($config->price_source) {
      PriceSource::FIXED => (float) $config->fixed_price,
      PriceSource::RESPONSE_BID => $this->isPriceAcceptable($config, $bidPrice) ? $bidPrice : null,
      PriceSource::CONDITIONAL => null, // resolved separately via resolveConditionalPrice
      PriceSource::POSTBACK => null, // resolved asynchronously
    };
  }

  /**
   * Check if the bid meets or exceeds the minimum threshold.
   */
  public function isPriceAcceptable(BuyerConfig $config, float $bidPrice): bool
  {
    if ($config->price_source !== PriceSource::RESPONSE_BID) {
      return true;
    }

    return $bidPrice >= (float) ($config->min_bid ?? 0);
  }

  /**
   * Evaluate conditional pricing rules and return the matched price, or null.
   *
   * @param  array<int, array{conditions: array<int, array{field: string, op: string, value: mixed}>, price: float}>  $rules
   */
  public function resolveConditionalPrice(BuyerConfig $config, array $leadData): ?float
  {
    $rules = $config->conditional_pricing_rules ?? [];

    foreach ($rules as $rule) {
      if ($this->evaluateConditions($rule['conditions'] ?? [], $leadData)) {
        return (float) $rule['price'];
      }
    }

    return null;
  }

  /**
   * @param  array<int, array{field: string, op: string, value: mixed}>  $conditions
   */
  private function evaluateConditions(array $conditions, array $leadData): bool
  {
    foreach ($conditions as $condition) {
      $fieldValue = $leadData[$condition['field']] ?? null;
      $ruleValue = $condition['value'];

      $passes = match ($condition['op']) {
        'eq' => $fieldValue == $ruleValue,
        'neq' => $fieldValue != $ruleValue,
        'gt' => is_numeric($fieldValue) && $fieldValue > $ruleValue,
        'gte' => is_numeric($fieldValue) && $fieldValue >= $ruleValue,
        'lt' => is_numeric($fieldValue) && $fieldValue < $ruleValue,
        'lte' => is_numeric($fieldValue) && $fieldValue <= $ruleValue,
        'in' => in_array($fieldValue, (array) $ruleValue),
        'not_in' => !in_array($fieldValue, (array) $ruleValue),
        default => false,
      };

      if (!$passes) {
        return false;
      }
    }

    return true;
  }
}
