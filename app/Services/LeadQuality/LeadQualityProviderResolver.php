<?php

namespace App\Services\LeadQuality;

use App\Enums\LeadQuality\LeadQualityProviderType;
use App\Models\LeadQualityProvider;
use App\Services\LeadQuality\Contracts\LeadQualityProviderInterface;
use App\Services\LeadQuality\Contracts\PhoneValidationProviderInterface;
use App\Services\LeadQuality\Providers\IpqsProvider;
use App\Services\LeadQuality\Providers\MelissaProvider;
use App\Services\LeadQuality\Providers\TwilioVerifyProvider;
use InvalidArgumentException;
use RuntimeException;

class LeadQualityProviderResolver
{
  /**
   * @var array<string, class-string<LeadQualityProviderInterface>>
   */
  protected array $providerMap = [
    'twilio_verify' => TwilioVerifyProvider::class,
    'ipqs' => IpqsProvider::class,
    'melissa' => MelissaProvider::class,
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

  /**
   * Resolve the sync phone-validation implementation for the given provider.
   *
   * Throws if the resolved class doesn't implement the sync contract — protects
   * against accidentally wiring an async-only provider (e.g. Twilio) into the
   * phone validation flow.
   */
  public function forPhoneValidation(LeadQualityProvider $provider): PhoneValidationProviderInterface
  {
    $impl = $this->make($provider->type);

    if (!$impl instanceof PhoneValidationProviderInterface) {
      throw new RuntimeException("Provider type '{$provider->type->value}' does not support phone validation.");
    }

    return $impl;
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
