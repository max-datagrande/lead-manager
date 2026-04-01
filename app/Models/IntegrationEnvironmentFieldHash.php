<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Stores the per-environment hash configuration for a field token.
 * One row per (integration_environment, field) pair.
 *
 * This separation from IntegrationFieldMapping allows the same field
 * to be hashed differently across env_types — e.g. md5 in ping but
 * no hashing in post, within the same integration.
 *
 * @property int $id
 * @property int $integration_environment_id
 * @property int $field_id
 * @property bool $is_hashed
 * @property string|null $hash_algorithm
 * @property string|null $hmac_secret
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class IntegrationEnvironmentFieldHash extends Model
{
  use HasFactory;

  protected $fillable = [
    'integration_environment_id',
    'field_id',
    'is_hashed',
    'hash_algorithm',
    'hmac_secret',
  ];

  protected $casts = [
    'is_hashed' => 'boolean',
  ];

  /**
   * Get the environment that owns this hash config.
   */
  public function environment(): BelongsTo
  {
    return $this->belongsTo(IntegrationEnvironment::class, 'integration_environment_id');
  }

  /**
   * Get the field referenced by this hash config.
   */
  public function field(): BelongsTo
  {
    return $this->belongsTo(Field::class);
  }
}
