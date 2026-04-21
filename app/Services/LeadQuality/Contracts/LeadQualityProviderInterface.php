<?php

namespace App\Services\LeadQuality\Contracts;

use App\Models\LeadQualityProvider;
use App\Models\LeadQualityValidationLog;
use App\Models\LeadQualityValidationRule;
use App\Services\LeadQuality\DTO\ChallengeResult;
use App\Services\LeadQuality\DTO\TestConnectionResult;
use App\Services\LeadQuality\DTO\VerifyResult;

interface LeadQualityProviderInterface
{
  /**
   * Dispatch a verification challenge (e.g., send SMS OTP).
   *
   * @param  array<string, mixed>  $context  Runtime data (destination, channel, locale, etc.).
   */
  public function sendChallenge(
    LeadQualityProvider $provider,
    LeadQualityValidationRule $rule,
    LeadQualityValidationLog $log,
    array $context,
  ): ChallengeResult;

  /**
   * Verify a user-submitted challenge code against the provider.
   *
   * @param  array<string, mixed>  $context
   */
  public function verifyChallenge(
    LeadQualityProvider $provider,
    LeadQualityValidationRule $rule,
    LeadQualityValidationLog $log,
    string $code,
    array $context,
  ): VerifyResult;

  /**
   * Health check against the provider credentials/endpoint. Used from admin UI.
   */
  public function testConnection(LeadQualityProvider $provider): TestConnectionResult;
}
