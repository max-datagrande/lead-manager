<?php

namespace App\Services\LeadQuality\Contracts;

use App\Models\LeadQualityProvider;
use App\Services\LeadQuality\DTO\PhoneValidationResult;
use App\Services\LeadQuality\DTO\TestConnectionResult;

/**
 * Contract for sync phone-validation providers (e.g., Melissa Global Phone API).
 *
 * Kept separate from `LeadQualityProviderInterface` (which models async OTP
 * challenge/verify) because the call shape and lifecycle are different: a
 * single request returns an immediate verdict, with no user interaction.
 */
interface PhoneValidationProviderInterface
{
  /**
   * Validate a phone number against the provider.
   *
   * @param  array<string, mixed>  $context  Optional metadata. Recognized keys:
   *   - country (string, ISO2): suspected country, e.g. 'US'.
   *   - country_origin (string, ISO2): origin country for callable formatting.
   *   - trace (string): transmission reference echoed back by the provider.
   */
  public function validatePhone(LeadQualityProvider $provider, string $phone, array $context = []): PhoneValidationResult;

  /**
   * Health check against the provider credentials/endpoint. Used from admin UI.
   */
  public function testConnection(LeadQualityProvider $provider): TestConnectionResult;
}
