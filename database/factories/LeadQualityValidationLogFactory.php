<?php

namespace Database\Factories;

use App\Enums\LeadQuality\ValidationLogStatus;
use App\Models\LeadQualityValidationLog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\LeadQualityValidationLog>
 */
class LeadQualityValidationLogFactory extends Factory
{
  protected $model = LeadQualityValidationLog::class;

  public function definition(): array
  {
    return [
      'validation_rule_id' => LeadQualityValidationRuleFactory::new(),
      'integration_id' => IntegrationFactory::new(),
      'lead_id' => null,
      'provider_id' => null,
      'lead_dispatch_id' => null,
      'fingerprint' => $this->faker->sha256(),
      'status' => ValidationLogStatus::PENDING,
      'attempts_count' => 0,
      'result' => null,
      'context' => [
        'channel' => 'sms',
        'masked_destination' => '+1*******1234',
      ],
      'message' => null,
      'challenge_reference' => null,
      'started_at' => now(),
      'resolved_at' => null,
      'expires_at' => now()->addMinutes(15),
    ];
  }

  public function sent(): static
  {
    return $this->state([
      'status' => ValidationLogStatus::SENT,
      'challenge_reference' => 'VE' . $this->faker->regexify('[a-f0-9]{32}'),
    ]);
  }

  public function verified(): static
  {
    return $this->state([
      'status' => ValidationLogStatus::VERIFIED,
      'result' => 'pass',
      'resolved_at' => now(),
      'challenge_reference' => 'VE' . $this->faker->regexify('[a-f0-9]{32}'),
    ]);
  }

  public function failed(): static
  {
    return $this->state([
      'status' => ValidationLogStatus::FAILED,
      'result' => 'fail',
      'resolved_at' => now(),
      'message' => 'Invalid code',
    ]);
  }

  public function expired(): static
  {
    return $this->state([
      'status' => ValidationLogStatus::EXPIRED,
      'result' => 'fail',
      'resolved_at' => now(),
      'expires_at' => now()->subMinutes(1),
    ]);
  }
}
