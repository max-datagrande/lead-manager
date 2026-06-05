<?php

use App\Enums\DispatchStatus;
use App\Models\Integration;
use App\Models\Lead;
use App\Models\PostResult;
use App\Models\Workflow;
use App\Models\WorkflowBuyer;
use App\Services\PingPost\DispatchOrchestrator;
use Illuminate\Support\Facades\Http;

// Regression for the best_bid fallback bug: activateFallback() used to POST
// directly with pingResult=null, so a ping-post fallback buyer never got pinged
// and the buyer rejected the post with "Lead_ID missing". The fix runs ping->post
// for ping-post fallback buyers (mirroring runWaterfall) and passes the PingResult
// to the post; post-only fallback buyers keep posting directly.
//
// A workflow whose only buyer is is_fallback=true has zero eligible primary
// buyers, so runBestBid goes straight to activateFallback.
//
// Local helpers (uniquely prefixed) so the file is self-contained and does not
// collide with the global helpers in DispatchOrchestratorTest.php.

function fbAttach(Workflow $workflow, array $buyers): void
{
  foreach ($buyers as $item) {
    WorkflowBuyer::create([
      'workflow_id' => $workflow->id,
      'integration_id' => $item['integration']->id,
      'position' => $item['position'],
      'buyer_group' => 'primary',
      'is_fallback' => $item['is_fallback'] ?? false,
      'is_active' => true,
    ]);
  }
}

function fbPingOk(float $bid = 10.0): array
{
  return ['accepted' => 'true', 'bid' => $bid];
}

function fbPingNo(): array
{
  return ['accepted' => 'false', 'bid' => 0];
}

function fbPostOk(): array
{
  return ['accepted' => 'true'];
}

it('pings before posting when the fallback buyer is ping-post', function () {
  $lead = Lead::factory()->create(['fingerprint' => 'fp-fb-pingpost']);

  $fallback = Integration::factory()
    ->pingPost()
    ->withBuyerConfig(['price_source' => 'response_bid', 'min_bid' => 0])
    ->create(['name' => 'DQ Fallback Ping Post']);

  $workflow = Workflow::factory()->bestBid()->create();
  fbAttach($workflow, [['integration' => $fallback, 'position' => 0, 'is_fallback' => true]]);

  Http::fake([
    'https://buyer.example.com/ping' => Http::response(fbPingOk(10.0)),
    'https://buyer.example.com/post' => Http::response(fbPostOk()),
  ]);

  $dispatch = app(DispatchOrchestrator::class)->dispatch($workflow, $lead, $lead->fingerprint);

  expect($dispatch->status)->toBe(DispatchStatus::SOLD);
  expect($dispatch->fallback_activated)->toBeTrue();
  expect($dispatch->winner_integration_id)->toBe($fallback->id);

  // The fallback ping-post buyer was pinged (the bug: it never was).
  $this->assertDatabaseHas('ping_results', ['integration_id' => $fallback->id, 'status' => 'accepted']);

  // And the post carried the PingResult — what supplies the buyer's Lead_ID.
  $postResult = PostResult::where('integration_id', $fallback->id)->first();
  expect($postResult)->not->toBeNull();
  expect($postResult->ping_result_id)->not->toBeNull();
  expect($postResult->status->value)->toBe('accepted');
});

it('posts directly without a ping when the fallback buyer is post-only', function () {
  $lead = Lead::factory()->create(['fingerprint' => 'fp-fb-postonly']);

  $fallback = Integration::factory()
    ->postOnly()
    ->withBuyerConfig(['price_source' => 'fixed', 'fixed_price' => 5.0])
    ->create(['name' => 'Post Only Fallback']);

  $workflow = Workflow::factory()->bestBid()->create();
  fbAttach($workflow, [['integration' => $fallback, 'position' => 0, 'is_fallback' => true]]);

  Http::fake([
    'https://buyer.example.com/post' => Http::response(fbPostOk()),
  ]);

  $dispatch = app(DispatchOrchestrator::class)->dispatch($workflow, $lead, $lead->fingerprint);

  expect($dispatch->status)->toBe(DispatchStatus::SOLD);
  expect($dispatch->fallback_activated)->toBeTrue();

  // post-only fallback: no ping happened (regression guard).
  $this->assertDatabaseMissing('ping_results', ['integration_id' => $fallback->id]);
  Http::assertNotSent(fn($req) => str_contains($req->url(), '/ping'));

  $postResult = PostResult::where('integration_id', $fallback->id)->first();
  expect($postResult->ping_result_id)->toBeNull();
});

it('advances to the next fallback buyer when a ping-post fallback ping is rejected', function () {
  $lead = Lead::factory()->create(['fingerprint' => 'fp-fb-advance']);

  $fb1 = Integration::factory()
    ->pingPost()
    ->withBuyerConfig(['price_source' => 'response_bid', 'min_bid' => 0])
    ->create(['name' => 'FB1']);
  $fb2 = Integration::factory()
    ->pingPost()
    ->withBuyerConfig(['price_source' => 'response_bid', 'min_bid' => 0])
    ->create(['name' => 'FB2']);

  $workflow = Workflow::factory()
    ->bestBid()
    ->create(['advance_on_rejection' => true]);
  fbAttach($workflow, [
    ['integration' => $fb1, 'position' => 0, 'is_fallback' => true],
    ['integration' => $fb2, 'position' => 1, 'is_fallback' => true],
  ]);

  Http::fake([
    'https://buyer.example.com/ping' => Http::sequence()
      ->push(fbPingNo()) // fb1 ping rejected -> advance
      ->push(fbPingOk(9.0)), // fb2 ping accepted
    'https://buyer.example.com/post' => Http::response(fbPostOk()),
  ]);

  $dispatch = app(DispatchOrchestrator::class)->dispatch($workflow, $lead, $lead->fingerprint);

  expect($dispatch->status)->toBe(DispatchStatus::SOLD);
  expect($dispatch->winner_integration_id)->toBe($fb2->id);
  $this->assertDatabaseHas('ping_results', ['integration_id' => $fb1->id, 'status' => 'rejected']);
  $this->assertDatabaseHas('ping_results', ['integration_id' => $fb2->id, 'status' => 'accepted']);
  $this->assertDatabaseMissing('post_results', ['integration_id' => $fb1->id]);
});

it('keeps the dispatch pending when a ping-post fallback returns pending_postback', function () {
  $lead = Lead::factory()->create(['fingerprint' => 'fp-fb-postback']);

  $fallback = Integration::factory()
    ->pingPost()
    ->withBuyerConfig(['price_source' => 'postback', 'postback_pending_days' => 15])
    ->create(['name' => 'Postback Fallback']);

  $workflow = Workflow::factory()->bestBid()->create();
  fbAttach($workflow, [['integration' => $fallback, 'position' => 0, 'is_fallback' => true]]);

  Http::fake([
    'https://buyer.example.com/ping' => Http::response(fbPingOk(0.0)),
    'https://buyer.example.com/post' => Http::response(['accepted' => 'true', 'status' => 'received']),
  ]);

  $dispatch = app(DispatchOrchestrator::class)->dispatch($workflow, $lead, $lead->fingerprint);

  // Treated as terminal (pending), NOT marked not_sold.
  expect($dispatch->status)->toBe(DispatchStatus::RUNNING);
  $this->assertDatabaseHas('post_results', ['integration_id' => $fallback->id, 'status' => 'pending_postback']);
  $this->assertDatabaseHas('ping_results', ['integration_id' => $fallback->id, 'status' => 'accepted']);
});
