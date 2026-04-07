<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Stores the global token mapping config for a field used in an integration.
 * One row per (integration, field) pair. The field_id acts as the token identifier
 * in request_body templates (e.g. {$42} where 42 is the field_id).
 *
 * Hash config is intentionally NOT stored here — it lives per-environment
 * in integration_environment_field_hashes, since the same field may need
 * different hashing in different env_types (e.g. md5 in ping, none in post).
 *
 * @property int $id
 * @property int $integration_id
 * @property int $field_id
 * @property string $data_type
 * @property string|null $default_value
 * @property array|null $value_mapping
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class IntegrationFieldMapping extends Model
{
  use HasFactory;

  protected $fillable = ['integration_id', 'field_id', 'data_type', 'default_value', 'value_mapping'];

  protected $casts = [
    'value_mapping' => 'array',
  ];

  /**
   * Get the integration that owns this mapping.
   */
  public function integration(): BelongsTo
  {
    return $this->belongsTo(Integration::class);
  }

  /**
   * Get the field referenced by this mapping.
   */
  public function field(): BelongsTo
  {
    return $this->belongsTo(Field::class);
  }
}
