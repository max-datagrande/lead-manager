<?php

namespace App\Models;

use App\Enums\PingResultStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PingResult extends Model
{
  protected $fillable = [
    'lead_dispatch_id',
    'integration_id',
    'idempotency_key',
    'status',
    'bid_price',
    'http_status_code',
    'request_url',
    'request_payload',
    'request_headers',
    'response_body',
    'duration_ms',
    'skip_reason',
    'attempt_count',
  ];

  protected $casts = [
    'status' => PingResultStatus::class,
    'bid_price' => 'decimal:4',
    'request_payload' => 'array',
    'request_headers' => 'array',
    'response_body' => 'array',
  ];

  public function leadDispatch(): BelongsTo
  {
    return $this->belongsTo(LeadDispatch::class);
  }

  public function integration(): BelongsTo
  {
    return $this->belongsTo(Integration::class);
  }

  public function postResult(): HasOne
  {
    return $this->hasOne(PostResult::class);
  }
}
