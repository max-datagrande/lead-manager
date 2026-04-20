<?php

namespace Database\Seeders;

use App\Enums\LeadQuality\LeadQualityProviderType;
use App\Enums\LeadQuality\ProviderStatus;
use App\Enums\LeadQuality\RuleStatus;
use App\Enums\LeadQuality\ValidationLogStatus;
use App\Enums\LeadQuality\ValidationType;
use App\Models\ExternalServiceRequest;
use App\Models\Integration;
use App\Models\LeadQualityProvider;
use App\Models\LeadQualityValidationLog;
use App\Models\LeadQualityValidationRule;
use Illuminate\Database\Seeder;

/**
 * Seeds realistic Lead Quality data for manual UI testing.
 *
 * Creates:
 *  - One active Twilio Verify provider
 *  - Two rules (async OTP, sync phone lookup) linked to a handful of buyers
 *  - A mixed cohort of validation logs covering every status
 *  - Two external service request rows attached to a verified log so the
 *    technical modal has content to show.
 */
class LeadQualityDemoSeeder extends Seeder
{
  public function run(): void
  {
    $provider = LeadQualityProvider::firstOrCreate(
      ['name' => 'Twilio Verify (Demo)'],
      [
        'type' => LeadQualityProviderType::TWILIO_VERIFY,
        'status' => ProviderStatus::ACTIVE,
        'is_enabled' => true,
        'environment' => 'sandbox',
        'credentials' => [
          'account_sid' => 'AC' . str_repeat('a', 32),
          'auth_token' => 'demo-token-' . str_repeat('x', 20),
          'verify_service_sid' => 'VA' . str_repeat('b', 32),
        ],
        'settings' => ['default_channel' => 'sms', 'default_locale' => 'en'],
        'notes' => 'Auto-seeded demo provider. Replace credentials before production use.',
      ],
    );

    $rule1 = LeadQualityValidationRule::firstOrCreate(
      ['slug' => 'otp-phone-demo'],
      [
        'name' => 'OTP Phone Validation',
        'validation_type' => ValidationType::OTP_PHONE,
        'provider_id' => $provider->id,
        'status' => RuleStatus::ACTIVE,
        'is_enabled' => true,
        'description' => 'Requires phone OTP verification before dispatch.',
        'settings' => [
          'channel' => 'sms',
          'otp_length' => 6,
          'ttl' => 600,
          'max_attempts' => 3,
          'validity_window' => 15,
        ],
        'priority' => 100,
      ],
    );

    $rule2 = LeadQualityValidationRule::firstOrCreate(
      ['slug' => 'phone-lookup-demo'],
      [
        'name' => 'Phone Lookup (Premium)',
        'validation_type' => ValidationType::PHONE_LOOKUP,
        'provider_id' => $provider->id,
        'status' => RuleStatus::ACTIVE,
        'is_enabled' => true,
        'description' => 'Synchronous phone line-type lookup before dispatch.',
        'settings' => ['sync_check' => true, 'validity_window' => 60],
        'priority' => 50,
      ],
    );

    $buyers = Integration::query()
      ->whereIn('type', ['ping-post', 'post-only'])
      ->orderBy('id')
      ->take(3)
      ->get();

    if ($buyers->isNotEmpty()) {
      $pivot = $buyers->pluck('id')->mapWithKeys(fn(int $id) => [$id => ['is_enabled' => true]])->all();
      $rule1->buyers()->syncWithoutDetaching($pivot);
      $rule2->buyers()->syncWithoutDetaching([$buyers->first()->id => ['is_enabled' => true]]);
    }

    $primaryBuyer = $buyers->first();
    $secondaryBuyer = $buyers->get(1) ?? $primaryBuyer;

    LeadQualityValidationLog::factory()
      ->count(4)
      ->verified()
      ->create([
        'validation_rule_id' => $rule1->id,
        'provider_id' => $provider->id,
        'integration_id' => $primaryBuyer?->id,
      ]);

    LeadQualityValidationLog::factory()
      ->count(3)
      ->sent()
      ->create([
        'validation_rule_id' => $rule1->id,
        'provider_id' => $provider->id,
        'integration_id' => $secondaryBuyer?->id,
      ]);

    LeadQualityValidationLog::factory()
      ->count(2)
      ->failed()
      ->create([
        'validation_rule_id' => $rule1->id,
        'provider_id' => $provider->id,
        'integration_id' => $primaryBuyer?->id,
      ]);

    LeadQualityValidationLog::factory()
      ->count(2)
      ->expired()
      ->create([
        'validation_rule_id' => $rule2->id,
        'provider_id' => $provider->id,
        'integration_id' => $primaryBuyer?->id,
      ]);

    LeadQualityValidationLog::factory()->create([
      'validation_rule_id' => $rule2->id,
      'provider_id' => $provider->id,
      'integration_id' => $secondaryBuyer?->id,
      'status' => ValidationLogStatus::PENDING,
    ]);

    $verifiedLog = LeadQualityValidationLog::query()
      ->where('validation_rule_id', $rule1->id)
      ->where('status', ValidationLogStatus::VERIFIED)
      ->latest('id')
      ->first();

    if ($verifiedLog && $verifiedLog->externalRequests()->count() === 0) {
      ExternalServiceRequest::factory()->create([
        'loggable_type' => LeadQualityValidationLog::class,
        'loggable_id' => $verifiedLog->id,
        'service_id' => $provider->id,
        'operation' => 'send_challenge',
      ]);
      ExternalServiceRequest::factory()
        ->forVerify()
        ->create([
          'loggable_type' => LeadQualityValidationLog::class,
          'loggable_id' => $verifiedLog->id,
          'service_id' => $provider->id,
          'operation' => 'verify_challenge',
        ]);
    }

    $this->command?->info(sprintf(
      'Lead Quality demo data ready → provider #%d, rules: %d, logs: %d, external requests: %d',
      $provider->id,
      LeadQualityValidationRule::count(),
      LeadQualityValidationLog::count(),
      ExternalServiceRequest::count(),
    ));
  }
}
