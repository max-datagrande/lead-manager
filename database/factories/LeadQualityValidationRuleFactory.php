<?php

namespace Database\Factories;

use App\Enums\LeadQuality\RuleStatus;
use App\Enums\LeadQuality\ValidationType;
use App\Models\LeadQualityProvider;
use App\Models\LeadQualityValidationRule;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\LeadQualityValidationRule>
 */
class LeadQualityValidationRuleFactory extends Factory
{
  protected $model = LeadQualityValidationRule::class;

  public function definition(): array
  {
    $name = 'OTP phone validation ' . $this->faker->unique()->numberBetween(1, 99999);

    return [
      'name' => $name,
      'slug' => Str::slug($name) . '-' . $this->faker->unique()->numberBetween(1, 99999),
      'validation_type' => ValidationType::OTP_PHONE,
      'provider_id' => LeadQualityProviderFactory::new(),
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
    ];
  }

  public function draft(): static
  {
    return $this->state([
      'status' => RuleStatus::DRAFT,
      'is_enabled' => false,
    ]);
  }

  public function inactive(): static
  {
    return $this->state([
      'status' => RuleStatus::INACTIVE,
      'is_enabled' => false,
    ]);
  }

  public function phoneLookup(): static
  {
    return $this->state([
      'validation_type' => ValidationType::PHONE_LOOKUP,
      'settings' => [
        'sync_check' => true,
        'validity_window' => 60,
      ],
    ]);
  }

  public function ipqsScore(): static
  {
    return $this->state([
      'validation_type' => ValidationType::IPQS_SCORE,
      'settings' => [
        'sync_check' => true,
        'required_score' => 75,
        'validity_window' => 60,
      ],
    ]);
  }

  public function forProvider(LeadQualityProvider $provider): static
  {
    return $this->state(['provider_id' => $provider->id]);
  }
}
