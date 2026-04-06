<?php

namespace App\Services\PingPost;

use App\Enums\PostResultStatus;
use App\Jobs\PingPost\RetryPostJob;
use App\Models\BuyerConfig;
use App\Models\Integration;
use App\Models\LeadDispatch;
use App\Models\PingResult;
use App\Models\PostResult;
use App\Models\PostResponseConfig;
use App\Services\PayloadProcessorService;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Throwable;

class PostService
{
  public function __construct(private readonly PayloadProcessorService $payloadProcessor) {}

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

    // Async postback: create a pending record immediately
    if ($config->price_source->isAsync()) {
      return $this->createRecord($dispatch, $integration, $pingResult, [
        'status' => PostResultStatus::PENDING_POSTBACK,
        'price_offered' => $offeredPrice,
        'request_url' => $requestUrl,
        'request_payload' => $payload,
        'request_headers' => $headers,
        'postback_expires_at' => now()->addDays($config->postback_pending_days),
      ]);
    }

    $startMs = microtime(true);

    try {
      /** @var Response $response */
      $response = Http::withHeaders($headers)
        ->timeout($config->post_timeout_ms / 1000)
        ->{$method}($requestUrl, $payload);
      $durationMs = (int) round((microtime(true) - $startMs) * 1000);

      $configUrl = $postEnv->response_config;
      $accepted = $this->isAccepted($response, $configUrl);
      $rejectionReason = $this->extractRejectionReason($response, $configUrl);

      $status = $accepted ? PostResultStatus::ACCEPTED : PostResultStatus::REJECTED;

      return $this->createRecord($dispatch, $integration, $pingResult, [
        'status' => $status,
        'price_offered' => $offeredPrice,
        'price_final' => $accepted ? $offeredPrice : null,
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

      // Queue retry for network/server errors
      if (!$isTimeout) {
        RetryPostJob::dispatch($postResult->id)->delay(3);
      }

      return $postResult;
    }
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
