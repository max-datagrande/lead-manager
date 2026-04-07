<?php

use App\Models\BuyerEligibilityRule;
use App\Models\Integration;
use App\Services\PingPost\EligibilityCheckerService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

$checker = fn() => app(EligibilityCheckerService::class);

// ─── No rules ────────────────────────────────────────────────────────────────

it('returns true when integration has no eligibility rules', function () use ($checker) {
  $integration = Integration::factory()->pingPost()->create();

  expect($checker()->isEligible($integration, ['state' => 'CA']))->toBeTrue();
  expect($checker()->getSkipReason($integration, ['state' => 'CA']))->toBeNull();
});

// ─── Operators ───────────────────────────────────────────────────────────────

dataset('operator_pass', [
  'eq  match' => ['eq', 'CA', 'CA', true],
  'neq mismatch' => ['neq', 'CA', 'TX', true],
  'gt  pass' => ['gt', 25, 30, true],
  'gte equal' => ['gte', 25, 25, true],
  'lt  pass' => ['lt', 30, 25, true],
  'lte equal' => ['lte', 25, 25, true],
  'in  found' => ['in', ['CA', 'TX'], 'CA', true],
  'not_in miss' => ['not_in', ['NY', 'FL'], 'CA', true],
  'eq  fail' => ['eq', 'CA', 'TX', false],
  'neq match' => ['neq', 'CA', 'CA', false],
  'gt  fail' => ['gt', 30, 25, false],
  'gte fail' => ['gte', 30, 25, false],
  'lt  fail' => ['lt', 25, 30, false],
  'lte fail' => ['lte', 25, 30, false],
  'in  missing' => ['in', ['CA', 'TX'], 'FL', false],
  'not_in found' => ['not_in', ['CA', 'TX'], 'CA', false],
]);

it('evaluates operator correctly', function (string $op, mixed $ruleValue, mixed $leadValue, bool $expected) {
  $integration = Integration::factory()->pingPost()->create();

  BuyerEligibilityRule::create([
    'integration_id' => $integration->id,
    'field' => 'state',
    'operator' => $op,
    'value' => $ruleValue,
    'sort_order' => 0,
  ]);

  $integration->load('eligibilityRules');

  $checker = app(EligibilityCheckerService::class);

  expect($checker->isEligible($integration, ['state' => $leadValue]))->toBe($expected);
})->with('operator_pass');

// ─── AND logic ───────────────────────────────────────────────────────────────

it('returns false when any rule fails (AND logic)', function () {
  $integration = Integration::factory()->pingPost()->create();

  BuyerEligibilityRule::create([
    'integration_id' => $integration->id,
    'field' => 'state',
    'operator' => 'in',
    'value' => ['CA', 'TX'],
    'sort_order' => 0,
  ]);
  BuyerEligibilityRule::create(['integration_id' => $integration->id, 'field' => 'age', 'operator' => 'gte', 'value' => 18, 'sort_order' => 1]);

  $integration->load('eligibilityRules');
  $checker = app(EligibilityCheckerService::class);

  // state OK, age fails
  expect($checker->isEligible($integration, ['state' => 'CA', 'age' => 16]))->toBeFalse();
});

it('returns true when all rules pass', function () {
  $integration = Integration::factory()->pingPost()->create();

  BuyerEligibilityRule::create([
    'integration_id' => $integration->id,
    'field' => 'state',
    'operator' => 'in',
    'value' => ['CA', 'TX'],
    'sort_order' => 0,
  ]);
  BuyerEligibilityRule::create(['integration_id' => $integration->id, 'field' => 'age', 'operator' => 'gte', 'value' => 18, 'sort_order' => 1]);

  $integration->load('eligibilityRules');
  $checker = app(EligibilityCheckerService::class);

  expect($checker->isEligible($integration, ['state' => 'CA', 'age' => 25]))->toBeTrue();
});

// ─── getSkipReason ───────────────────────────────────────────────────────────

it('returns skip reason for the first failing rule', function () {
  $integration = Integration::factory()->pingPost()->create();

  BuyerEligibilityRule::create(['integration_id' => $integration->id, 'field' => 'state', 'operator' => 'eq', 'value' => 'CA', 'sort_order' => 0]);

  $integration->load('eligibilityRules');
  $reason = app(EligibilityCheckerService::class)->getSkipReason($integration, ['state' => 'TX']);

  expect($reason)->toContain('state')->toContain('eq');
});

it('returns null skip reason when all rules pass', function () {
  $integration = Integration::factory()->pingPost()->create();

  BuyerEligibilityRule::create(['integration_id' => $integration->id, 'field' => 'state', 'operator' => 'eq', 'value' => 'CA', 'sort_order' => 0]);

  $integration->load('eligibilityRules');
  $reason = app(EligibilityCheckerService::class)->getSkipReason($integration, ['state' => 'CA']);

  expect($reason)->toBeNull();
});
