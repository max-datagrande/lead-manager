<?php

namespace App\Services\LeadQuality\DTO;

/**
 * Result of a sync phone validation call.
 *
 * `classification` holds the business interpretation (e.g. valid_high_confidence,
 * disconnected_phone, validation_error). `valid` is the boolean shorthand for the
 * frontend; landings should generally trust this and use `classification` only
 * for analytics/UX nuance.
 */
class PhoneValidationResult
{
  public const CLASS_VALID_HIGH_CONFIDENCE = 'valid_high_confidence';
  public const CLASS_VALID_LOW_CONFIDENCE = 'valid_low_confidence';
  public const CLASS_LOW_CONFIDENCE = 'low_confidence';
  public const CLASS_INVALID_PHONE = 'invalid_phone';
  public const CLASS_DISCONNECTED_PHONE = 'disconnected_phone';
  public const CLASS_HIGH_RISK_PHONE = 'high_risk_phone';
  public const CLASS_COMPLIANCE_RISK = 'compliance_risk';
  public const CLASS_PENDING_OR_TIMEOUT = 'pending_or_timeout';
  public const CLASS_VALIDATION_ERROR = 'validation_error';

  /**
   * @param  array<int, string>  $resultCodes  Provider record codes (e.g. ['PS01','PS22']).
   * @param  array<string, mixed>  $raw         Raw provider response for trace/debug.
   */
  public function __construct(
    public readonly bool $valid,
    public readonly string $classification,
    public readonly ?string $lineType = null,
    public readonly ?string $country = null,
    public readonly ?string $carrier = null,
    public readonly ?string $normalizedPhone = null,
    public readonly array $resultCodes = [],
    public readonly ?string $error = null,
    public readonly array $raw = [],
  ) {}

  /**
   * @param  array<int, string>  $resultCodes
   * @param  array<string, mixed>  $raw
   */
  public static function valid(
    string $classification,
    ?string $lineType = null,
    ?string $country = null,
    ?string $carrier = null,
    ?string $normalizedPhone = null,
    array $resultCodes = [],
    array $raw = [],
  ): self {
    return new self(
      valid: true,
      classification: $classification,
      lineType: $lineType,
      country: $country,
      carrier: $carrier,
      normalizedPhone: $normalizedPhone,
      resultCodes: $resultCodes,
      raw: $raw,
    );
  }

  /**
   * @param  array<int, string>  $resultCodes
   * @param  array<string, mixed>  $raw
   */
  public static function invalid(
    string $classification,
    ?string $error = null,
    ?string $lineType = null,
    ?string $country = null,
    ?string $carrier = null,
    ?string $normalizedPhone = null,
    array $resultCodes = [],
    array $raw = [],
  ): self {
    return new self(
      valid: false,
      classification: $classification,
      lineType: $lineType,
      country: $country,
      carrier: $carrier,
      normalizedPhone: $normalizedPhone,
      resultCodes: $resultCodes,
      error: $error,
      raw: $raw,
    );
  }

  /**
   * @param  array<string, mixed>  $raw
   */
  public static function technicalError(string $error, array $raw = []): self
  {
    return new self(valid: false, classification: self::CLASS_VALIDATION_ERROR, error: $error, raw: $raw);
  }
}
