<?php

namespace App\Services\ExternalServiceRequest;

use App\Models\ExternalServiceRequest;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Throwable;

/**
 * Generic recorder for external HTTP calls. Not tied to any module.
 *
 * Any module that makes outbound HTTP calls can wrap them with `record()` and
 * a single row will be persisted to `external_service_requests` regardless of
 * success, failure, timeout, or exception. The caller provides metadata
 * (module, service_name, service_id, operation, loggable) to discriminate.
 */
class ExternalRequestRecorder
{
  /**
   * Execute the given HTTP call closure and persist the full request/response cycle.
   *
   * The closure receives no arguments and must return an `Illuminate\Http\Client\Response`.
   * Exceptions thrown inside the closure are persisted as status `exception` or `timeout`
   * and then re-thrown unchanged so the caller can handle them.
   *
   * @param  array{
   *   module: string,
   *   service_name?: string|null,
   *   service_id?: int|null,
   *   operation?: string|null,
   *   loggable?: Model|null,
   *   request_method?: string,
   *   request_url?: string,
   *   request_headers?: array<string, mixed>|null,
   *   request_body?: array<string, mixed>|null,
   * }  $meta
   */
  public function record(Closure $callback, array $meta): Response
  {
    $requestedAt = now();
    $startNs = hrtime(true);

    try {
      /** @var Response $response */
      $response = $callback();
    } catch (ConnectionException $e) {
      $this->persist($meta, $requestedAt, null, $startNs, 'timeout', $e->getMessage());
      throw $e;
    } catch (Throwable $e) {
      $this->persist($meta, $requestedAt, null, $startNs, 'exception', $e->getMessage());
      throw $e;
    }

    $status = $response->successful() ? 'success' : 'failed';
    $error = $response->successful() ? null : "HTTP {$response->status()}";
    $this->persist($meta, $requestedAt, $response, $startNs, $status, $error);

    return $response;
  }

  /**
   * Persist a one-shot log row without executing a callable. Useful when the caller
   * already has a response object or needs to record a synthetic entry.
   *
   * @param  array<string, mixed>  $meta
   */
  public function log(array $meta): ExternalServiceRequest
  {
    $now = now();
    [$loggableType, $loggableId] = $this->loggablePair($meta);

    return ExternalServiceRequest::create([
      'loggable_type' => $loggableType,
      'loggable_id' => $loggableId,
      'module' => $meta['module'] ?? 'unknown',
      'service_name' => $meta['service_name'] ?? null,
      'service_id' => $meta['service_id'] ?? null,
      'operation' => $meta['operation'] ?? null,
      'request_method' => $meta['request_method'] ?? 'POST',
      'request_url' => $meta['request_url'] ?? '',
      'request_headers' => $meta['request_headers'] ?? null,
      'request_body' => $meta['request_body'] ?? null,
      'response_status_code' => $meta['response_status_code'] ?? null,
      'response_headers' => $meta['response_headers'] ?? null,
      'response_body' => $meta['response_body'] ?? null,
      'status' => $meta['status'] ?? 'success',
      'error_message' => $meta['error_message'] ?? null,
      'duration_ms' => $meta['duration_ms'] ?? null,
      'requested_at' => $meta['requested_at'] ?? $now,
      'responded_at' => $meta['responded_at'] ?? $now,
    ]);
  }

  /**
   * @param  array<string, mixed>  $meta
   */
  private function persist(array $meta, \Carbon\Carbon $requestedAt, ?Response $response, int $startNs, string $status, ?string $error): void
  {
    $durationMs = (int) round((hrtime(true) - $startNs) / 1_000_000);
    [$loggableType, $loggableId] = $this->loggablePair($meta);

    ExternalServiceRequest::create([
      'loggable_type' => $loggableType,
      'loggable_id' => $loggableId,
      'module' => $meta['module'] ?? 'unknown',
      'service_name' => $meta['service_name'] ?? null,
      'service_id' => $meta['service_id'] ?? null,
      'operation' => $meta['operation'] ?? null,
      'request_method' => strtoupper($meta['request_method'] ?? 'POST'),
      'request_url' => $meta['request_url'] ?? '',
      'request_headers' => $meta['request_headers'] ?? null,
      'request_body' => $meta['request_body'] ?? null,
      'response_status_code' => $response?->status(),
      'response_headers' => $response ? $this->flattenHeaders($response->headers()) : null,
      'response_body' => $response ? $this->safeJsonBody($response) : null,
      'status' => $status,
      'error_message' => $error,
      'duration_ms' => $durationMs,
      'requested_at' => $requestedAt,
      'responded_at' => now(),
    ]);
  }

  /**
   * Extracts (loggable_type, loggable_id) from the meta bag, honoring the morph contract.
   *
   * @param  array<string, mixed>  $meta
   * @return array{0: ?string, 1: ?int}
   */
  private function loggablePair(array $meta): array
  {
    $loggable = $meta['loggable'] ?? null;
    if (!$loggable instanceof Model) {
      return [null, null];
    }

    $type = get_class($loggable);
    $id = $loggable->getKey();

    return [$type, is_int($id) ? $id : (int) $id];
  }

  /**
   * @param  array<string, array<int, string>>  $headers
   * @return array<string, string>
   */
  private function flattenHeaders(array $headers): array
  {
    $flat = [];
    foreach ($headers as $key => $values) {
      $flat[$key] = is_array($values) ? implode(', ', $values) : (string) $values;
    }

    return $flat;
  }

  /**
   * @return array<string, mixed>|array{raw: string}
   */
  private function safeJsonBody(Response $response): array
  {
    $json = $response->json();

    return is_array($json) ? $json : ['raw' => $response->body()];
  }
}
