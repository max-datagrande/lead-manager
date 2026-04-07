<?php

namespace Database\Factories;

use App\Enums\WorkflowStrategy;
use App\Models\User;
use App\Models\Workflow;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Workflow>
 */
class WorkflowFactory extends Factory
{
  protected $model = Workflow::class;

  public function definition(): array
  {
    return [
      'name' => $this->faker->words(3, true),
      'execution_mode' => 'sync',
      'strategy' => WorkflowStrategy::BEST_BID,
      'global_timeout_ms' => 5000,
      'is_active' => true,
      'user_id' => User::factory(),
      'cascade_on_post_rejection' => true,
      'cascade_max_retries' => 3,
      'advance_on_rejection' => true,
      'advance_on_timeout' => true,
      'advance_on_error' => false,
    ];
  }

  public function bestBid(): static
  {
    return $this->state(['strategy' => WorkflowStrategy::BEST_BID]);
  }

  public function waterfall(): static
  {
    return $this->state(['strategy' => WorkflowStrategy::WATERFALL]);
  }

  public function combined(): static
  {
    return $this->state(['strategy' => WorkflowStrategy::COMBINED]);
  }

  public function async(): static
  {
    return $this->state(['execution_mode' => 'async']);
  }

  public function inactive(): static
  {
    return $this->state(['is_active' => false]);
  }
}
