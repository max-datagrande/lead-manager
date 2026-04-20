<?php

namespace App\Services\LeadQuality\Providers;

use App\Exceptions\LeadQuality\ProviderNotEnabledException;
use App\Models\LeadQualityProvider;
use App\Models\LeadQualityValidationLog;
use App\Models\LeadQualityValidationRule;
use App\Services\LeadQuality\Contracts\LeadQualityProviderInterface;
use App\Services\LeadQuality\DTO\ChallengeResult;
use App\Services\LeadQuality\DTO\TestConnectionResult;
use App\Services\LeadQuality\DTO\VerifyResult;

/**
 * IPQS placeholder. Reserved in the resolver so the admin UI shows it in the
 * provider type dropdown, but every operation errors out to prevent accidental use.
 */
class IpqsProvider implements LeadQualityProviderInterface
{
  public function sendChallenge(
    LeadQualityProvider $provider,
    LeadQualityValidationRule $rule,
    LeadQualityValidationLog $log,
    array $context,
  ): ChallengeResult {
    throw ProviderNotEnabledException::forType('ipqs');
  }

  public function verifyChallenge(
    LeadQualityProvider $provider,
    LeadQualityValidationRule $rule,
    LeadQualityValidationLog $log,
    string $code,
    array $context,
  ): VerifyResult {
    throw ProviderNotEnabledException::forType('ipqs');
  }

  public function testConnection(LeadQualityProvider $provider): TestConnectionResult
  {
    return TestConnectionResult::failure('IPQS provider is a placeholder and has not been implemented yet.');
  }
}
