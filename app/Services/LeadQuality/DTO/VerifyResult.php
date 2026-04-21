<?php

namespace App\Services\LeadQuality\DTO;

class VerifyResult
{
  /**
   * @param  array<string, mixed>  $raw
   */
  public function __construct(public readonly bool $verified, public readonly ?string $error = null, public readonly array $raw = []) {}

  public static function success(array $raw = []): self
  {
    return new self(verified: true, raw: $raw);
  }

  public static function failure(string $error, array $raw = []): self
  {
    return new self(verified: false, error: $error, raw: $raw);
  }
}
