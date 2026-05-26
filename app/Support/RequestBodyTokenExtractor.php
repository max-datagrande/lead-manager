<?php

namespace App\Support;

class RequestBodyTokenExtractor
{
  /**
   * Extract the unique set of field IDs referenced as {$<id>} tokens in a request body.
   *
   * Operates on the raw text — does not require valid JSON. Returns a sorted array of ints.
   *
   * @return array<int, int>
   */
  public static function extractFieldIds(?string $body): array
  {
    if ($body === null || $body === '') {
      return [];
    }

    preg_match_all('/\{\$(\d+)\}/', $body, $matches);

    if (empty($matches[1])) {
      return [];
    }

    $ids = array_values(array_unique(array_map('intval', $matches[1])));
    sort($ids);

    return $ids;
  }
}
