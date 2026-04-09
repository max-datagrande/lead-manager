<?php

namespace App\Services;

use App\Enums\ExecutionStatus;
use App\Enums\FireMode;
use App\Enums\PostbackSource;
use App\Jobs\DispatchPostbackJob;
use App\Models\Postback;
use App\Models\PostbackExecution;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Maxidev\Logger\TailLogger;

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

    $source = PostbackSource::EXTERNAL_API;
    $idempotencyKey = PostbackExecution::generateIdempotencyKey($postback->id, $inboundParams, $source->value);
    $existing = PostbackExecution::query()->where('idempotency_key', $idempotencyKey)->first();

    if ($existing) {
      return $existing;
    }

    $execution = PostbackExecution::create([
      'postback_id' => $postback->id,
      'source' => $source,
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

    TailLogger::saveLog('Postback inbound received', 'postback/fire', 'info', [
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
   * Dispara un postback interno desde código (offerwall, ping-post, manual, etc.).
   *
   * @param  array<string, string>  $params
   *
   * @throws ModelNotFoundException
   */
  public function fireInternal(string $uuid, array $params, PostbackSource $source, ?string $sourceReference = null): PostbackExecution
  {
    $postback = Postback::query()->where('uuid', $uuid)->active()->firstOrFail();

    if (empty($postback->result_url)) {
      throw new \InvalidArgumentException('Postback has no result URL configured.');
    }

    $outboundUrl = $postback->buildOutboundUrl($params);

    $idempotencyKey = PostbackExecution::generateIdempotencyKey($postback->id, $params, $source->value);
    $existing = PostbackExecution::query()->where('idempotency_key', $idempotencyKey)->first();

    if ($existing) {
      return $existing;
    }

    $execution = PostbackExecution::create([
      'postback_id' => $postback->id,
      'source' => $source,
      'source_reference' => $sourceReference,
      'status' => ExecutionStatus::PENDING,
      'inbound_params' => $params,
      'resolved_tokens' => $params,
      'outbound_url' => $outboundUrl,
      'idempotency_key' => $idempotencyKey,
    ]);

    $postback->increment('total_executions');
    $postback->update(['last_fired_at' => now()]);

    TailLogger::saveLog('Internal postback fired', 'postback/internal', 'info', [
      'postback_id' => $postback->id,
      'execution_id' => $execution->id,
      'source' => $source->value,
      'source_reference' => $sourceReference,
    ]);

    DispatchPostbackJob::dispatch($execution);

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
