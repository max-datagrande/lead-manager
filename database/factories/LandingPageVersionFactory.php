<?php

namespace Database\Factories;

use App\Models\LandingPage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\LandingPageVersion>
 */
class LandingPageVersionFactory extends Factory
{
  public function definition(): array
  {
    return [
      'landing_page_id' => LandingPage::factory(),
      'name' => $this->faker->unique()->word() . ' version',
      'description' => $this->faker->sentence(),
      'path' => '/' . $this->faker->unique()->slug(2) . '/',
      'status' => true,
    ];
  }
}
