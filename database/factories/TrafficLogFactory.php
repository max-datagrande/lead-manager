<?php

namespace Database\Factories;

use App\Models\TrafficLog;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\TrafficLog>
 */
class TrafficLogFactory extends Factory
{
  protected $model = TrafficLog::class;

  /**
   * @return array<string, mixed>
   */
  public function definition(): array
  {
    return [
      'id' => (string) Str::uuid(),
      'fingerprint' => hash('sha256', $this->faker->unique()->text(50)),
      'visit_date' => now()->toDateString(),
      'visit_count' => 1,
      'ip_address' => $this->faker->ipv4(),
      'user_agent' => $this->faker->userAgent(),
      'host' => $this->faker->domainName(),
      'is_bot' => false,
    ];
  }

  public function bot(): static
  {
    return $this->state(fn() => ['is_bot' => true]);
  }

  public function withPlatform(string $platform, string $channel = 'ads'): static
  {
    return $this->state(
      fn() => [
        'platform' => $platform,
        'channel' => $channel,
      ],
    );
  }

  public function withUtm(array $utm = []): static
  {
    return $this->state(
      fn() => array_merge(
        [
          'utm_source' => 'google',
          'utm_medium' => 'cpc',
          'utm_campaign_name' => 'test_campaign',
        ],
        $utm,
      ),
    );
  }
}
