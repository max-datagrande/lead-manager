<?php

namespace App\Models;

use App\Enums\PostResultStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PostResult extends Model
{
  protected $fillable = [
    'lead_dispatch_id',
    'ping_result_id',
    'integration_id',
    'status',
    'price_offered',
    'price_final',
    'http_status_code',
    'request_url',
    'request_payload',
    'request_headers',
    'response_body',
    'duration_ms',
    'rejection_reason',
    'attempt_count',
    'postback_received_at',
    'postback_expires_at',
  ];

  protected $casts = [
    'status' => PostResultStatus::class,
    'price_offered' => 'decimal:4',
    'price_final' => 'decimal:4',
    'request_payload' => 'array',
    'request_headers' => 'array',
    'response_body' => 'array',
    'postback_received_at' => 'datetime',
    'postback_expires_at' => 'datetime',
  ];

  public function leadDispatch(): BelongsTo
  {
    return $this->belongsTo(LeadDispatch::class);
  }

  public function pingResult(): BelongsTo
  {
    return $this->belongsTo(PingResult::class);
  }

  public function integration(): BelongsTo
  {
    return $this->belongsTo(Integration::class);
  }
}
