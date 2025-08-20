<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Project>
 */
class ProjectFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
use App\Models\User;

    public function definition(): array
    {
        return [
            'name' => $this->faker->company,
            'description' => $this->faker->optional()->paragraph,
            'user_id' => User::factory(),
            'updated_user_id' => User::factory(),
        ];
    }
}
