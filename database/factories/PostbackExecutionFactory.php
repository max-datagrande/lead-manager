<?php

namespace Database\Factories;

use App\Enums\ExecutionStatus;
use App\Enums\PostbackSource;
use App\Models\Postback;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PostbackExecution>
 */
class PostbackExecutionFactory extends Factory
{
  public function definition(): array
  {
    return [
      'execution_uuid' => (string) Str::uuid(),
      'postback_id' => PostbackFactory::new(),
      'source' => PostbackSource::EXTERNAL_API,
      'status' => ExecutionStatus::PENDING,
      'inbound_params' => ['click_id' => 'CLK-' . $this->faker->numerify('######')],
      'resolved_tokens' => ['click_id' => 'CLK-' . $this->faker->numerify('######')],
      'outbound_url' => 'https://dest.example.com/cv?click_id=CLK-123',
      'ip_address' => $this->faker->ipv4(),
      'user_agent' => $this->faker->userAgent(),
      'attempts' => 0,
      'max_attempts' => 5,
      'idempotency_key' => hash('sha256', Str::uuid()),
    ];
  }

  public function pending(): static
  {
    return $this->state([
      'status' => ExecutionStatus::PENDING,
      'attempts' => 0,
    ]);
  }

  public function dispatching(): static
  {
    return $this->state([
      'status' => ExecutionStatus::DISPATCHING,
      'dispatched_at' => now(),
      'attempts' => 1,
    ]);
  }

  public function completed(): static
  {
    return $this->state([
      'status' => ExecutionStatus::COMPLETED,
      'dispatched_at' => now()->subSeconds(2),
      'completed_at' => now(),
      'attempts' => 1,
    ]);
  }

  public function failed(): static
  {
    return $this->state([
      'status' => ExecutionStatus::FAILED,
      'dispatched_at' => now()->subSeconds(2),
      'attempts' => 1,
      'next_retry_at' => now()->addSeconds(60),
    ]);
  }

  public function failedRetryable(): static
  {
    return $this->state([
      'status' => ExecutionStatus::FAILED,
      'attempts' => 1,
      'max_attempts' => 5,
      'next_retry_at' => now()->subMinute(), // past → retryable
    ]);
  }

  public function failedExhausted(): static
  {
    return $this->state([
      'status' => ExecutionStatus::FAILED,
      'attempts' => 5,
      'max_attempts' => 5,
      'next_retry_at' => null,
    ]);
  }

  public function fromOfferwall(): static
  {
    return $this->state(['source' => PostbackSource::OFFERWALL]);
  }

  public function fromPingPost(): static
  {
    return $this->state(['source' => PostbackSource::PING_POST]);
  }

  public function fromWorkflow(): static
  {
    return $this->state(['source' => PostbackSource::WORKFLOW]);
  }

  public function manual(): static
  {
    return $this->state(['source' => PostbackSource::MANUAL]);
  }
}
