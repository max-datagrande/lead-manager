<?php

use App\Enums\DispatchStatus;
use App\Jobs\PingPost\DispatchLeadJob;
use App\Models\DispatchTimelineLog;
use App\Models\Lead;
use App\Models\LeadDispatch;
use App\Models\User;
use App\Models\Workflow;
use App\Services\PingPost\DispatchTimelineService;
use Illuminate\Support\Facades\Queue;

use function Pest\Laravel\actingAs;

function makePendingValidationDispatch(?DispatchStatus $status = null): LeadDispatch
{
  $workflow = Workflow::factory()->bestBid()->create();
  $lead = Lead::factory()->create(['fingerprint' => 'fp-force-' . uniqid()]);

  return LeadDispatch::create([
    'workflow_id' => $workflow->id,
    'lead_id' => $lead->id,
    'fingerprint' => $lead->fingerprint,
    'status' => $status ?? DispatchStatus::PENDING_VALIDATION,
    'strategy_used' => $workflow->strategy?->value,
    'started_at' => now(),
  ]);
}

test('admin can force-run a dispatch in pending_validation', function () {
  Queue::fake();

  $admin = User::factory()->create(['role' => 'admin']);
  $dispatch = makePendingValidationDispatch();

  $response = actingAs($admin)->postJson(route('ping-post.dispatches.force-run', $dispatch));

  $response->assertOk();
  $response->assertJson([
    'success' => true,
    'message' => 'Dispatch resumed. It will continue through the workflow shortly.',
  ]);

  expect($dispatch->fresh()->status)->toBe(DispatchStatus::RUNNING);

  Queue::assertPushed(DispatchLeadJob::class);

  $log = DispatchTimelineLog::where('lead_dispatch_id', $dispatch->id)
    ->where('event', DispatchTimelineService::VALIDATION_FORCED)
    ->first();

  expect($log)->not->toBeNull();
  expect($log->message)->toContain($admin->name);
  expect($log->context)->toMatchArray([
    'forced_by_user_id' => $admin->id,
    'forced_by_user_name' => $admin->name,
    'previous_status' => DispatchStatus::PENDING_VALIDATION->value,
  ]);
  expect($log->context)->toHaveKey('forced_at');
});

test('manager can force-run a dispatch in pending_validation', function () {
  Queue::fake();

  $manager = User::factory()->create(['role' => 'manager']);
  $dispatch = makePendingValidationDispatch();

  $response = actingAs($manager)->postJson(route('ping-post.dispatches.force-run', $dispatch));

  $response->assertOk();
  expect($dispatch->fresh()->status)->toBe(DispatchStatus::RUNNING);
  Queue::assertPushed(DispatchLeadJob::class);
});

test('force-run rejects 422 when dispatch is not in pending_validation', function () {
  Queue::fake();

  $admin = User::factory()->create(['role' => 'admin']);
  $statuses = [
    DispatchStatus::RUNNING,
    DispatchStatus::SOLD,
    DispatchStatus::NOT_SOLD,
    DispatchStatus::ERROR,
    DispatchStatus::TIMEOUT,
    DispatchStatus::VALIDATION_FAILED,
    DispatchStatus::PENDING,
  ];

  foreach ($statuses as $status) {
    $dispatch = makePendingValidationDispatch($status);

    $response = actingAs($admin)->postJson(route('ping-post.dispatches.force-run', $dispatch));

    $response->assertStatus(422);
    $response->assertJson([
      'success' => false,
      'message' => 'Only dispatches in PENDING_VALIDATION can be forced to run.',
    ]);

    expect($dispatch->fresh()->status)->toBe($status);
  }

  Queue::assertNothingPushed();
  expect(DispatchTimelineLog::where('event', DispatchTimelineService::VALIDATION_FORCED)->count())->toBe(0);
});

test('force-run is forbidden for users without admin or manager role', function () {
  Queue::fake();

  $user = User::factory()->create(['role' => 'user']);
  $dispatch = makePendingValidationDispatch();

  $response = actingAs($user)->postJson(route('ping-post.dispatches.force-run', $dispatch));

  $response->assertForbidden();
  expect($dispatch->fresh()->status)->toBe(DispatchStatus::PENDING_VALIDATION);
  Queue::assertNothingPushed();
});

test('force-run requires authentication', function () {
  Queue::fake();

  $dispatch = makePendingValidationDispatch();

  $response = $this->postJson(route('ping-post.dispatches.force-run', $dispatch));

  $response->assertUnauthorized();
  Queue::assertNothingPushed();
});
