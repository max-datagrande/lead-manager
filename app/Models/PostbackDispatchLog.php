<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PostbackDispatchLog extends Model
{
  protected $fillable = [
    'execution_id',
    'attempt_number',
    'request_url',
    'request_method',
    'request_headers',
    'response_status_code',
    'response_body',
    'response_headers',
    'response_time_ms',
    'error_message',
  ];

  protected $casts = [
    'request_headers' => 'array',
    'response_headers' => 'array',
    'response_status_code' => 'integer',
    'response_time_ms' => 'integer',
  ];

  public function execution(): BelongsTo
  {
    return $this->belongsTo(PostbackExecution::class, 'execution_id');
  }

  public function isSuccessful(): bool
  {
    return $this->response_status_code >= 200 && $this->response_status_code < 300;
  }
}
