<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Host>
 */
class HostFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
use App\Models\Landing;
use App\Models\User;

    public function definition(): array
    {
        return [
            'landing_id' => Landing::factory(),
            'domain' => $this->faker->unique()->domainName,
            'is_active' => $this->faker->boolean(80), // 80% chance of being true
            'user_id' => User::factory(),
            'updated_user_id' => User::factory(),
        ];
    }
}
