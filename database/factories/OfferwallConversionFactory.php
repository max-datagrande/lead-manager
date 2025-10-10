<?php

namespace Database\Factories;

use App\Models\OfferwallConversion;
use App\Models\Integration;
use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\OfferwallConversion>
 */
class OfferwallConversionFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = OfferwallConversion::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // As per user instruction, fetch the single existing integration and company.
        // Using a static variable to avoid querying the database for every created model.
        static $integrationId;
        static $companyId;

        if (!$integrationId) {
            $integrationId = Integration::first()->id;
        }

        if (!$companyId) {
            $companyId = Company::first()->id;
        }

        return [
            'integration_id' => $integrationId,
            'company_id' => $companyId,
            'amount' => $this->faker->randomFloat(4, 0.1, 100),
            'fingerprint' => $this->faker->sha256,
            'click_id' => $this->faker->uuid,
            'utm_source' => $this->faker->word,
            'utm_medium' => $this->faker->word,
        ];
    }
}
