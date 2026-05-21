<?php

use App\Enums\DispatchStatus;
use App\Enums\PingResultStatus;
use App\Enums\PostResultStatus;
use App\Models\DispatchBuyerEvent;
use App\Models\Integration;
use App\Models\Lead;
use App\Models\LeadDispatch;
use App\Models\PingResult;
use App\Models\PostResult;
use App\Models\User;
use App\Models\Workflow;
use Inertia\Testing\AssertableInertia;

use function Pest\Laravel\actingAs;

/**
 * Seed: 1 dispatch with 3 buyers — buyerA filtered in pre_dispatch, buyerB rejected at ping,
 * buyerC accepted at ping + rejected at post. Total = 4 buyer-event rows for the index.
 */
function seedBuyerEventScenario(): array
{
  $workflow = Workflow::factory()->bestBid()->create();
  $lead = Lead::factory()->create(['fingerprint' => 'fp-be-' . uniqid()]);

  $dispatch = LeadDispatch::create([
    'workflow_id' => $workflow->id,
    'lead_id' => $lead->id,
    'fingerprint' => $lead->fingerprint,
    'status' => DispatchStatus::NOT_SOLD,
    'strategy_used' => $workflow->strategy?->value,
    'started_at' => now(),
  ]);

  $buyerA = Integration::factory()->create(['type' => 'ping-post']);
  $buyerB = Integration::factory()->create(['type' => 'ping-post']);
  $buyerC = Integration::factory()->create(['type' => 'ping-post']);

  DispatchBuyerEvent::create([
    'lead_dispatch_id' => $dispatch->id,
    'integration_id' => $buyerA->id,
    'event' => 'filtered',
    'reason' => 'cap_exceeded',
    'created_at' => now(),
  ]);

  PingResult::create([
    'lead_dispatch_id' => $dispatch->id,
    'integration_id' => $buyerB->id,
    'idempotency_key' => 'idem-' . uniqid(),
    'status' => PingResultStatus::REJECTED,
    'http_status_code' => 200,
    'duration_ms' => 120,
  ]);

  $pingC = PingResult::create([
    'lead_dispatch_id' => $dispatch->id,
    'integration_id' => $buyerC->id,
    'idempotency_key' => 'idem-' . uniqid(),
    'status' => PingResultStatus::ACCEPTED,
    'bid_price' => '25.5000',
    'http_status_code' => 200,
    'duration_ms' => 100,
  ]);

  PostResult::create([
    'lead_dispatch_id' => $dispatch->id,
    'ping_result_id' => $pingC->id,
    'integration_id' => $buyerC->id,
    'status' => PostResultStatus::REJECTED,
    'price_offered' => '25.5000',
    'rejection_reason' => 'bad_data',
    'http_status_code' => 200,
    'duration_ms' => 200,
  ]);

  return compact('dispatch', 'buyerA', 'buyerB', 'buyerC');
}

beforeEach(function () {
  $this->admin = User::factory()->create(['role' => 'admin']);
});

test('admin sees all 4 rows: 1 pre_dispatch + 2 ping + 1 post', function () {
  seedBuyerEventScenario();

  $response = actingAs($this->admin)->get(route('ping-post.buyer-events.index'));

  $response->assertSuccessful();
  $response->assertInertia(
    fn(AssertableInertia $page) => $page
      ->component('ping-post/buyer-events/index')
      ->has('rows.data', 4)
      ->has('meta')
      ->has('data.stageOptions', 3)
      ->has('data.eventTypeOptions')
      ->has('data.reasonOptions')
      ->has('data.workflows')
      ->has('data.integrations')
      ->has('data.companies'),
  );
});

test('filter stage=ping returns only ping_result rows', function () {
  seedBuyerEventScenario();

  $response = actingAs($this->admin)->get(
    route('ping-post.buyer-events.index', [
      'filters' => json_encode([['id' => 'stage', 'value' => ['ping']]]),
    ]),
  );

  $response->assertSuccessful();
  $response->assertInertia(fn(AssertableInertia $page) => $page->has('rows.data', 2)->where('rows.data.0.stage', 'ping'));
});

test('filter stage=post returns only post_result rows', function () {
  seedBuyerEventScenario();

  $response = actingAs($this->admin)->get(
    route('ping-post.buyer-events.index', [
      'filters' => json_encode([['id' => 'stage', 'value' => ['post']]]),
    ]),
  );

  $response->assertSuccessful();
  $response->assertInertia(fn(AssertableInertia $page) => $page->has('rows.data', 1)->where('rows.data.0.stage', 'post'));
});

test('filter stage=pre_dispatch returns only dispatch_buyer_event rows', function () {
  seedBuyerEventScenario();

  $response = actingAs($this->admin)->get(
    route('ping-post.buyer-events.index', [
      'filters' => json_encode([['id' => 'stage', 'value' => ['pre_dispatch']]]),
    ]),
  );

  $response->assertSuccessful();
  $response->assertInertia(
    fn(AssertableInertia $page) => $page
      ->has('rows.data', 1)
      ->where('rows.data.0.stage', 'pre_dispatch')
      ->where('rows.data.0.event_type', 'filtered'),
  );
});

test('filter event_type=rejected returns rejections across both ping and post stages', function () {
  seedBuyerEventScenario();

  $response = actingAs($this->admin)->get(
    route('ping-post.buyer-events.index', [
      'filters' => json_encode([['id' => 'event_type', 'value' => ['rejected']]]),
    ]),
  );

  $response->assertSuccessful();
  $response->assertInertia(fn(AssertableInertia $page) => $page->has('rows.data', 2));
});

test('filter integration_id scopes results to a single buyer across all stages', function () {
  $ctx = seedBuyerEventScenario();

  $response = actingAs($this->admin)->get(
    route('ping-post.buyer-events.index', [
      'filters' => json_encode([['id' => 'integration_id', 'value' => [(string) $ctx['buyerC']->id]]]),
    ]),
  );

  $response->assertSuccessful();
  $response->assertInertia(fn(AssertableInertia $page) => $page->has('rows.data', 2));
});

test('global search by dispatch_uuid returns matching rows', function () {
  $ctx = seedBuyerEventScenario();

  $response = actingAs($this->admin)->get(route('ping-post.buyer-events.index', ['search' => $ctx['dispatch']->dispatch_uuid]));

  $response->assertSuccessful();
  $response->assertInertia(fn(AssertableInertia $page) => $page->has('rows.data', 4));
});

test('export returns a CSV with one header row plus one row per filtered event', function () {
  seedBuyerEventScenario();

  $response = actingAs($this->admin)->get(
    route('ping-post.buyer-events.export', [
      'filters' => json_encode([['id' => 'stage', 'value' => ['ping']]]),
    ]),
  );

  $response->assertSuccessful();
  $response->assertHeader('content-type', 'text/csv; charset=UTF-8');

  $body = $response->streamedContent();
  $lines = array_values(array_filter(explode("\n", trim($body))));
  expect($lines)->toHaveCount(3); // header + 2 ping rows
  expect($lines[0])->toContain('Date');
  expect($lines[0])->toContain('Ping Bid');
  expect($lines[0])->toContain('Final Payout');
});

test('non-admin/manager users are forbidden', function () {
  $user = User::factory()->create(['role' => 'user']);

  actingAs($user)->get(route('ping-post.buyer-events.index'))->assertForbidden();
  actingAs($user)->get(route('ping-post.buyer-events.export'))->assertForbidden();
});

test('guest is redirected to login', function () {
  $this->get(route('ping-post.buyer-events.index'))->assertRedirect(route('login'));
});
