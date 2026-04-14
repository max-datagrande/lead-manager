<?php

namespace App\Services\PingPost;

use App\Enums\PingResultStatus;
use App\Jobs\PingPost\SendWorkflowAlertJob;
use App\Models\BuyerConfig;
use App\Models\Integration;
use App\Models\IntegrationEnvironment;
use App\Models\LeadDispatch;
use App\Models\PingResult;
use App\Models\PingResponseConfig;
use App\Services\PayloadProcessorService;
use App\Support\HttpResponseInspector;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Throwable;

class PingService
{
  private ?DispatchTimelineService $timeline = null;

  public function __construct(private readonly PayloadProcessorService $payloadProcessor) {}

  public function setTimeline(DispatchTimelineService $timeline): void
  {
    $this->timeline = $timeline;
  }

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

      $baseData = [
        'lead_dispatch_id' => $dispatch->id,
        'integration_id' => $integration->id,
        'idempotency_key' => $idempotencyKey,
        'http_status_code' => $response->status(),
        'request_url' => $requestUrl,
        'request_payload' => $payload,
        'request_headers' => $headers,
        'response_body' => $response->json() ?? ['raw' => $response->body()],
        'duration_ms' => $durationMs,
      ];

      // 1. HTTP-level error (5xx, invalid JSON)
      $errorCheck = HttpResponseInspector::detectError($response);
      if ($errorCheck['is_error']) {
        return $this->handleErrorResult($dispatch, $integration, $baseData, $errorCheck['reason']);
      }

      // 2. Configured error path match
      $responseConfig = $pingEnv->response_config;
      $configError = HttpResponseInspector::detectConfiguredError(
        $response->json() ?? [],
        $responseConfig?->error_path,
        $responseConfig?->error_value,
        $responseConfig?->error_reason_path,
      );
      if ($configError['is_error']) {
        return $this->handleErrorResult($dispatch, $integration, $baseData, $configError['reason']);
      }

      // 3. Normal accepted/rejected evaluation
      $bidPrice = $this->extractBidPrice($response, $responseConfig);
      $accepted = $this->isAccepted($response, $responseConfig);

      return PingResult::create(
        array_merge($baseData, [
          'status' => $accepted ? PingResultStatus::ACCEPTED : PingResultStatus::REJECTED,
          'bid_price' => $bidPrice,
        ]),
      );
    } catch (Throwable $e) {
      $durationMs = (int) round((microtime(true) - $startMs) * 1000);
      $isTimeout = str_contains($e->getMessage(), 'timed out') || str_contains($e->getMessage(), 'timeout');

      $pingResult = PingResult::create([
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

      $this->timeline?->log(
        $isTimeout ? DispatchTimelineService::PING_RESULT : DispatchTimelineService::PING_ERROR,
        "Buyer '{$integration->name}': " . ($isTimeout ? 'timeout' : "exception — {$e->getMessage()}"),
        ['integration_id' => $integration->id, 'ping_result_id' => $pingResult->id],
      );

      if (!$isTimeout) {
        SendWorkflowAlertJob::dispatch($dispatch->workflow_id, "Buyer '{$integration->name}' ping failed: {$e->getMessage()}", [
          'title' => 'Buyer Ping Failed',
          'fields' => [
            'Buyer' => "{$integration->name} (#{$integration->id})",
            'Dispatch' => "#{$dispatch->id} — {$dispatch->dispatch_uuid}",
            'Error' => $e->getMessage(),
          ],
        ]);
      }

      return $pingResult;
    }
  }

  /**
   * Handle a detected error: create ERROR result, log to timeline, dispatch alert.
   */
  private function handleErrorResult(LeadDispatch $dispatch, Integration $integration, array $baseData, string $reason): PingResult
  {
    $pingResult = PingResult::create(
      array_merge($baseData, [
        'status' => PingResultStatus::ERROR,
        'bid_price' => null,
      ]),
    );

    $this->timeline?->log(DispatchTimelineService::PING_ERROR, "Buyer '{$integration->name}': {$reason}", [
      'integration_id' => $integration->id,
      'http_status' => $baseData['http_status_code'],
      'ping_result_id' => $pingResult->id,
    ]);

    SendWorkflowAlertJob::dispatch($dispatch->workflow_id, "Buyer '{$integration->name}' ping error: {$reason}", [
      'title' => 'Buyer Ping Error',
      'fields' => [
        'Buyer' => "{$integration->name} (#{$integration->id})",
        'Dispatch' => "#{$dispatch->id} — {$dispatch->dispatch_uuid}",
        'HTTP Status' => (string) $baseData['http_status_code'],
        'Error' => $reason,
      ],
    ]);

    return $pingResult;
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
