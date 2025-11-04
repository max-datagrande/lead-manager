<?php

namespace App\Services\Postback;

use App\Interfaces\Postback\VendorIntegrationInterface;
use App\Services\NaturalIntelligenceService;
use InvalidArgumentException;

class VendorServiceResolver
{
  /**
   * @var array<string, class-string<VendorIntegrationInterface>>
   */
  protected array $vendorMap = [
    'ni' => NaturalIntelligenceService::class,
  ];

  public function make(string $vendor): VendorIntegrationInterface
  {
    $serviceClass = $this->vendorMap[$vendor] ?? null;

    if (!$serviceClass) {
      throw new InvalidArgumentException("No vendor service registered for '{$vendor}'.");
    }

    return app($serviceClass);
  }

  public function isRegistered(string $vendor): bool
  {
    return array_key_exists($vendor, $this->vendorMap);
  }
}
