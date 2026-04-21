<?php

namespace App\Services\PingPost;

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
   * @param  array<int, array{field: string, operator: string, value: mixed, sort_order?: int}>  $rules
   */
  public function syncEligibilityRules(Integration $integration, array $rules): void
  {
    $integration->eligibilityRules()->delete();

    foreach ($rules as $index => $rule) {
      $integration->eligibilityRules()->create([
        'field' => $rule['field'],
        'operator' => $rule['operator'],
        'value' => $rule['value'],
        'sort_order' => $rule['sort_order'] ?? $index,
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
}
