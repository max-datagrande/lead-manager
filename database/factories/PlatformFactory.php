<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Platform>
 */
class PlatformFactory extends Factory
{
  public function definition(): array
  {
    return [
      'name' => $this->faker->company(),
      'token_mappings' => [
        'Cost' => 'payout',
        'Callid' => 'click_id',
        'TxID' => 'transaction_id',
      ],
      'user_id' => User::factory(),
    ];
  }

  public function withMappings(array $mappings): static
  {
    return $this->state(['token_mappings' => $mappings]);
  }
}
