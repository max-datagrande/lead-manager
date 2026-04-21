<?php

namespace App\Services\LeadQuality\DTO;

class ChallengeResult
{
  /**
   * @param  array<string, mixed>  $raw  Raw provider response for trace/debug.
   */
  public function __construct(
    public readonly bool $sent,
    public readonly ?string $reference = null,
    public readonly ?string $error = null,
    public readonly ?string $maskedDestination = null,
    public readonly array $raw = [],
  ) {}

  public static function success(string $reference, ?string $maskedDestination = null, array $raw = []): self
  {
    return new self(sent: true, reference: $reference, maskedDestination: $maskedDestination, raw: $raw);
  }

  public static function failure(string $error, array $raw = []): self
  {
    return new self(sent: false, error: $error, raw: $raw);
  }
}
