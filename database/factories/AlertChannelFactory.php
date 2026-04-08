<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\AlertChannel>
 */
class AlertChannelFactory extends Factory
{
  public function definition(): array
  {
    return [
      'name' => fake()->words(2, true),
      'type' => 'slack',
      'webhook_url' => fake()->url(),
      'active' => true,
      'user_id' => User::factory(),
    ];
  }

  public function twilio(): static
  {
    return $this->state(['type' => 'twilio']);
  }

  public function inactive(): static
  {
    return $this->state(['active' => false]);
  }
}
