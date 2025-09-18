<?php

namespace App\Services;

use App\Models\PostbackApiRequests;
use Illuminate\Database\Eloquent\Collection;
use App\Models\Postback;
use Maxidev\Logger\TailLogger;

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
  public function validatePostback(int $postbackId): Postback
  {

    $postback = Postback::find($postbackId);
    //Verificar si existe
    if (!$postback) {
      throw new PostbackServiceException("Postback not found", [
        'postback_id' => $postbackId,
        'exists' => $postback ? 'sí' : 'no',
        'status' => $postback?->status ?? 'N/A'
      ]);
    }
    //Verificar si esta procesado
    if ($postback->status === Postback::STATUS_PROCESSED) {
      throw new PostbackServiceException("Postback already marked as processed", [
        'postback_id' => $postbackId,
        'exists' => $postback ? 'sí' : 'no',
        'status' => $postback?->status ?? 'N/A'
      ]);
    }
    // Verificar si no está fallido
    if ($postback->status === Postback::STATUS_FAILED) {
      throw new PostbackServiceException("Postback already marked as failed", [
        'postback_id' => $postbackId,
        'exists' => $postback ? 'sí' : 'no',
        'status' => $postback?->status ?? 'N/A'
      ]);
    }
    TailLogger::saveLog("Postback found, current status: {$postback->status}", 'jobs/postback', 'info', [
      'postback_id' => $postbackId,
      'current_status' => $postback->status,
      'created_at' => $postback->created_at
    ]);
    return $postback;
  }
}

class PostbackServiceException extends \Exception
{
  protected array $context = [];
  public function __construct(string $message, array $context = [], int $code = 0, ?\Throwable $previous = null)
  {
    parent::__construct($message, $code, $previous);
  }
  public function getContext(): array
  {
    return $this->context;
  }
}
