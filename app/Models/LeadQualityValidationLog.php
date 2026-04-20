<?php

namespace App\Models;

use App\Enums\LeadQuality\ValidationLogStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class LeadQualityValidationLog extends Model
{
  use HasFactory;

  protected $table = 'lead_quality_validation_logs';

  protected $fillable = [
    'validation_rule_id',
    'integration_id',
    'lead_id',
    'provider_id',
    'lead_dispatch_id',
    'fingerprint',
    'status',
    'attempts_count',
    'result',
    'context',
    'message',
    'challenge_reference',
    'started_at',
    'resolved_at',
    'expires_at',
  ];

  protected $casts = [
    'status' => ValidationLogStatus::class,
    'attempts_count' => 'integer',
    'context' => 'array',
    'started_at' => 'datetime',
    'resolved_at' => 'datetime',
    'expires_at' => 'datetime',
  ];

  public function rule(): BelongsTo
  {
    return $this->belongsTo(LeadQualityValidationRule::class, 'validation_rule_id');
  }

  public function integration(): BelongsTo
  {
    return $this->belongsTo(Integration::class, 'integration_id');
  }

  public function lead(): BelongsTo
  {
    return $this->belongsTo(Lead::class, 'lead_id');
  }

  public function provider(): BelongsTo
  {
    return $this->belongsTo(LeadQualityProvider::class, 'provider_id');
  }

  public function leadDispatch(): BelongsTo
  {
    return $this->belongsTo(LeadDispatch::class, 'lead_dispatch_id');
  }

  public function externalRequests(): MorphMany
  {
    return $this->morphMany(ExternalServiceRequest::class, 'loggable');
  }

  public function markVerified(?string $message = null): self
  {
    $this->update([
      'status' => ValidationLogStatus::VERIFIED,
      'result' => 'pass',
      'resolved_at' => now(),
      'message' => $message ?? $this->message,
    ]);

    return $this;
  }

  public function markFailed(string $message): self
  {
    $this->update([
      'status' => ValidationLogStatus::FAILED,
      'result' => 'fail',
      'resolved_at' => now(),
      'message' => $message,
    ]);

    return $this;
  }

  public function markExpired(): self
  {
    $this->update([
      'status' => ValidationLogStatus::EXPIRED,
      'result' => 'fail',
      'resolved_at' => now(),
    ]);

    return $this;
  }

  public function incrementAttempt(): self
  {
    $this->increment('attempts_count');

    return $this;
  }

  public function isExpired(): bool
  {
    return $this->expires_at !== null && $this->expires_at->isPast();
  }
}
