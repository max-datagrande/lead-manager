<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Response configuration for offerwall-type integration environments.
 *
 * Defines how to parse the external offerwall API response: where to find the
 * list of offers, how to map each offer's fields to normalized keys, and
 * fallback values for fields that might come back empty.
 *
 * @property int $id
 * @property int $integration_environment_id
 * @property string|null $offer_list_path
 * @property array $mapping
 * @property array $fallbacks
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class OfferwallResponseConfig extends Model
{
  protected $guarded = [];

  /**
   * Default mapping skeleton — ensures all expected keys are always present.
   * When the frontend sends null, these defaults are persisted instead.
   */
  protected $attributes = [
    'mapping' =>
      '{"title":null,"description":null,"logo_url":null,"click_url":null,"impression_url":null,"cpc":null,"display_name":null,"company":null}',
    'fallbacks' => '{"title":null,"description":null}',
  ];

  /**
   * @return array<string, string>
   */
  protected function casts(): array
  {
    return [
      'mapping' => 'array',
      'fallbacks' => 'array',
    ];
  }

  /**
   * Get the integration environment that owns this config.
   */
  public function integrationEnvironment(): BelongsTo
  {
    return $this->belongsTo(IntegrationEnvironment::class);
  }
}
