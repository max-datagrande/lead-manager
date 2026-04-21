<?php

namespace App\Services\LeadQuality;

use App\Enums\LeadQuality\LeadQualityProviderType;
use App\Models\LeadQualityProvider;
use App\Services\LeadQuality\Contracts\LeadQualityProviderInterface;
use App\Services\LeadQuality\Providers\IpqsProvider;
use App\Services\LeadQuality\Providers\TwilioVerifyProvider;
use InvalidArgumentException;

class LeadQualityProviderResolver
{
  /**
   * @var array<string, class-string<LeadQualityProviderInterface>>
   */
  protected array $providerMap = [
    'twilio_verify' => TwilioVerifyProvider::class,
    'ipqs' => IpqsProvider::class,
  ];

  public function make(LeadQualityProviderType|string $type): LeadQualityProviderInterface
  {
    $key = $type instanceof LeadQualityProviderType ? $type->value : $type;
    $serviceClass = $this->providerMap[$key] ?? null;

    if (!$serviceClass) {
      throw new InvalidArgumentException("No Lead Quality provider registered for type '{$key}'.");
    }

    return app($serviceClass);
  }

  public function forProvider(LeadQualityProvider $provider): LeadQualityProviderInterface
  {
    return $this->make($provider->type);
  }

  public function isRegistered(string $type): bool
  {
    return array_key_exists($type, $this->providerMap);
  }

  /**
   * @return array<int, string>
   */
  public function registeredTypes(): array
  {
    return array_keys($this->providerMap);
  }
}
