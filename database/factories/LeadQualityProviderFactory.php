<?php

namespace Database\Factories;

use App\Enums\LeadQuality\LeadQualityProviderType;
use App\Enums\LeadQuality\ProviderStatus;
use App\Models\LeadQualityProvider;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\LeadQualityProvider>
 */
class LeadQualityProviderFactory extends Factory
{
  protected $model = LeadQualityProvider::class;

  public function definition(): array
  {
    return [
      'name' => 'Twilio Verify - ' . $this->faker->unique()->word(),
      'type' => LeadQualityProviderType::TWILIO_VERIFY,
      'status' => ProviderStatus::ACTIVE,
      'is_enabled' => true,
      'environment' => 'sandbox',
      'credentials' => [
        'account_sid' => 'AC' . $this->faker->regexify('[a-f0-9]{32}'),
        'auth_token' => $this->faker->regexify('[a-f0-9]{32}'),
        'verify_service_sid' => 'VA' . $this->faker->regexify('[a-f0-9]{32}'),
      ],
      'settings' => [
        'default_channel' => 'sms',
        'default_locale' => 'en',
      ],
      'notes' => null,
    ];
  }

  public function active(): static
  {
    return $this->state([
      'status' => ProviderStatus::ACTIVE,
      'is_enabled' => true,
    ]);
  }

  public function disabled(): static
  {
    return $this->state([
      'status' => ProviderStatus::DISABLED,
      'is_enabled' => false,
    ]);
  }

  public function ipqs(): static
  {
    return $this->state([
      'name' => 'IPQS - ' . $this->faker->unique()->word(),
      'type' => LeadQualityProviderType::IPQS,
      'credentials' => [
        'api_key' => $this->faker->regexify('[A-Za-z0-9]{32}'),
      ],
      'settings' => [
        'strictness' => 1,
      ],
      'is_enabled' => false,
      'status' => ProviderStatus::DISABLED,
    ]);
  }

  public function production(): static
  {
    return $this->state(['environment' => 'production']);
  }
}
