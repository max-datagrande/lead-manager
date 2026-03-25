<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Integration;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Integration>
 */
class IntegrationFactory extends Factory
{
    protected $model = Integration::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'user_id' => User::factory(),
            'name' => $this->faker->company(),
            'type' => 'ping-post',
            'is_active' => true,
            'request_mapping_config' => [],
            'payload_transformer' => null,
            'use_custom_transformer' => false,
        ];
    }

    public function pingPost(): static
    {
        return $this->state(['type' => 'ping-post']);
    }

    public function postOnly(): static
    {
        return $this->state(['type' => 'post-only']);
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }

    /**
     * Attach a ping and/or post environment and a BuyerConfig in one go.
     *
     * @param  array<string, mixed>  $configOverrides
     */
    public function withBuyerConfig(array $configOverrides = []): static
    {
        return $this->afterCreating(function (Integration $integration) use ($configOverrides): void {
            $integration->buyerConfig()->create(array_merge([
                'ping_url' => 'https://buyer.example.com/ping',
                'ping_method' => 'POST',
                'ping_headers' => [],
                'ping_body' => '{"fingerprint":"{fingerprint}"}',
                'ping_timeout_ms' => 3000,
                'post_url' => 'https://buyer.example.com/post',
                'post_method' => 'POST',
                'post_headers' => [],
                'post_body' => '{"fingerprint":"{fingerprint}"}',
                'post_timeout_ms' => 5000,
                'ping_response_config' => [
                    'bid_price_path' => 'bid',
                    'accepted_path' => 'accepted',
                    'accepted_value' => 'true',
                ],
                'post_response_config' => [
                    'accepted_path' => 'accepted',
                    'accepted_value' => 'true',
                    'rejected_path' => 'reason',
                ],
                'pricing_type' => 'fixed',
                'fixed_price' => 10.00,
            ], $configOverrides));
        });
    }
}
