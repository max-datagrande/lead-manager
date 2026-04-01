<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Represents the execution log of a single offerwall mix aggregation run.
 *
 * Each time an `OfferwallMix` is executed (either via real traffic in
 * `MixService` or through the offerwall tester in `TesterService`), a
 * new `OfferwallMixLog` record is created to capture the overall outcome:
 * how many integrations were called, how many succeeded/failed, the total
 * number of offers returned, and the total processing time.
 *
 * This model also acts as the polymorphic parent for `IntegrationCallLog`
 * records via the `integrationCallLogs()` morph relationship, linking
 * individual HTTP call details back to the mix execution that triggered them.
 *
 * @property int $id
 * @property int $offerwall_mix_id
 * @property string $fingerprint
 * @property string|null $origin
 * @property string|null $placement
 * @property int $total_integrations
 * @property int $successful_integrations
 * @property int $failed_integrations
 * @property int $total_offers_aggregated
 * @property int $total_duration_ms
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class OfferwallMixLog extends Model
{
  protected $guarded = [];

  /**
   * @return array<string, string>
   */
  protected function casts(): array
  {
    return [
      'total_integrations' => 'integer',
      'successful_integrations' => 'integer',
      'failed_integrations' => 'integer',
      'total_offers_aggregated' => 'integer',
      'total_duration_ms' => 'integer',
    ];
  }

  /**
   * Get the offerwall mix that owns this log.
   */
  public function offerwallMix(): BelongsTo
  {
    return $this->belongsTo(OfferwallMix::class);
  }

  /**
   * Get the individual integration HTTP call logs for this mix execution.
   *
   * Each record represents a single request/response cycle to an external
   * integration, linked back to this mix log via the `loggable` morph.
   */
  public function integrationCallLogs(): MorphMany
  {
    return $this->morphMany(IntegrationCallLog::class, 'loggable');
  }
}
