<?php

use App\Enums\DispatchStatus;
use App\Enums\PostResultStatus;
use App\Events\LeadSold;
use App\Models\Integration;
use App\Models\Lead;
use App\Models\LeadDispatch;
use App\Models\Postback;
use App\Models\PostResult;
use App\Models\Workflow;
use Illuminate\Support\Facades\Event;

use function Pest\Laravel\getJson;

// ─── Helpers ─────────────────────────────────────────────────────────────────

function makePricingSetup(array $overrides = []): array
{
  $workflow = Workflow::factory()->waterfall()->create();
  $lead = Lead::factory()->create(['fingerprint' => 'fp-pricing-test']);

  $integration = Integration::factory()
    ->postOnly()
    ->withBuyerConfig(['price_source' => 'postback', 'postback_pending_days' => 15])
    ->create();

  $config = $integration->buyerConfig;

  $postback = Postback::factory()->create([
    'param_mappings' => ['click_id' => 'click_id', 'payout' => 'payout'],
  ]);

  // Link postback to buyer config via pivot
  $config->pricingPostback()->attach($postback->id, [
    'identifier_token' => $overrides['identifier_token'] ?? 'click_id',
    'price_token' => $overrides['price_token'] ?? 'payout',
  ]);

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

  return compact('workflow', 'lead', 'integration', 'config', 'postback', 'dispatch', 'postResult');
}

// ─── Tests ───────────────────────────────────────────────────────────────────

it('resolves a pending postback when partner fires with correct tokens', function () {
  ['postback' => $postback, 'dispatch' => $dispatch] = makePricingSetup();

  getJson("/v1/postback/fire/{$postback->uuid}?click_id=fp-pricing-test&payout=85.00")
    ->assertOk()
    ->assertJsonPath('success', true);

  $this->assertDatabaseHas('post_results', [
    'lead_dispatch_id' => $dispatch->id,
    'status' => 'postback_resolved',
    'price_final' => '85.0000',
  ]);

  $dispatch->refresh();
  expect($dispatch->status)->toBe(DispatchStatus::SOLD);
  expect((float) $dispatch->final_price)->toBe(85.0);
});

it('triggers LeadSold event on resolution', function () {
  Event::fake([LeadSold::class]);

  ['postback' => $postback] = makePricingSetup();

  getJson("/v1/postback/fire/{$postback->uuid}?click_id=fp-pricing-test&payout=50.00")
    ->assertOk();

  Event::assertDispatched(LeadSold::class);
});

it('creates PostbackExecution for audit trail', function () {
  ['postback' => $postback, 'postResult' => $postResult] = makePricingSetup();

  getJson("/v1/postback/fire/{$postback->uuid}?click_id=fp-pricing-test&payout=25.00")
    ->assertOk();

  $this->assertDatabaseHas('postback_executions', [
    'postback_id' => $postback->id,
    'status' => 'completed',
    'source_reference' => "pricing_resolve:post_result:{$postResult->id}",
  ]);
});

it('returns 422 when identifier token is missing from params', function () {
  ['postback' => $postback] = makePricingSetup();

  getJson("/v1/postback/fire/{$postback->uuid}?payout=85.00")
    ->assertStatus(422);
});

it('returns 422 when no pending postback matches the fingerprint', function () {
  ['postback' => $postback] = makePricingSetup();

  getJson("/v1/postback/fire/{$postback->uuid}?click_id=unknown-fingerprint&payout=85.00")
    ->assertStatus(422);
});

it('handles idempotent duplicate fires', function () {
  ['postback' => $postback] = makePricingSetup();

  $url = "/v1/postback/fire/{$postback->uuid}?click_id=fp-pricing-test&payout=85.00";

  getJson($url)->assertOk();
  getJson($url)->assertOk();

  $this->assertDatabaseCount('postback_executions', 1);
});

it('does not dispatch to external destination for pricing postbacks', function () {
  ['postback' => $postback] = makePricingSetup();

  getJson("/v1/postback/fire/{$postback->uuid}?click_id=fp-pricing-test&payout=85.00")
    ->assertOk();

  $execution = $postback->executions()->first();
  expect($execution->outbound_url)->toBeNull();
  expect($execution->status->value)->toBe('completed');
});

it('still fires normally for postbacks without buyer config links', function () {
  $postback = Postback::factory()->create();

  getJson("/v1/postback/fire/{$postback->uuid}?click_id=abc&payout=10")
    ->assertOk();

  $execution = $postback->executions()->first();
  expect($execution->outbound_url)->not->toBeNull();
});
