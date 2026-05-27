<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A detected configuration gap on an integration field mapping, surfaced by a
 * system scan (currently: a field with possible_values whose value_mapping is
 * not configured). One row per (integration, field); status tracks its lifecycle.
 *
 * @property int $id
 * @property int $integration_id
 * @property int $field_id
 * @property string $status
 * @property \Illuminate\Support\Carbon|null $first_detected_at
 * @property \Illuminate\Support\Carbon|null $last_seen_at
 * @property \Illuminate\Support\Carbon|null $resolved_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class IntegrationMappingFinding extends Model
{
  use HasFactory;

  public const STATUS_OPEN = 'open';
  public const STATUS_RESOLVED = 'resolved';
  public const STATUS_IGNORED = 'ignored';

  protected $fillable = ['integration_id', 'field_id', 'status', 'first_detected_at', 'last_seen_at', 'resolved_at'];

  protected $casts = [
    'first_detected_at' => 'datetime',
    'last_seen_at' => 'datetime',
    'resolved_at' => 'datetime',
  ];

  /**
   * The integration the finding belongs to.
   */
  public function integration(): BelongsTo
  {
    return $this->belongsTo(Integration::class);
  }

  /**
   * The field whose mapping is incomplete.
   */
  public function field(): BelongsTo
  {
    return $this->belongsTo(Field::class);
  }
}
