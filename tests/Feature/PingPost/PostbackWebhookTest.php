<?php

use App\Enums\DispatchStatus;
use App\Enums\PostResultStatus;
use App\Models\Integration;
use App\Models\Lead;
use App\Models\LeadDispatch;
use App\Models\PostResult;
use App\Models\Workflow;

use function Pest\Laravel\postJson;

// ─── Helpers ─────────────────────────────────────────────────────────────────

function makePendingPostbackSetup(): array
{
  $workflow = Workflow::factory()->waterfall()->create();
  $lead = Lead::factory()->create(['fingerprint' => fake()->uuid()]);
  $integration = Integration::factory()
    ->postOnly()
    ->withBuyerConfig(['price_source' => 'postback'])
    ->create();

  $dispatch = LeadDispatch::create([
    'workflow_id' => $workflow->id,
    'lead_id' => $lead->id,
    'fingerprint' => $lead->fingerprint,
    'status' => DispatchStatus::RUNNING,
    'strategy_used' => 'waterfall',
  ]);

  $postResult = PostResult::create([
    'lead_dispatch_id' => $dispatch->id,
    'integration_id' => $integration->id,
    'status' => PostResultStatus::PENDING_POSTBACK,
    'price_offered' => null,
    'postback_expires_at' => now()->addDays(15),
  ]);

  return compact('dispatch', 'integration', 'postResult');
}

// ─── Tests ───────────────────────────────────────────────────────────────────

it('resolves a pending postback and marks dispatch as sold', function () {
  ['dispatch' => $dispatch, 'integration' => $integration] = makePendingPostbackSetup();

  postJson("/v1/ping-post/postback/{$dispatch->id}/{$integration->id}", ['price' => 15.5])
    ->assertOk()
    ->assertJsonPath('success', true)
    ->assertJsonPath('data.status', 'postback_resolved');

  $this->assertDatabaseHas('post_results', [
    'lead_dispatch_id' => $dispatch->id,
    'status' => 'postback_resolved',
    'price_final' => '15.5000',
  ]);

  $dispatch->refresh();
  expect($dispatch->status)->toBe(DispatchStatus::SOLD);
  expect((float) $dispatch->final_price)->toBe(15.5);
});

it('accepts price from "amount" param as fallback', function () {
  ['dispatch' => $dispatch, 'integration' => $integration] = makePendingPostbackSetup();

  postJson("/v1/ping-post/postback/{$dispatch->id}/{$integration->id}", ['amount' => 8.0])
    ->assertOk()
    ->assertJsonPath('data.status', 'postback_resolved');

  $this->assertDatabaseHas('post_results', ['price_final' => '8.0000']);
});

it('returns 404 when there is no pending postback for the dispatch+integration', function () {
  $workflow = Workflow::factory()->create();
  $lead = Lead::factory()->create();
  $integration = Integration::factory()->create();

  $dispatch = LeadDispatch::create([
    'workflow_id' => $workflow->id,
    'lead_id' => $lead->id,
    'fingerprint' => fake()->uuid(),
    'status' => DispatchStatus::SOLD,
    'strategy_used' => 'waterfall',
  ]);

  postJson("/v1/ping-post/postback/{$dispatch->id}/{$integration->id}", ['price' => 10.0])
    ->assertNotFound()
    ->assertJsonPath('success', false);
});

it('returns 404 when dispatch does not exist', function () {
  $integration = Integration::factory()->create();

  postJson("/v1/ping-post/postback/999999/{$integration->id}", ['price' => 10.0])->assertNotFound();
});

it('sets postback_received_at timestamp on resolution', function () {
  ['dispatch' => $dispatch, 'integration' => $integration, 'postResult' => $postResult] = makePendingPostbackSetup();

  postJson("/v1/ping-post/postback/{$dispatch->id}/{$integration->id}", ['price' => 5.0])->assertOk();

  $postResult->refresh();
  expect($postResult->postback_received_at)->not->toBeNull();
});
