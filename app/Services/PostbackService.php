<?php

namespace App\Services;

use App\Models\PostbackApiRequests;
use Illuminate\Database\Eloquent\Collection;

class PostbackService
{
  public function getApiRequests(int $postbackId): Collection
  {
    return PostbackApiRequests::where('postback_id', $postbackId)
      ->orderBy('created_at', 'desc')
      ->get([
        'id',
        'request_id',
        'service',
        'endpoint',
        'method',
        'request_data',
        'response_data',
        'status_code',
        'error_message',
        'response_time_ms',
        'created_at'
      ]);
  }
}
