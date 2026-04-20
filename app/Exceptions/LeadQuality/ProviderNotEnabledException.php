<?php

namespace App\Exceptions\LeadQuality;

use RuntimeException;

class ProviderNotEnabledException extends RuntimeException
{
  public static function forType(string $type): self
  {
    return new self("Lead Quality provider type '{$type}' is registered but not enabled in this build.");
  }
}
