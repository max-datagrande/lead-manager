<?php

use App\Enums\DispatchStatus;
use App\Enums\PostResultStatus;
use App\Models\Integration;
use App\Models\Lead;
use App\Models\LeadDispatch;
use App\Models\PostResult;
use App\Models\Workflow;
use App\Services\PingPost\PostbackResolverService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

// ─── Helpers ─────────────────────────────────────────────────────────────────

function pendingPostResult(array $overrides = []): PostResult
{
  $workflow = Workflow::factory()->create();
  $lead = Lead::factory()->create();
  $integration = Integration::factory()->postOnly()->create();

  $dispatch = LeadDispatch::create([
    'workflow_id' => $workflow->id,
    'lead_id' => $lead->id,
    'fingerprint' => fake()->uuid(),
    'status' => DispatchStatus::RUNNING,
    'strategy_used' => 'waterfall',
  ]);

  return PostResult::create(
    array_merge(
      [
        'lead_dispatch_id' => $dispatch->id,
        'integration_id' => $integration->id,
        'status' => PostResultStatus::PENDING_POSTBACK,
        'postback_expires_at' => now()->addDays(15),
      ],
      $overrides,
    ),
  );
}

// ─── resolvePostback ─────────────────────────────────────────────────────────

it('marks post result as postback_resolved and sets price_final', function () {
  $postResult = pendingPostResult();

  $resolved = app(PostbackResolverService::class)->resolvePostback($postResult->id, 25.0);

  expect($resolved->status)->toBe(PostResultStatus::POSTBACK_RESOLVED);
  expect((float) $resolved->price_final)->toBe(25.0);
  expect($resolved->postback_received_at)->not->toBeNull();
});

it('marks the dispatch as sold after resolving postback', function () {
  $postResult = pendingPostResult();
  $dispatch = $postResult->leadDispatch;

  app(PostbackResolverService::class)->resolvePostback($postResult->id, 10.0);

  $dispatch->refresh();
  expect($dispatch->status)->toBe(DispatchStatus::SOLD);
  expect((float) $dispatch->final_price)->toBe(10.0);
});

it('does not double-resolve if dispatch is already terminal', function () {
  $postResult = pendingPostResult();
  $dispatch = $postResult->leadDispatch;
  $dispatch->update(['status' => DispatchStatus::SOLD, 'final_price' => 5.0]);

  // Should not throw or change the dispatch status
  app(PostbackResolverService::class)->resolvePostback($postResult->id, 99.0);

  $dispatch->refresh();
  expect((float) $dispatch->final_price)->toBe(5.0); // unchanged
});

// ─── expireStalePostbacks ────────────────────────────────────────────────────

it('marks stale pending_postbacks as skipped', function () {
  $postResult = pendingPostResult(['postback_expires_at' => now()->subDay()]);

  $count = app(PostbackResolverService::class)->expireStalePostbacks();

  expect($count)->toBe(1);
  $postResult->refresh();
  expect($postResult->status)->toBe(PostResultStatus::SKIPPED);
});

it('marks dispatch as not_sold after all postbacks expire', function () {
  $postResult = pendingPostResult(['postback_expires_at' => now()->subHour()]);
  $dispatch = $postResult->leadDispatch;

  app(PostbackResolverService::class)->expireStalePostbacks();

  $dispatch->refresh();
  expect($dispatch->status)->toBe(DispatchStatus::NOT_SOLD);
});

it('does not expire postbacks that are still within the window', function () {
  pendingPostResult(['postback_expires_at' => now()->addDay()]);

  $count = app(PostbackResolverService::class)->expireStalePostbacks();

  expect($count)->toBe(0);
});

it('returns 0 when there are no pending postbacks', function () {
  expect(app(PostbackResolverService::class)->expireStalePostbacks())->toBe(0);
});
