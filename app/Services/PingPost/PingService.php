<?php

namespace App\Services\PingPost;

use App\Enums\PingResultStatus;
use App\Models\BuyerConfig;
use App\Models\Integration;
use App\Models\IntegrationEnvironment;
use App\Models\LeadDispatch;
use App\Models\PingResult;
use App\Services\PayloadProcessorService;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Throwable;

class PingService
{
  public function __construct(
    private readonly PayloadProcessorService $payloadProcessor,
  ) {}

  /**
   * Execute a ping to a buyer and return the recorded PingResult.
   */
  public function ping(
    Integration $integration,
    BuyerConfig $config,
    LeadDispatch $dispatch,
    array $leadData,
  ): PingResult {
    $idempotencyKey = LeadDispatch::generateIdempotencyKey(
      $dispatch->id,
      $integration->id,
      $dispatch->fingerprint,
    );

    $pingEnv = $integration->environments
      ->where('env_type', 'ping')
      ->where('environment', 'production')
      ->first();

    if (! $pingEnv || ! $pingEnv->url) {
      return $this->createSkippedResult($dispatch, $integration, $idempotencyKey, 'No ping URL configured');
    }

    $mappingConfig = $integration->request_mapping_config ?? [];
    $replacements = PayloadProcessorService::generateReplacements($leadData, $mappingConfig);
    $finals = $replacements['finalReplacements'] ?? [];

    $requestUrl = $this->payloadProcessor->processUrl($pingEnv->url, $finals);
    $payloadString = $this->payloadProcessor->process($pingEnv->request_body ?? '{}', $finals);
    $payload = json_decode($payloadString, true) ?? [];
    $headersRaw = $pingEnv->request_headers ?? '{}';
    $headersString = $this->payloadProcessor->process($headersRaw, $finals);
    $headers = json_decode($headersString, true) ?? [];
    $method = strtolower($pingEnv->method ?? 'post');

    $startMs = microtime(true);

    try {
      /** @var Response $response */
      $response = Http::withHeaders($headers)->timeout($config->ping_timeout_ms / 1000)->{$method}($requestUrl, $payload);
      $durationMs = (int) round((microtime(true) - $startMs) * 1000);

      $responseConfig = $pingEnv->response_config ?? [];
      $bidPrice = $this->extractBidPrice($response, $responseConfig);
      $accepted = $this->isAccepted($response, $responseConfig);
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

  private function extractBidPrice(Response $response, array $responseConfig): ?float
  {
    $path = Arr::get($responseConfig, 'bid_price_path');

    if (! $path) {
      return null;
    }

    $value = Arr::get($response->json() ?? [], $path);

    return is_numeric($value) ? (float) $value : null;
  }

  private function isAccepted(Response $response, array $responseConfig): bool
  {
    $acceptedPath = Arr::get($responseConfig, 'accepted_path');
    $acceptedValue = Arr::get($responseConfig, 'accepted_value');

    if (! $acceptedPath) {
      return $response->successful();
    }

    $actual = Arr::get($response->json() ?? [], $acceptedPath);

    return (string) $actual === (string) $acceptedValue;
  }

  private function createSkippedResult(
    LeadDispatch $dispatch,
    Integration $integration,
    string $idempotencyKey,
    string $reason,
  ): PingResult {
    return PingResult::create([
      'lead_dispatch_id' => $dispatch->id,
      'integration_id' => $integration->id,
      'idempotency_key' => $idempotencyKey,
      'status' => PingResultStatus::SKIPPED,
      'skip_reason' => $reason,
    ]);
  }
}
