<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Represents a single HTTP call log made to an external integration.
 *
 * Captures the full request/response cycle including headers, payload,
 * status, duration, and field mapping context for debugging and auditing.
 *
 * The `loggable` polymorphic relationship links this log to the parent
 * entity that triggered the call. This allows different modules to reuse
 * the same logging table without coupling to a specific model. Currently
 * used by:
 *
 * - `OfferwallMixLog` — logs generated during offerwall mix aggregation
 *   and offerwall integration testing.
 *
 * The morph columns (`loggable_type`, `loggable_id`) store the parent
 * model's fully qualified class name and its primary key respectively,
 * so a single `IntegrationCallLog` always traces back to the exact
 * process execution that originated the HTTP call.
 *
 * @property int $id
 * @property string $loggable_type
 * @property int $loggable_id
 * @property int $integration_id
 * @property string $status
 * @property int $http_status_code
 * @property int $duration_ms
 * @property string $request_url
 * @property string $request_method
 * @property array|null $request_headers
 * @property array|null $request_payload
 * @property array|null $response_headers
 * @property array|string|null $response_body
 * @property array|null $original_field_values
 * @property array|null $mapped_field_values
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class IntegrationCallLog extends Model
{
  protected $guarded = [];

  /**
   * @return array<string, string>
   */
  protected function casts(): array
  {
    return [
      'http_status_code' => 'integer',
      'duration_ms' => 'integer',
      'request_headers' => 'array',
      'request_payload' => 'array',
      'response_headers' => 'array',
      'response_body' => 'array',
      'original_field_values' => 'array',
      'mapped_field_values' => 'array',
    ];
  }

  /**
   * Get the parent model that triggered this integration call.
   *
   * This is a polymorphic relationship. The `loggable_type` column stores the
   * fully qualified class name (e.g. `App\Models\OfferwallMixLog`) and
   * `loggable_id` stores its primary key. Any model that defines a
   * `morphMany(IntegrationCallLog::class, 'loggable')` relationship can
   * act as a parent.
   */
  public function loggable(): MorphTo
  {
    return $this->morphTo();
  }

  /**
   * Get the integration that was called.
   */
  public function integration(): BelongsTo
  {
    return $this->belongsTo(Integration::class);
  }
}
