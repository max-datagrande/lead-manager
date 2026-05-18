<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Vertical>
 */
class VerticalFactory extends Factory
{
  public function definition(): array
  {
    return [
      'name' => $this->faker->unique()->word(),
      'description' => $this->faker->sentence(),
      'active' => true,
      'user_id' => User::factory(),
    ];
  }
}
