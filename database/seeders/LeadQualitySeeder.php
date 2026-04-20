<?php

namespace Database\Seeders;

use App\Enums\LeadQuality\LeadQualityProviderType;
use App\Enums\LeadQuality\ProviderStatus;
use App\Models\LeadQualityProvider;
use Illuminate\Database\Seeder;

class LeadQualitySeeder extends Seeder
{
  public function run(): void
  {
    LeadQualityProvider::firstOrCreate(
      ['name' => 'Twilio Verify (Sandbox)'],
      [
        'type' => LeadQualityProviderType::TWILIO_VERIFY,
        'status' => ProviderStatus::INACTIVE,
        'is_enabled' => false,
        'environment' => 'sandbox',
        'credentials' => [
          'account_sid' => '',
          'auth_token' => '',
          'verify_service_sid' => '',
        ],
        'settings' => [
          'default_channel' => 'sms',
          'default_locale' => 'en',
        ],
        'notes' => 'Fill in Twilio credentials and mark active before use.',
      ],
    );

    LeadQualityProvider::firstOrCreate(
      ['name' => 'IPQS (Placeholder)'],
      [
        'type' => LeadQualityProviderType::IPQS,
        'status' => ProviderStatus::DISABLED,
        'is_enabled' => false,
        'environment' => 'production',
        'credentials' => [
          'api_key' => '',
        ],
        'settings' => [
          'strictness' => 1,
        ],
        'notes' => 'Placeholder — integration not implemented yet.',
      ],
    );
  }
}
