<?php

namespace App\Services\PingPost;

use App\Models\Buyer;
use App\Models\BuyerConfig;
use App\Models\Integration;

class BuyerConfigService
{
  public function createConfig(Integration $integration, array $data): BuyerConfig
  {
    return $integration->buyerConfig()->create($data);
  }

  public function updateConfig(BuyerConfig $config, array $data): BuyerConfig
  {
    $config->update($data);

    return $config->fresh();
  }

  /**
   * Sync the pricing postback pivot for a buyer config.
   *
   * @param  array{postback_id: int, identifier_token: string, price_token: string}|null  $data
   */
  public function syncPricingPostback(BuyerConfig $config, ?array $data): void
  {
    $config->pricingPostback()->detach();

    if ($data && isset($data['postback_id'])) {
      $config->pricingPostback()->attach($data['postback_id'], [
        'identifier_token' => $data['identifier_token'],
        'price_token' => $data['price_token'],
      ]);
    }
  }

  public function deleteConfig(BuyerConfig $config): void
  {
    $config->delete();
  }

  /**
   * Replace all eligibility rules for the integration.
   *
   * @param  array<int, array{field: string, operator: string, value: mixed, sort_order?: int, group_index?: int}>  $rules
   */
  public function syncEligibilityRules(Integration $integration, array $rules): void
  {
    $integration->eligibilityRules()->delete();

    foreach ($rules as $index => $rule) {
      $integration->eligibilityRules()->create([
        'field' => $rule['field'],
        'operator' => $rule['operator'],
        'value' => $rule['value'] ?? null,
        'sort_order' => $rule['sort_order'] ?? $index,
        'group_index' => $rule['group_index'] ?? 0,
      ]);
    }
  }

  /**
   * Replace all cap rules for the integration.
   *
   * @param  array<int, array{period: string, max_leads?: int|null, max_revenue?: float|null}>  $caps
   */
  public function syncCapRules(Integration $integration, array $caps): void
  {
    $integration->capRules()->delete();

    foreach ($caps as $cap) {
      $integration->capRules()->create([
        'period' => $cap['period'],
        'max_leads' => $cap['max_leads'] ?? null,
        'max_revenue' => $cap['max_revenue'] ?? null,
      ]);
    }
  }

  /**
   * Replace all schedule windows for a buyer.
   *
   * @param  array<int, array{days_of_week: array<int>, start_time: string, end_time: string, sort_order?: int}>  $windows
   */
  public function syncScheduleWindows(Buyer $buyer, array $windows): void
  {
    $buyer->scheduleWindows()->delete();

    foreach ($windows as $index => $window) {
      $buyer->scheduleWindows()->create([
        'days_of_week' => array_values(array_map('intval', $window['days_of_week'])),
        'start_time' => $window['start_time'],
        'end_time' => $window['end_time'],
        'sort_order' => $window['sort_order'] ?? $index,
      ]);
    }
  }
}
