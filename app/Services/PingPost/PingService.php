<?php

namespace App\Services\PingPost;

use App\Enums\PingResultStatus;
use App\Models\BuyerConfig;
use App\Models\Integration;
use App\Models\IntegrationEnvironment;
use App\Models\LeadDispatch;
use App\Models\PingResult;
use App\Models\PingResponseConfig;
use App\Services\PayloadProcessorService;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Throwable;

class PingService
{
  public function __construct(private readonly PayloadProcessorService $payloadProcessor) {}

  /**
   * Execute a ping to a buyer and return the recorded PingResult.
   */
  public function ping(Integration $integration, BuyerConfig $config, LeadDispatch $dispatch, array $leadData): PingResult
  {
    $idempotencyKey = LeadDispatch::generateIdempotencyKey($dispatch->id, $integration->id, $dispatch->fingerprint);

    $pingEnv = $integration->environments->where('env_type', 'ping')->where('environment', 'production')->first();

    if (!$pingEnv || !$pingEnv->url) {
      return $this->createSkippedResult($dispatch, $integration, $idempotencyKey, 'No ping URL configured');
    }

    $replacements = $this->payloadProcessor->buildReplacements($integration, $pingEnv, $leadData);
    $requestUrl = $this->payloadProcessor->applyReplacements($pingEnv->url ?? '', $replacements);
    $payload = json_decode($this->payloadProcessor->applyReplacements($pingEnv->request_body ?? '{}', $replacements), true) ?? [];
    $payload = $this->payloadProcessor->applyTwigTransformer($integration, $payload);
    $headers = json_decode($this->payloadProcessor->applyReplacements($pingEnv->request_headers ?? '{}', $replacements), true) ?? [];
    $method = strtolower($pingEnv->method ?? 'post');

    $startMs = microtime(true);

    try {
      /** @var Response $response */
      $response = Http::withHeaders($headers)
        ->timeout($config->ping_timeout_ms / 1000)
        ->{$method}($requestUrl, $payload);
      $durationMs = (int) round((microtime(true) - $startMs) * 1000);

      $config = $pingEnv->response_config;
      $bidPrice = $this->extractBidPrice($response, $config);
      $accepted = $this->isAccepted($response, $config);
      $status = $accepted ? PingResultStatus::ACCEPTED : PingResultStatus::REJECTED;

      return PingResult::create([
        'lead_dispatch_id' => $dispatch->id,
        'integration_id' => $integration->id,
        'idempotency_key' => $idempotencyKey,
        'status' => $status,
        'bid_price' => $bidPrice,
        'http_status_code' => $response->status(),
        'request_url' => $requestUrl,
        'request_payload' => $payload,
        'request_headers' => $headers,
        'response_body' => $response->json() ?? ['raw' => $response->body()],
        'duration_ms' => $durationMs,
      ]);
    } catch (Throwable $e) {
      $durationMs = (int) round((microtime(true) - $startMs) * 1000);
      $isTimeout = str_contains($e->getMessage(), 'timed out') || str_contains($e->getMessage(), 'timeout');

      return PingResult::create([
        'lead_dispatch_id' => $dispatch->id,
        'integration_id' => $integration->id,
        'idempotency_key' => $idempotencyKey,
        'status' => $isTimeout ? PingResultStatus::TIMEOUT : PingResultStatus::ERROR,
        'bid_price' => null,
        'http_status_code' => null,
        'request_url' => $requestUrl,
        'request_payload' => $payload,
        'request_headers' => $headers,
        'response_body' => ['error' => $e->getMessage()],
        'duration_ms' => $durationMs,
      ]);
    }
  }

  private function extractBidPrice(Response $response, ?PingResponseConfig $config): ?float
  {
    $path = $config?->bid_price_path;

    if (!$path) {
      return null;
    }

    $value = Arr::get($response->json() ?? [], $path);

    return is_numeric($value) ? (float) $value : null;
  }

  private function isAccepted(Response $response, ?PingResponseConfig $config): bool
  {
    $acceptedPath = $config?->accepted_path;
    $acceptedValue = $config?->accepted_value;

    if (!$acceptedPath) {
      return $response->successful();
    }

    $actual = Arr::get($response->json() ?? [], $acceptedPath);

    return (string) $actual === (string) $acceptedValue;
  }

  private function createSkippedResult(LeadDispatch $dispatch, Integration $integration, string $idempotencyKey, string $reason): PingResult
  {
    return PingResult::create([
      'lead_dispatch_id' => $dispatch->id,
      'integration_id' => $integration->id,
      'idempotency_key' => $idempotencyKey,
      'status' => PingResultStatus::SKIPPED,
      'skip_reason' => $reason,
    ]);
  }
}
