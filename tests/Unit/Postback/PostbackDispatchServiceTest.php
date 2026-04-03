<?php

use App\Enums\ExecutionStatus;
use App\Models\PostbackExecution;
use App\Services\PostbackDispatchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(Tests\TestCase::class, RefreshDatabase::class);

/**
 * Helper: run dispatch in a non-local environment so real HTTP path is exercised.
 */
function dispatchInProduction(PostbackExecution $execution): void
{
  $originalEnv = app()->environment();
  app()->detectEnvironment(fn() => 'production');

  try {
    app(PostbackDispatchService::class)->dispatch($execution);
  } finally {
    app()->detectEnvironment(fn() => $originalEnv);
  }
}

describe('local environment simulation', function () {
  it('marks execution as completed and logs simulated response', function () {
    Http::fake(); // ensure no real requests

    $execution = PostbackExecution::factory()->pending()->create();
    $execution->incrementAttempt();

    app(PostbackDispatchService::class)->dispatch($execution);

    $execution->refresh();

    expect($execution->status)->toBe(ExecutionStatus::COMPLETED);

    $log = $execution->dispatchLogs()->first();
    expect($log)->not->toBeNull();
    expect($log->response_status_code)->toBe(200);
    expect($log->response_body)->toContain('"simulated":true');

    Http::assertNothingSent();
  });
});

describe('production HTTP dispatch', function () {
  it('marks execution as completed when destination returns 2xx', function () {
    Http::fake(['*' => Http::response('OK', 200)]);

    $execution = PostbackExecution::factory()->pending()->create();
    $execution->incrementAttempt();

    dispatchInProduction($execution);

    $execution->refresh();

    expect($execution->status)->toBe(ExecutionStatus::COMPLETED);

    $log = $execution->dispatchLogs()->first();
    expect($log->response_status_code)->toBe(200);
    expect($log->response_time_ms)->not->toBeNull();

    Http::assertSentCount(1);
  });

  it('marks execution as failed when destination returns 5xx', function () {
    Http::fake(['*' => Http::response('Internal Server Error', 500)]);

    $execution = PostbackExecution::factory()
      ->pending()
      ->create(['attempts' => 0, 'max_attempts' => 5]);
    $execution->incrementAttempt();

    dispatchInProduction($execution);

    $execution->refresh();

    expect($execution->status)->toBe(ExecutionStatus::FAILED);

    $log = $execution->dispatchLogs()->first();
    expect($log->response_status_code)->toBe(500);
    expect($execution->next_retry_at)->not->toBeNull();
  });

  it('marks execution as failed on network exception and logs error message', function () {
    Http::fake([
      '*' => function () {
        throw new \Exception('Connection refused');
      },
    ]);

    $execution = PostbackExecution::factory()->pending()->create();
    $execution->incrementAttempt();

    dispatchInProduction($execution);

    $execution->refresh();

    expect($execution->status)->toBe(ExecutionStatus::FAILED);

    $log = $execution->dispatchLogs()->first();
    expect($log->error_message)->toContain('Connection refused');
  });

  it('creates a dispatch log for every attempt', function () {
    Http::fake(['*' => Http::response('OK', 200)]);

    $execution = PostbackExecution::factory()->pending()->create();
    $execution->incrementAttempt();

    dispatchInProduction($execution);

    expect($execution->dispatchLogs()->count())->toBe(1);
  });
});
