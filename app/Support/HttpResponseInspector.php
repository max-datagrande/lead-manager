<?php

namespace App\Support;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Arr;

class HttpResponseInspector
{
  /**
   * Inspect an HTTP response for real errors (server failures, invalid payloads).
   *
   * @return array{is_error: bool, reason: ?string}
   */
  public static function detectError(Response $response): array
  {
    if ($response->serverError()) {
      return [
        'is_error' => true,
        'reason' => "HTTP {$response->status()} server error",
      ];
    }

    if ($response->body() !== '' && $response->json() === null) {
      $snippet = substr($response->body(), 0, 120);
      return [
        'is_error' => true,
        'reason' => "Invalid JSON response: {$snippet}",
      ];
    }

    return ['is_error' => false, 'reason' => null];
  }

  /**
   * Detect errors using configured JSON paths from response config.
   *
   * Mode match: error_path + error_value → exact match triggers error.
   * Mode exists: error_path only → any truthy value triggers error.
   *
   * @return array{is_error: bool, reason: ?string}
   */
  public static function detectConfiguredError(array $json, ?string $errorPath, ?string $errorValue = null, ?string $errorReasonPath = null): array
  {
    if (!$errorPath) {
      return ['is_error' => false, 'reason' => null];
    }

    $actual = Arr::get($json, $errorPath);

    $isError = $errorValue !== null ? (string) $actual === (string) $errorValue : !empty($actual);

    if (!$isError) {
      return ['is_error' => false, 'reason' => null];
    }

    $reason = null;
    if ($errorReasonPath) {
      foreach (explode('|', $errorReasonPath) as $path) {
        $value = Arr::get($json, trim($path));
        if ($value) {
          $reason = (string) $value;
          break;
        }
      }
    } elseif ($errorValue === null) {
      $reason = is_string($actual) ? $actual : null;
    }

    return [
      'is_error' => true,
      'reason' => $reason ?: "Error detected at {$errorPath}",
    ];
  }
}
