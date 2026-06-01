<?php

use App\Enums\DispatchStatus;
use App\Models\Lead;
use App\Models\LeadDispatch;
use App\Models\TrafficLog;
use App\Models\User;
use App\Models\Workflow;
use Inertia\Testing\AssertableInertia;

use function Pest\Laravel\actingAs;

/**
 * Seed two dispatches with distinct fingerprints, each backed by a traffic_log
 * carrying a distinct s10 (tracking platform click id). Returns both dispatches.
 *
 * @return array{a: LeadDispatch, b: LeadDispatch}
 */
function seedS10Scenario(): array
{
  $workflow = Workflow::factory()->bestBid()->create();

  $leadA = Lead::factory()->create(['fingerprint' => 'fp-s10-a-' . uniqid()]);
  $leadB = Lead::factory()->create(['fingerprint' => 'fp-s10-b-' . uniqid()]);

  $a = LeadDispatch::create([
    'workflow_id' => $workflow->id,
    'lead_id' => $leadA->id,
    'fingerprint' => $leadA->fingerprint,
    'status' => DispatchStatus::SOLD,
    'strategy_used' => $workflow->strategy?->value,
    'started_at' => now(),
  ]);

  $b = LeadDispatch::create([
    'workflow_id' => $workflow->id,
    'lead_id' => $leadB->id,
    'fingerprint' => $leadB->fingerprint,
    'status' => DispatchStatus::NOT_SOLD,
    'strategy_used' => $workflow->strategy?->value,
    'started_at' => now(),
  ]);

  TrafficLog::factory()->create(['fingerprint' => $leadA->fingerprint, 's10' => 'CLICK-AAA-111']);
  TrafficLog::factory()->create(['fingerprint' => $leadB->fingerprint, 's10' => 'CLICK-BBB-222']);

  return ['a' => $a, 'b' => $b];
}

beforeEach(function () {
  $this->admin = User::factory()->create(['role' => 'admin']);
});

test('global search by s10 returns only the matching dispatch', function () {
  ['a' => $a] = seedS10Scenario();

  $response = actingAs($this->admin)->get(route('ping-post.dispatches.index', ['search' => 'CLICK-AAA-111']));

  $response->assertSuccessful();
  $response->assertInertia(
    fn(AssertableInertia $page) => $page->component('ping-post/dispatches/index')->has('rows.data', 1)->where('rows.data.0.id', $a->id),
  );
});

test('global search by an unknown s10 returns no dispatches', function () {
  seedS10Scenario();

  $response = actingAs($this->admin)->get(route('ping-post.dispatches.index', ['search' => 'CLICK-NOPE-999']));

  $response->assertSuccessful();
  $response->assertInertia(fn(AssertableInertia $page) => $page->has('rows.data', 0));
});

test('global search still matches by fingerprint', function () {
  ['b' => $b] = seedS10Scenario();

  $response = actingAs($this->admin)->get(route('ping-post.dispatches.index', ['search' => $b->fingerprint]));

  $response->assertSuccessful();
  $response->assertInertia(fn(AssertableInertia $page) => $page->has('rows.data', 1)->where('rows.data.0.id', $b->id));
});
