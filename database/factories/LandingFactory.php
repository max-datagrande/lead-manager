<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Landing>
 */
class LandingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Str;

    public function definition(): array
    {
        $name = $this->faker->company;
        return [
            'project_id' => Project::factory(),
            'name' => $name,
            'slug' => Str::slug($name) . '-' . Str::random(4),
            'user_id' => User::factory(),
            'updated_user_id' => User::factory(),
        ];
    }
}
