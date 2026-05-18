<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\Vertical;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\LandingPage>
 */
class LandingPageFactory extends Factory
{
  public function definition(): array
  {
    return [
      'name' => $this->faker->unique()->company() . ' Landing',
      'url' => $this->faker->unique()->url(),
      'is_external' => false,
      'vertical_id' => Vertical::factory(),
      'company_id' => null,
      'active' => true,
      'user_id' => User::factory(),
    ];
  }
}
