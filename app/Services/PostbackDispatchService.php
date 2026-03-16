<?php

namespace App\Services;

use App\Models\PostbackDispatchLog;
use App\Models\PostbackExecution;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PostbackDispatchService
{
  /**
   * Ejecuta HTTP GET outbound y registra el intento.
   */
  public function dispatch(PostbackExecution $execution): PostbackDispatchLog
  {
    $execution->markAsDispatching();
    $execution->incrementAttempt();

    $log = PostbackDispatchLog::create([
      'execution_id' => $execution->id,
      'attempt_number' => $execution->attempts,
      'request_url' => $execution->outbound_url,
      'request_method' => 'GET',
    ]);

    $startTime = microtime(true);

    try {
      if (app()->environment(['local', 'testing'])) {
        $this->simulateLocalDispatch($execution, $log, $startTime);

        return $log;
      }

      $response = Http::timeout(30)->withoutVerifying()->get($execution->outbound_url);

      $responseTimeMs = (int) ((microtime(true) - $startTime) * 1000);

      $log->update([
        'response_status_code' => $response->status(),
        'response_body' => mb_substr($response->body(), 0, 5000),
        'response_headers' => $response->headers(),
        'response_time_ms' => $responseTimeMs,
      ]);

      if ($response->successful()) {
        $execution->markAsCompleted();
      } else {
        $execution->markAsFailed('HTTP ' . $response->status());
      }
    } catch (\Throwable $e) {
      $responseTimeMs = (int) ((microtime(true) - $startTime) * 1000);

      $log->update([
        'error_message' => mb_substr($e->getMessage(), 0, 2000),
        'response_time_ms' => $responseTimeMs,
      ]);

      $execution->markAsFailed($e->getMessage());

      Log::warning('Postback dispatch failed', [
        'execution_id' => $execution->id,
        'outbound_url' => $execution->outbound_url,
        'error' => $e->getMessage(),
      ]);
    }

    return $log;
  }

  /**
   * Simula dispatch en entorno local sin hacer request real.
   */
  private function simulateLocalDispatch(PostbackExecution $execution, PostbackDispatchLog $log, float $startTime): void
  {
    $responseTimeMs = (int) ((microtime(true) - $startTime) * 1000);

    $log->update([
      'response_status_code' => 200,
      'response_body' => json_encode(['simulated' => true, 'environment' => 'local']),
      'response_time_ms' => $responseTimeMs,
    ]);

    $execution->markAsCompleted();

    Log::info('Postback dispatch simulated (local)', [
      'execution_id' => $execution->id,
      'outbound_url' => $execution->outbound_url,
    ]);
  }
}
