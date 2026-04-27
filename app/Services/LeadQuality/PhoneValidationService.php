<?php

namespace App\Services\LeadQuality;

use App\Enums\LeadQuality\LeadQualityProviderType;
use App\Enums\LeadQuality\ProviderStatus;
use App\Models\LeadQualityProvider;
use App\Services\LeadQuality\DTO\PhoneValidationResult;
use Illuminate\Support\Facades\Cache;

/**
 * Orchestrates phone validation: resolves the provider, normalizes the phone,
 * and serves cache hits without touching the upstream.
 *
 * Auditing invariant: one row in `external_service_requests` corresponds to
 * exactly one real call to the provider. Cache hits never enter the recorder
 * closure, so they don't generate a row — the recording happens inside the
 * `Cache::remember()` callback, not outside.
 */
class PhoneValidationService
{
  private const DEFAULT_CACHE_TTL_SECONDS = 300;

  public function __construct(private readonly LeadQualityProviderResolver $resolver) {}

  /**
   * Validate a phone number through the configured Melissa-style provider.
   *
   * @param  array<string, mixed>  $context  Optional metadata: country, country_origin, trace.
   */
  public function validate(string $phone, ?LeadQualityProvider $provider = null, array $context = []): PhoneValidationResult
  {
    $provider ??= $this->resolveDefaultProvider();

    if (!$provider) {
      return PhoneValidationResult::technicalError('No active phone validation provider configured.');
    }

    $normalized = $this->normalize($phone, (string) ($context['country'] ?? 'US'));
    if ($normalized === '') {
      return PhoneValidationResult::invalid(classification: PhoneValidationResult::CLASS_INVALID_PHONE, error: 'Empty phone number.');
    }

    $cacheKey = "lead_quality:phone_validation:{$provider->id}:{$normalized}";
    $ttl = (int) ($provider->settings['cache_ttl'] ?? self::DEFAULT_CACHE_TTL_SECONDS);

    return Cache::remember($cacheKey, $ttl, function () use ($provider, $normalized, $context): PhoneValidationResult {
      return $this->resolver->forPhoneValidation($provider)->validatePhone($provider, $normalized, $context);
    });
  }

  /**
   * Resolve the first active Melissa-type provider. Today there's a single
   * "default" provider per environment; if multiple are ever configured, the
   * lowest id wins — explicit selection is the caller's job by passing
   * `$provider` directly.
   */
  private function resolveDefaultProvider(): ?LeadQualityProvider
  {
    return LeadQualityProvider::query()
      ->where('type', LeadQualityProviderType::MELISSA->value)
      ->where('is_enabled', true)
      ->where('status', ProviderStatus::ACTIVE->value)
      ->orderBy('id')
      ->first();
  }

  /**
   * Light normalization for cache keying. Strips whitespace and prefixes a
   * default country code for bare US-shaped numbers. The actual upstream
   * normalization is the provider's responsibility — this only stabilizes
   * the cache key so equivalent inputs (`8006354772`, `+1 800-635-4772`)
   * resolve to the same entry.
   */
  private function normalize(string $phone, string $country): string
  {
    $phone = trim($phone);
    if ($phone === '') {
      return '';
    }

    if (str_starts_with($phone, '+')) {
      return '+' . preg_replace('/\D/', '', $phone);
    }

    $digits = preg_replace('/\D/', '', $phone) ?? '';
    if ($digits === '') {
      return '';
    }

    if ($country === 'US') {
      if (strlen($digits) === 10) {
        return '+1' . $digits;
      }
      if (strlen($digits) === 11 && str_starts_with($digits, '1')) {
        return '+' . $digits;
      }
    }

    return $digits;
  }
}
