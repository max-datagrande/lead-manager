<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Response configuration for ping-type integration environments.
 *
 * Defines how to interpret the buyer's ping response: where to find the
 * bid price, how to determine if the lead was accepted, and where to
 * extract the external lead ID assigned by the buyer.
 *
 * @property int $id
 * @property int $integration_environment_id
 * @property string|null $bid_price_path
 * @property string|null $accepted_path
 * @property string|null $accepted_value
 * @property string|null $lead_id_path
 * @property string|null $error_path
 * @property string|null $error_value
 * @property string|null $error_reason_path
 * @property array|null $error_excludes
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class PingResponseConfig extends Model
{
  protected $guarded = [];

  protected $casts = [
    'error_excludes' => 'array',
  ];

  /**
   * Get the integration environment that owns this config.
   */
  public function integrationEnvironment(): BelongsTo
  {
    return $this->belongsTo(IntegrationEnvironment::class);
  }
}
