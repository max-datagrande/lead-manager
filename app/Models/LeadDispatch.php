<?php

namespace App\Models;

use App\Enums\DispatchStatus;
use App\Events\LeadSold;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class LeadDispatch extends Model
{
  protected $fillable = [
    'dispatch_uuid',
    'workflow_id',
    'lead_id',
    'fingerprint',
    'lead_snapshot',
    'status',
    'strategy_used',
    'winner_integration_id',
    'final_price',
    'fallback_activated',
    'total_duration_ms',
    'error_message',
    'started_at',
    'completed_at',
  ];

  protected $casts = [
    'lead_snapshot' => 'array',
    'status' => DispatchStatus::class,
    'final_price' => 'decimal:4',
    'fallback_activated' => 'boolean',
    'started_at' => 'datetime',
    'completed_at' => 'datetime',
  ];

  protected static function booted(): void
  {
    static::creating(function (LeadDispatch $dispatch): void {
      $dispatch->dispatch_uuid ??= (string) Str::uuid();
    });
  }

  public function workflow(): BelongsTo
  {
    return $this->belongsTo(Workflow::class);
  }

  public function lead(): BelongsTo
  {
    return $this->belongsTo(Lead::class);
  }

  public function winnerIntegration(): BelongsTo
  {
    return $this->belongsTo(Integration::class, 'winner_integration_id');
  }

  public function pingResults(): HasMany
  {
    return $this->hasMany(PingResult::class);
  }

  public function postResults(): HasMany
  {
    return $this->hasMany(PostResult::class);
  }

  public function markAsSold(Integration $winner, float $price): void
  {
    $this->update([
      'status' => DispatchStatus::SOLD,
      'winner_integration_id' => $winner->id,
      'final_price' => $price,
      'completed_at' => now(),
    ]);

    LeadSold::dispatch($this);
  }

  public function markAsNotSold(): void
  {
    $this->update([
      'status' => DispatchStatus::NOT_SOLD,
      'completed_at' => now(),
    ]);
  }

  public function markAsError(string $message): void
  {
    $this->update([
      'status' => DispatchStatus::ERROR,
      'error_message' => $message,
      'completed_at' => now(),
    ]);
  }

  public function markAsTimeout(): void
  {
    $this->update([
      'status' => DispatchStatus::TIMEOUT,
      'completed_at' => now(),
    ]);
  }

  /**
   * Generate a deterministic idempotency key for a ping attempt.
   */
  public static function generateIdempotencyKey(int $workflowId, int $integrationId, string $fingerprint): string
  {
    return hash('sha256', implode('|', [$workflowId, $integrationId, $fingerprint]));
  }
}
