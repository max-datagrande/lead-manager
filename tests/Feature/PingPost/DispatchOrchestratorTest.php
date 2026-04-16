<?php

use App\Enums\DispatchStatus;
use App\Models\Integration;
use App\Models\Lead;
use App\Models\Workflow;
use App\Models\WorkflowBuyer;
use App\Services\PingPost\DispatchOrchestrator;
use Illuminate\Support\Facades\Http;

// ─── Helpers ─────────────────────────────────────────────────────────────────

/**
 * Create a workflow with buyers already attached.
 *
 * @param  array<int, array{integration: Integration, position: int, group?: string, is_fallback?: bool}>  $buyers
 */
function attachBuyers(Workflow $workflow, array $buyers): void
{
  foreach ($buyers as $item) {
    WorkflowBuyer::create([
      'workflow_id' => $workflow->id,
      'integration_id' => $item['integration']->id,
      'position' => $item['position'],
      'buyer_group' => $item['group'] ?? 'primary',
      'is_fallback' => $item['is_fallback'] ?? false,
      'is_active' => true,
    ]);
  }
}

function fakePingAccepted(float $bid = 10.0): array
{
  return ['accepted' => 'true', 'bid' => $bid];
}

function fakePingRejected(): array
{
  return ['accepted' => 'false', 'bid' => 0];
}

function fakePostAccepted(): array
{
  return ['accepted' => 'true'];
}

function fakePostRejected(): array
{
  return ['accepted' => 'false', 'reason' => 'duplicate'];
}

// ─── Best Bid ────────────────────────────────────────────────────────────────

describe('Best Bid strategy', function () {
  it('posts to the highest bidder and marks dispatch as sold', function () {
    $lead = Lead::factory()->create(['fingerprint' => 'fp-bestbid-1']);

    $buyer1 = Integration::factory()
      ->pingPost()
      ->withBuyerConfig(['price_source' => 'response_bid', 'min_bid' => 0])
      ->create(['name' => 'Buyer A']);
    $buyer2 = Integration::factory()
      ->pingPost()
      ->withBuyerConfig(['price_source' => 'response_bid', 'min_bid' => 0])
      ->create(['name' => 'Buyer B']);

    $workflow = Workflow::factory()->bestBid()->create();
    attachBuyers($workflow, [['integration' => $buyer1, 'position' => 0], ['integration' => $buyer2, 'position' => 1]]);

    Http::fake([
      'https://buyer.example.com/ping' => Http::sequence()
        ->push(fakePingAccepted(8.0)) // buyer1 bids 8
        ->push(fakePingAccepted(12.0)), // buyer2 bids 12
      'https://buyer.example.com/post' => Http::response(fakePostAccepted()),
    ]);

    $dispatch = app(DispatchOrchestrator::class)->dispatch($workflow, $lead, $lead->fingerprint);

    expect($dispatch->status)->toBe(DispatchStatus::SOLD);
    expect((float) $dispatch->final_price)->toBe(12.0);
    expect($dispatch->winner_integration_id)->toBe($buyer2->id);

    $this->assertDatabaseHas('ping_results', ['integration_id' => $buyer1->id, 'status' => 'accepted']);
    $this->assertDatabaseHas('ping_results', ['integration_id' => $buyer2->id, 'status' => 'accepted']);
    $this->assertDatabaseHas('post_results', ['integration_id' => $buyer2->id, 'status' => 'accepted']);
  });

  it('cascades to the next bidder when winner rejects post', function () {
    $lead = Lead::factory()->create(['fingerprint' => 'fp-bestbid-cascade']);

    $buyer1 = Integration::factory()->pingPost()->withBuyerConfig()->create();
    $buyer2 = Integration::factory()->pingPost()->withBuyerConfig()->create();

    $workflow = Workflow::factory()
      ->bestBid()
      ->create(['cascade_on_post_rejection' => true, 'cascade_max_retries' => 2]);
    attachBuyers($workflow, [['integration' => $buyer1, 'position' => 0], ['integration' => $buyer2, 'position' => 1]]);

    Http::fake([
      'https://buyer.example.com/ping' => Http::sequence()
        ->push(fakePingAccepted(15.0)) // buyer1 wins bid
        ->push(fakePingAccepted(10.0)), // buyer2 second
      'https://buyer.example.com/post' => Http::sequence()
        ->push(fakePostRejected()) // winner rejects
        ->push(fakePostAccepted()), // #2 accepts
    ]);

    $dispatch = app(DispatchOrchestrator::class)->dispatch($workflow, $lead, $lead->fingerprint);

    expect($dispatch->status)->toBe(DispatchStatus::SOLD);
    expect($dispatch->winner_integration_id)->toBe($buyer2->id);
    $this->assertDatabaseHas('post_results', ['integration_id' => $buyer1->id, 'status' => 'rejected']);
    $this->assertDatabaseHas('post_results', ['integration_id' => $buyer2->id, 'status' => 'accepted']);
  });

  it('marks dispatch as not_sold when all buyers reject', function () {
    $lead = Lead::factory()->create(['fingerprint' => 'fp-bestbid-nosale']);

    $buyer = Integration::factory()->pingPost()->withBuyerConfig()->create();
    $workflow = Workflow::factory()
      ->bestBid()
      ->create(['cascade_max_retries' => 1]);
    attachBuyers($workflow, [['integration' => $buyer, 'position' => 0]]);

    Http::fake([
      'https://buyer.example.com/ping' => Http::response(fakePingAccepted(5.0)),
      'https://buyer.example.com/post' => Http::response(fakePostRejected()),
    ]);

    $dispatch = app(DispatchOrchestrator::class)->dispatch($workflow, $lead, $lead->fingerprint);

    expect($dispatch->status)->toBe(DispatchStatus::NOT_SOLD);
    expect($dispatch->winner_integration_id)->toBeNull();
  });

  it('skips ineligible buyers based on eligibility rules', function () {
    $lead = Lead::factory()->create(['fingerprint' => 'fp-bestbid-ineligible']);

    $buyer = Integration::factory()->pingPost()->withBuyerConfig()->create();
    $buyer->eligibilityRules()->create(['field' => 'state', 'operator' => 'eq', 'value' => 'CA', 'sort_order' => 0]);

    $workflow = Workflow::factory()->bestBid()->create();
    attachBuyers($workflow, [['integration' => $buyer, 'position' => 0]]);

    Http::fake(); // no HTTP should be called

    // Lead has no field responses, so 'state' is not present → eq 'CA' fails → buyer skipped
    $dispatch = app(DispatchOrchestrator::class)->dispatch($workflow, $lead, $lead->fingerprint);

    expect($dispatch->status)->toBe(DispatchStatus::NOT_SOLD);
    Http::assertNothingSent();
  });

  it('records ping result as error when buyer responds with 5xx and marks dispatch not_sold', function () {
    $lead = Lead::factory()->create(['fingerprint' => 'fp-bestbid-error']);

    $buyer = Integration::factory()->pingPost()->withBuyerConfig()->create();
    $workflow = Workflow::factory()->bestBid()->create();
    attachBuyers($workflow, [['integration' => $buyer, 'position' => 0]]);

    Http::fake([
      'https://buyer.example.com/ping' => Http::response(['error' => 'server unavailable'], 500),
    ]);

    $dispatch = app(DispatchOrchestrator::class)->dispatch($workflow, $lead, $lead->fingerprint);

    expect($dispatch->status)->toBe(DispatchStatus::NOT_SOLD);
    $this->assertDatabaseHas('ping_results', ['integration_id' => $buyer->id, 'status' => 'error']);
  });

  it('prevents duplicate pings via idempotency key', function () {
    $lead = Lead::factory()->create(['fingerprint' => 'fp-dedup']);

    $buyer = Integration::factory()->pingPost()->withBuyerConfig()->create();
    $workflow = Workflow::factory()->bestBid()->create();
    attachBuyers($workflow, [['integration' => $buyer, 'position' => 0]]);

    Http::fake([
      'https://buyer.example.com/ping' => Http::response(fakePingAccepted(10.0)),
      'https://buyer.example.com/post' => Http::response(fakePostAccepted()),
    ]);

    // First dispatch
    app(DispatchOrchestrator::class)->dispatch($workflow, $lead, $lead->fingerprint);

    // Second dispatch — same fingerprint+integration, buyer should be deduped (no new ping)
    Http::fake(); // reset — no calls should go through for the same key
    $dispatch2 = app(DispatchOrchestrator::class)->dispatch($workflow, $lead, $lead->fingerprint);

    // The second dispatch has no eligible buyers (idempotency), ends not_sold
    expect($dispatch2->status)->toBe(DispatchStatus::NOT_SOLD);
  });
});

// ─── Waterfall ───────────────────────────────────────────────────────────────

describe('Waterfall strategy', function () {
  it('posts to first buyer that accepts', function () {
    $lead = Lead::factory()->create(['fingerprint' => 'fp-wf-1']);

    $buyer1 = Integration::factory()
      ->postOnly()
      ->withBuyerConfig(['price_source' => 'fixed', 'fixed_price' => 8.0])
      ->create();
    $buyer2 = Integration::factory()
      ->postOnly()
      ->withBuyerConfig(['price_source' => 'fixed', 'fixed_price' => 6.0])
      ->create();

    $workflow = Workflow::factory()
      ->waterfall()
      ->create([
        'advance_on_rejection' => true,
      ]);
    attachBuyers($workflow, [['integration' => $buyer1, 'position' => 0], ['integration' => $buyer2, 'position' => 1]]);

    Http::fake([
      'https://buyer.example.com/post' => Http::sequence()
        ->push(fakePostRejected()) // buyer1 rejects
        ->push(fakePostAccepted()), // buyer2 accepts
    ]);

    $dispatch = app(DispatchOrchestrator::class)->dispatch($workflow, $lead, $lead->fingerprint);

    expect($dispatch->status)->toBe(DispatchStatus::SOLD);
    expect($dispatch->winner_integration_id)->toBe($buyer2->id);
    $this->assertDatabaseHas('post_results', ['integration_id' => $buyer1->id, 'status' => 'rejected']);
    $this->assertDatabaseHas('post_results', ['integration_id' => $buyer2->id, 'status' => 'accepted']);
  });

  it('stops advancing when advance_on_rejection is false', function () {
    $lead = Lead::factory()->create(['fingerprint' => 'fp-wf-noadv']);

    $buyer1 = Integration::factory()->postOnly()->withBuyerConfig()->create();
    $buyer2 = Integration::factory()->postOnly()->withBuyerConfig()->create();

    $workflow = Workflow::factory()
      ->waterfall()
      ->create(['advance_on_rejection' => false]);
    attachBuyers($workflow, [['integration' => $buyer1, 'position' => 0], ['integration' => $buyer2, 'position' => 1]]);

    Http::fake([
      'https://buyer.example.com/post' => Http::response(fakePostRejected()),
    ]);

    $dispatch = app(DispatchOrchestrator::class)->dispatch($workflow, $lead, $lead->fingerprint);

    expect($dispatch->status)->toBe(DispatchStatus::NOT_SOLD);
    $this->assertDatabaseCount('post_results', 1); // buyer2 never tried
  });

  it('handles ping-post buyer in waterfall: advances on ping rejection', function () {
    $lead = Lead::factory()->create(['fingerprint' => 'fp-wf-pingpost']);

    $buyer1 = Integration::factory()->pingPost()->withBuyerConfig()->create();
    $buyer2 = Integration::factory()
      ->postOnly()
      ->withBuyerConfig(['price_source' => 'fixed', 'fixed_price' => 5.0])
      ->create();

    $workflow = Workflow::factory()
      ->waterfall()
      ->create(['advance_on_rejection' => true]);
    attachBuyers($workflow, [['integration' => $buyer1, 'position' => 0], ['integration' => $buyer2, 'position' => 1]]);

    Http::fake([
      'https://buyer.example.com/ping' => Http::response(fakePingRejected()),
      'https://buyer.example.com/post' => Http::response(fakePostAccepted()),
    ]);

    $dispatch = app(DispatchOrchestrator::class)->dispatch($workflow, $lead, $lead->fingerprint);

    expect($dispatch->status)->toBe(DispatchStatus::SOLD);
    expect($dispatch->winner_integration_id)->toBe($buyer2->id);
  });
});

// ─── Combined ────────────────────────────────────────────────────────────────

describe('Combined strategy', function () {
  it('sells via primary best-bid group without activating secondary', function () {
    $lead = Lead::factory()->create(['fingerprint' => 'fp-comb-primary']);

    $primary = Integration::factory()->pingPost()->withBuyerConfig()->create();
    $secondary = Integration::factory()->postOnly()->withBuyerConfig()->create();

    $workflow = Workflow::factory()->combined()->create();
    attachBuyers($workflow, [
      ['integration' => $primary, 'position' => 0, 'group' => 'primary'],
      ['integration' => $secondary, 'position' => 0, 'group' => 'secondary'],
    ]);

    Http::fake([
      'https://buyer.example.com/ping' => Http::response(fakePingAccepted(10.0)),
      'https://buyer.example.com/post' => Http::response(fakePostAccepted()),
    ]);

    $dispatch = app(DispatchOrchestrator::class)->dispatch($workflow, $lead, $lead->fingerprint);

    expect($dispatch->status)->toBe(DispatchStatus::SOLD);
    expect($dispatch->fallback_activated)->toBeFalse();
    $this->assertDatabaseMissing('post_results', ['integration_id' => $secondary->id]);
  });

  it('activates secondary waterfall when primary best-bid fails', function () {
    $lead = Lead::factory()->create(['fingerprint' => 'fp-comb-fallback']);

    $primary = Integration::factory()->pingPost()->withBuyerConfig()->create();
    $secondary = Integration::factory()
      ->postOnly()
      ->withBuyerConfig(['price_source' => 'fixed', 'fixed_price' => 5.0])
      ->create();

    $workflow = Workflow::factory()
      ->combined()
      ->create(['cascade_on_post_rejection' => true, 'cascade_max_retries' => 1]);
    attachBuyers($workflow, [
      ['integration' => $primary, 'position' => 0, 'group' => 'primary'],
      ['integration' => $secondary, 'position' => 0, 'group' => 'secondary'],
    ]);

    Http::fake([
      'https://buyer.example.com/ping' => Http::response(fakePingAccepted(10.0)),
      'https://buyer.example.com/post' => Http::sequence()
        ->push(fakePostRejected()) // primary post rejected
        ->push(fakePostAccepted()), // secondary accepts
    ]);

    $dispatch = app(DispatchOrchestrator::class)->dispatch($workflow, $lead, $lead->fingerprint);

    expect($dispatch->status)->toBe(DispatchStatus::SOLD);
    expect($dispatch->fallback_activated)->toBeTrue();
    expect($dispatch->winner_integration_id)->toBe($secondary->id);
  });
});

// ─── Async postback ──────────────────────────────────────────────────────────

describe('Async postback pricing', function () {
  it('creates a pending_postback post result and does not mark dispatch as sold', function () {
    $lead = Lead::factory()->create(['fingerprint' => 'fp-postback']);

    $buyer = Integration::factory()
      ->postOnly()
      ->withBuyerConfig([
        'price_source' => 'postback',
        'postback_pending_days' => 15,
      ])
      ->create();

    $workflow = Workflow::factory()->waterfall()->create();
    attachBuyers($workflow, [['integration' => $buyer, 'position' => 0]]);

    Http::fake([
      'https://buyer.example.com/post' => Http::response(['accepted' => 'true', 'status' => 'received']),
    ]);

    $dispatch = app(DispatchOrchestrator::class)->dispatch($workflow, $lead, $lead->fingerprint);

    // Post was actually sent to the buyer (HTTP request recorded)
    Http::assertSent(fn ($request) => str_contains($request->url(), 'buyer.example.com/post'));

    // PostResult is pending_postback (buyer accepted but price comes later)
    $this->assertDatabaseHas('post_results', [
      'integration_id' => $buyer->id,
      'status' => 'pending_postback',
    ]);

    // Dispatch stays in RUNNING — not sold yet, not marked as not_sold
    expect($dispatch->status)->toBe(DispatchStatus::RUNNING);
  });
});

// ─── Error Excludes ─────────────────────────────────────────────────────────

describe('Error Excludes', function () {
  it('treats excluded ping error as rejected instead of error', function () {
    $lead = Lead::factory()->create(['fingerprint' => 'fp-excluded-ping']);

    $buyer = Integration::factory()
      ->pingPost()
      ->withBuyerConfig(['price_source' => 'response_bid', 'min_bid' => 0])
      ->create(['name' => 'Buyer Exclude']);

    // Configure error detection with excludes on the ping environment
    $pingEnv = $buyer->environments->where('env_type', 'ping')->where('environment', 'production')->first();

    $pingEnv->pingResponseConfig->update([
      'error_path' => 'outcome',
      'error_value' => 'failure',
      'error_reason_path' => 'reason',
      'error_excludes' => ['duplicate', 'cap reached'],
    ]);

    $workflow = Workflow::factory()->bestBid()->create();
    attachBuyers($workflow, [['integration' => $buyer, 'position' => 0]]);

    Http::fake([
      'https://buyer.example.com/ping' => Http::response([
        'outcome' => 'failure',
        'reason' => 'Lead is a duplicate',
      ]),
    ]);

    $dispatch = app(DispatchOrchestrator::class)->dispatch($workflow, $lead, $lead->fingerprint);

    expect($dispatch->status)->toBe(DispatchStatus::NOT_SOLD);
    $this->assertDatabaseHas('ping_results', [
      'integration_id' => $buyer->id,
      'status' => 'rejected',
    ]);
    $this->assertDatabaseMissing('ping_results', [
      'integration_id' => $buyer->id,
      'status' => 'error',
    ]);
  });

  it('treats excluded post error as rejected instead of error', function () {
    $lead = Lead::factory()->create(['fingerprint' => 'fp-excluded-post']);

    $buyer = Integration::factory()
      ->postOnly()
      ->withBuyerConfig(['price_source' => 'fixed', 'fixed_price' => 10.0])
      ->create(['name' => 'Buyer Post Exclude']);

    // Configure error detection with excludes on the post environment
    $postEnv = $buyer->environments->where('env_type', 'post')->where('environment', 'production')->first();

    $postEnv->postResponseConfig->update([
      'error_path' => 'status',
      'error_value' => 'Error',
      'error_reason_path' => 'message',
      'error_excludes' => ['state not accepted'],
    ]);

    $workflow = Workflow::factory()->waterfall()->create();
    attachBuyers($workflow, [['integration' => $buyer, 'position' => 0]]);

    Http::fake([
      'https://buyer.example.com/post' => Http::response([
        'status' => 'Error',
        'message' => 'State not accepted for this buyer',
      ]),
    ]);

    $dispatch = app(DispatchOrchestrator::class)->dispatch($workflow, $lead, $lead->fingerprint);

    expect($dispatch->status)->toBe(DispatchStatus::NOT_SOLD);
    $this->assertDatabaseHas('post_results', [
      'integration_id' => $buyer->id,
      'status' => 'rejected',
    ]);
    $this->assertDatabaseMissing('post_results', [
      'integration_id' => $buyer->id,
      'status' => 'error',
    ]);
  });

  it('still triggers error when configured error does not match any exclude', function () {
    $lead = Lead::factory()->create(['fingerprint' => 'fp-nonexcluded-ping']);

    $buyer = Integration::factory()
      ->pingPost()
      ->withBuyerConfig(['price_source' => 'response_bid', 'min_bid' => 0])
      ->create(['name' => 'Buyer NonExclude']);

    $pingEnv = $buyer->environments->where('env_type', 'ping')->where('environment', 'production')->first();

    $pingEnv->pingResponseConfig->update([
      'error_path' => 'outcome',
      'error_value' => 'failure',
      'error_reason_path' => 'reason',
      'error_excludes' => ['duplicate'],
    ]);

    $workflow = Workflow::factory()->bestBid()->create();
    attachBuyers($workflow, [['integration' => $buyer, 'position' => 0]]);

    Http::fake([
      'https://buyer.example.com/ping' => Http::response([
        'outcome' => 'failure',
        'reason' => 'Internal server error on buyer side',
      ]),
    ]);

    $dispatch = app(DispatchOrchestrator::class)->dispatch($workflow, $lead, $lead->fingerprint);

    expect($dispatch->status)->toBe(DispatchStatus::NOT_SOLD);
    $this->assertDatabaseHas('ping_results', [
      'integration_id' => $buyer->id,
      'status' => 'error',
    ]);
  });
});
