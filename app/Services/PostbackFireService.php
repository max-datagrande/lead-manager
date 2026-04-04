<?php

namespace App\Services;

use App\Enums\ExecutionStatus;
use App\Enums\FireMode;
use App\Jobs\DispatchPostbackJob;
use App\Models\Postback;
use App\Models\PostbackExecution;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Log;

class PostbackFireService
{
  public function __construct(protected PostbackDispatchService $dispatchService) {}

  /**
   * Recibe un request inbound, identifica el postback, resuelve tokens, y despacha o encola.
   *
   * @param  array<string, string>  $inboundParams
   *
   * @throws ModelNotFoundException
   */
  public function handleInbound(string $uuid, array $inboundParams, ?string $ipAddress = null, ?string $userAgent = null): PostbackExecution
  {
    $postback = Postback::query()->where('uuid', $uuid)->active()->firstOrFail();

    if (empty($postback->result_url)) {
      throw new \InvalidArgumentException('Postback has no result URL configured.');
    }

    $outboundUrl = $postback->buildOutboundUrl($inboundParams);

    $idempotencyKey = PostbackExecution::generateIdempotencyKey($postback->id, $inboundParams);
    $existing = PostbackExecution::query()->where('idempotency_key', $idempotencyKey)->first();

    if ($existing) {
      return $existing;
    }

    $execution = PostbackExecution::create([
      'postback_id' => $postback->id,
      'status' => ExecutionStatus::PENDING,
      'inbound_params' => $inboundParams,
      'resolved_tokens' => $inboundParams,
      'outbound_url' => $outboundUrl,
      'ip_address' => $ipAddress,
      'user_agent' => $userAgent ? mb_substr($userAgent, 0, 500) : null,
      'idempotency_key' => $idempotencyKey,
    ]);

    $postback->increment('total_executions');
    $postback->update(['last_fired_at' => now()]);

    Log::info('Postback inbound received', [
      'postback_id' => $postback->id,
      'execution_id' => $execution->id,
      'fire_mode' => $postback->fire_mode->value,
      'inbound_params' => $inboundParams,
    ]);

    if ($postback->fire_mode === FireMode::REALTIME) {
      $this->processExecution($execution);
    } else {
      DispatchPostbackJob::dispatch($execution);
    }

    return $execution->fresh();
  }

  /**
   * Procesa una ejecución individual (dispatch outbound HTTP).
   */
  public function processExecution(PostbackExecution $execution): void
  {
    if (!in_array($execution->status, [ExecutionStatus::PENDING, ExecutionStatus::FAILED])) {
      return;
    }

    $this->dispatchService->dispatch($execution);
  }

  /**
   * Procesa ejecuciones retryable en batch (llamado por scheduler).
   *
   * @return int Número de ejecuciones procesadas
   */
  public function processRetryableExecutions(): int
  {
    $executions = PostbackExecution::query()->retryable()->orderBy('next_retry_at')->limit(50)->get();

    foreach ($executions as $execution) {
      $execution->update(['status' => ExecutionStatus::PENDING]);
      DispatchPostbackJob::dispatch($execution);
    }

    return $executions->count();
  }
}
