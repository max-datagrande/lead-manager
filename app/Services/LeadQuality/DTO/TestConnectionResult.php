<?php

namespace App\Services\LeadQuality\DTO;

class TestConnectionResult
{
  /**
   * @param  array<string, mixed>  $raw
   */
  public function __construct(
    public readonly bool $ok,
    public readonly ?string $error = null,
    public readonly ?string $message = null,
    public readonly array $raw = [],
  ) {}

  public static function success(?string $message = null, array $raw = []): self
  {
    return new self(ok: true, message: $message, raw: $raw);
  }

  public static function failure(string $error, array $raw = []): self
  {
    return new self(ok: false, error: $error, raw: $raw);
  }
}
