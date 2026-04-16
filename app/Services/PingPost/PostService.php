<?php

namespace App\Services\PingPost;

use App\Enums\PostResultStatus;
use App\Jobs\PingPost\RetryPostJob;
use App\Jobs\PingPost\SendWorkflowAlertJob;
use App\Models\BuyerConfig;
use App\Models\Integration;
use App\Models\LeadDispatch;
use App\Models\PingResult;
use App\Models\PostResult;
use App\Models\PostResponseConfig;
use App\Services\PayloadProcessorService;
use App\Support\HttpResponseInspector;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Throwable;

class PostService
{
  private ?DispatchTimelineService $timeline = null;

  public function __construct(private readonly PayloadProcessorService $payloadProcessor) {}

  public function setTimeline(DispatchTimelineService $timeline): void
  {
    $this->timeline = $timeline;
  }

  /**
   * Execute a post to a buyer and return the recorded PostResult.
   */
  public function post(
    Integration $integration,
    BuyerConfig $config,
    LeadDispatch $dispatch,
    array $leadData,
    ?PingResult $pingResult,
    float $offeredPrice,
  ): PostResult {
    $postEnv = $integration->environments->where('env_type', 'post')->where('environment', 'production')->first();

    if (!$postEnv || !$postEnv->url) {
      return $this->createRecord($dispatch, $integration, $pingResult, [
        'status' => PostResultStatus::SKIPPED,
        'price_offered' => $offeredPrice,
      ]);
    }

    // Enrich leadData with the buyer's external lead_id from the ping response.
    // lead_id_path lives in the ping environment's response_config.
    if ($pingResult?->response_body) {
      $pingEnv = $integration->environments->where('env_type', 'ping')->where('environment', 'production')->first();
      $leadIdPath = $pingEnv?->response_config?->lead_id_path;
      if ($leadIdPath) {
        $leadData['ping_lead_id'] = Arr::get($pingResult->response_body, $leadIdPath, '');
      }
    }

    $replacements = $this->payloadProcessor->buildReplacements($integration, $postEnv, $leadData);
    $requestUrl = $this->payloadProcessor->applyReplacements($postEnv->url ?? '', $replacements);
    $payload = json_decode($this->payloadProcessor->applyReplacements($postEnv->request_body ?? '{}', $replacements), true) ?? [];
    $payload = $this->payloadProcessor->applyTwigTransformer($integration, $payload);
    $headers = json_decode($this->payloadProcessor->applyReplacements($postEnv->request_headers ?? '{}', $replacements), true) ?? [];
    $method = strtolower($postEnv->method ?? 'post');

    $isAsyncPricing = $config->price_source->isAsync();

    $startMs = microtime(true);

    try {
      /** @var Response $response */
      $response = Http::withHeaders($headers)
        ->timeout($config->post_timeout_ms / 1000)
        ->{$method}($requestUrl, $payload);
      $durationMs = (int) round((microtime(true) - $startMs) * 1000);

      $baseData = [
        'status' => PostResultStatus::ERROR,
        'price_offered' => $offeredPrice,
        'http_status_code' => $response->status(),
        'request_url' => $requestUrl,
        'request_payload' => $payload,
        'request_headers' => $headers,
        'response_body' => $response->json() ?? ['raw' => $response->body()],
        'duration_ms' => $durationMs,
      ];

      // 1. HTTP-level error (5xx, invalid JSON) — transient, retry
      $errorCheck = HttpResponseInspector::detectError($response);
      if ($errorCheck['is_error']) {
        return $this->handleErrorResult($dispatch, $integration, $pingResult, $baseData, $errorCheck['reason'], retry: true);
      }

      // 2. Configured error path match — deterministic, no retry
      $responseConfig = $postEnv->response_config;
      $configError = HttpResponseInspector::detectConfiguredError(
        $response->json() ?? [],
        $responseConfig?->error_path,
        $responseConfig?->error_value,
        $responseConfig?->error_reason_path,
      );
      if ($configError['is_error']) {
        $reason = $configError['reason'];
        $excludes = $responseConfig?->error_excludes;
        $isExcluded = HttpResponseInspector::isExcludedError($reason, $excludes);

        // Excluded (expected) error → treat as rejection, no alert, no retry
        if ($isExcluded) {
          $postResult = $this->createRecord(
            $dispatch,
            $integration,
            $pingResult,
            array_merge($baseData, [
              'status' => PostResultStatus::REJECTED,
              'rejection_reason' => $reason,
            ]),
          );

          $this->timeline?->log(DispatchTimelineService::POST_RESULT, "Buyer '{$integration->name}': excluded error — {$reason}", [
            'integration_id' => $integration->id,
            'post_result_id' => $postResult->id,
            'excluded_error' => true,
          ]);

          return $postResult;
        }

        return $this->handleErrorResult($dispatch, $integration, $pingResult, $baseData, $reason, retry: false);
      }

      // 3. Normal accepted/rejected evaluation
      $accepted = $this->isAccepted($response, $responseConfig);
      $rejectionReason = $this->extractRejectionReason($response, $responseConfig);

      // Async pricing: buyer accepted the lead but price comes later via postback
      if ($accepted && $isAsyncPricing) {
        return $this->createRecord($dispatch, $integration, $pingResult, [
          'status' => PostResultStatus::PENDING_POSTBACK,
          'price_offered' => $offeredPrice,
          'http_status_code' => $response->status(),
          'request_url' => $requestUrl,
          'request_payload' => $payload,
          'request_headers' => $headers,
          'response_body' => $response->json() ?? ['raw' => $response->body()],
          'duration_ms' => $durationMs,
          'postback_expires_at' => now()->addDays($config->postback_pending_days),
        ]);
      }

      $priceFinal = $accepted ? $offeredPrice : null;

      // Extract bid price from POST response when bid_price_path is configured
      if ($accepted && $responseConfig?->bid_price_path) {
        $extractedPrice = $this->extractBidFromResponse($response, $responseConfig);
        if ($extractedPrice !== null) {
          $priceFinal = $extractedPrice;
          $this->timeline?->log(
            DispatchTimelineService::PRICE_EXTRACTED_FROM_POST,
            "Extracted price \${$extractedPrice} from post response for '{$integration->name}'",
            ['integration_id' => $integration->id, 'extracted_price' => $extractedPrice],
          );
        }
      }

      return $this->createRecord($dispatch, $integration, $pingResult, [
        'status' => $accepted ? PostResultStatus::ACCEPTED : PostResultStatus::REJECTED,
        'price_offered' => $offeredPrice,
        'price_final' => $priceFinal,
        'http_status_code' => $response->status(),
        'request_url' => $requestUrl,
        'request_payload' => $payload,
        'request_headers' => $headers,
        'response_body' => $response->json() ?? ['raw' => $response->body()],
        'duration_ms' => $durationMs,
        'rejection_reason' => $rejectionReason,
      ]);
    } catch (Throwable $e) {
      $durationMs = (int) round((microtime(true) - $startMs) * 1000);
      $isTimeout = str_contains($e->getMessage(), 'timed out') || str_contains($e->getMessage(), 'timeout');
      $status = $isTimeout ? PostResultStatus::TIMEOUT : PostResultStatus::ERROR;

      $postResult = $this->createRecord($dispatch, $integration, $pingResult, [
        'status' => $status,
        'price_offered' => $offeredPrice,
        'http_status_code' => null,
        'request_url' => $requestUrl,
        'request_payload' => $payload,
        'request_headers' => $headers,
        'response_body' => ['error' => $e->getMessage()],
        'duration_ms' => $durationMs,
      ]);

      $this->timeline?->log(
        $isTimeout ? DispatchTimelineService::POST_RESULT : DispatchTimelineService::POST_ERROR,
        "Buyer '{$integration->name}': " . ($isTimeout ? 'timeout' : "exception — {$e->getMessage()}"),
        ['integration_id' => $integration->id, 'post_result_id' => $postResult->id],
      );

      if (!$isTimeout) {
        RetryPostJob::dispatch($postResult->id)->delay(3);
        SendWorkflowAlertJob::dispatch($dispatch->workflow_id, "Buyer '{$integration->name}' post failed: {$e->getMessage()}", [
          'title' => 'Buyer Post Failed',
          'fields' => [
            'Buyer' => "{$integration->name} (#{$integration->id})",
            'Dispatch' => "#{$dispatch->id} — {$dispatch->dispatch_uuid}",
            'Error' => $e->getMessage(),
          ],
        ]);
      }

      return $postResult;
    }
  }

  /**
   * Handle a detected error: create ERROR result, log to timeline, dispatch alert.
   * Only queues retry for transient errors (HTTP 5xx, network), not for configured error path matches.
   */
  private function handleErrorResult(
    LeadDispatch $dispatch,
    Integration $integration,
    ?PingResult $pingResult,
    array $baseData,
    string $reason,
    bool $retry = false,
  ): PostResult {
    $postResult = $this->createRecord($dispatch, $integration, $pingResult, $baseData);

    $this->timeline?->log(DispatchTimelineService::POST_ERROR, "Buyer '{$integration->name}': {$reason}", [
      'integration_id' => $integration->id,
      'http_status' => $baseData['http_status_code'],
      'post_result_id' => $postResult->id,
    ]);

    SendWorkflowAlertJob::dispatch($dispatch->workflow_id, "Buyer '{$integration->name}' post error: {$reason}", [
      'title' => 'Buyer Post Error',
      'fields' => [
        'Buyer' => "{$integration->name} (#{$integration->id})",
        'Dispatch' => "#{$dispatch->id} — {$dispatch->dispatch_uuid}",
        'HTTP Status' => (string) $baseData['http_status_code'],
        'Error' => $reason,
      ],
    ]);

    if ($retry) {
      RetryPostJob::dispatch($postResult->id)->delay(3);
    }

    return $postResult;
  }

  private function isAccepted(Response $response, ?PostResponseConfig $config): bool
  {
    $acceptedPath = $config?->accepted_path;
    $acceptedValue = $config?->accepted_value;

    if (!$acceptedPath) {
      return $response->successful();
    }

    $actual = Arr::get($response->json() ?? [], $acceptedPath);

    return (string) $actual === (string) $acceptedValue;
  }

  private function extractBidFromResponse(Response $response, ?PostResponseConfig $config): ?float
  {
    $path = $config?->bid_price_path;

    if (!$path) {
      return null;
    }

    $value = Arr::get($response->json() ?? [], $path);

    return is_numeric($value) ? (float) $value : null;
  }

  private function extractRejectionReason(Response $response, ?PostResponseConfig $config): ?string
  {
    $rejectedPath = $config?->rejected_path;

    if (!$rejectedPath) {
      return null;
    }

    $value = Arr::get($response->json() ?? [], $rejectedPath);

    return $value ? (string) $value : null;
  }

  /**
   * @param  array<string, mixed>  $data
   */
  private function createRecord(LeadDispatch $dispatch, Integration $integration, ?PingResult $pingResult, array $data): PostResult
  {
    return PostResult::create(
      array_merge(
        [
          'lead_dispatch_id' => $dispatch->id,
          'ping_result_id' => $pingResult?->id,
          'integration_id' => $integration->id,
        ],
        $data,
      ),
    );
  }
}
