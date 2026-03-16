<?php

namespace Database\Factories;

use App\Enums\FireMode;
use App\Models\Platform;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Postback>
 */
class PostbackFactory extends Factory
{
  public function definition(): array
  {
    return [
      'uuid' => (string) Str::uuid(),
      'name' => $this->faker->words(3, true),
      'platform_id' => PlatformFactory::new(),
      'base_url' => 'https://dest.example.com/cv?click_id=&payout=',
      'param_mappings' => [
        'click_id' => 'click_id',
        'payout' => 'payout',
      ],
      'result_url' => 'https://dest.example.com/cv?click_id={click_id}&payout={payout}',
      'fire_mode' => FireMode::REALTIME,
      'is_active' => true,
      'is_public' => true,
      'user_id' => User::factory(),
    ];
  }

  public function realtime(): static
  {
    return $this->state(['fire_mode' => FireMode::REALTIME]);
  }

  public function deferred(): static
  {
    return $this->state(['fire_mode' => FireMode::DEFERRED]);
  }

  public function inactive(): static
  {
    return $this->state(['is_active' => false]);
  }

  public function withoutResultUrl(): static
  {
    return $this->state(['result_url' => null]);
  }

  public function asPublic(): static
  {
    return $this->state(['is_public' => true]);
  }

  public function asInternal(): static
  {
    return $this->state(['is_public' => false]);
  }

  /**
   * Postback with a specific platform (pass a Platform instance or use factory).
   */
  public function forPlatform(Platform $platform): static
  {
    return $this->state(['platform_id' => $platform->id]);
  }
}
