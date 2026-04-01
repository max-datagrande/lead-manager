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
   * Attach ping/post IntegrationEnvironments and a BuyerConfig in one go.
   *
   * @param  array<string, mixed>  $configOverrides  Overrides for the BuyerConfig fields only.
   */
  public function withBuyerConfig(array $configOverrides = []): static
  {
    return $this->afterCreating(function (Integration $integration) use ($configOverrides): void {
      // Create environments for both development and production
      foreach (['development', 'production'] as $envName) {
        if ($integration->type === 'ping-post') {
          $pingEnv = $integration->environments()->create([
            'environment' => $envName,
            'env_type' => 'ping',
            'url' => 'https://buyer.example.com/ping',
            'method' => 'POST',
            'request_headers' => '{}',
            'request_body' => '{"fingerprint":"{fingerprint}"}',
          ]);
          $pingEnv->pingResponseConfig()->create([
            'bid_price_path' => 'bid',
            'accepted_path' => 'accepted',
            'accepted_value' => 'true',
          ]);
        }

        $postEnv = $integration->environments()->create([
          'environment' => $envName,
          'env_type' => 'post',
          'url' => 'https://buyer.example.com/post',
          'method' => 'POST',
          'request_headers' => '{}',
          'request_body' => '{"fingerprint":"{fingerprint}"}',
        ]);
        $postEnv->postResponseConfig()->create([
          'accepted_path' => 'accepted',
          'accepted_value' => 'true',
          'rejected_path' => 'reason',
        ]);
      }

      $integration->buyerConfig()->create(array_merge([
        'ping_timeout_ms' => 3000,
        'post_timeout_ms' => 5000,
        'pricing_type' => 'fixed',
        'fixed_price' => 10.00,
      ], $configOverrides));
    });
  }
}
