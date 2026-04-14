<?php

use App\Models\BuyerEligibilityRule;
use App\Models\Field;
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
  'is_empty with null' => ['is_empty', null, null, true],
  'is_empty with empty string' => ['is_empty', null, '', true],
  'is_empty with value' => ['is_empty', null, 'something', false],
  'is_not_empty with value' => ['is_not_empty', null, 'something', true],
  'is_not_empty with null' => ['is_not_empty', null, null, false],
  'is_not_empty with empty string' => ['is_not_empty', null, '', false],
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

// ─── is_empty / is_not_empty with missing field ─────────────────────────────

it('is_empty passes when field is missing from lead data', function () {
  $integration = Integration::factory()->pingPost()->create();

  BuyerEligibilityRule::create(['integration_id' => $integration->id, 'field' => 'injuries', 'operator' => 'is_empty', 'value' => null, 'sort_order' => 0]);

  $integration->load('eligibilityRules');
  $checker = app(EligibilityCheckerService::class);

  expect($checker->isEligible($integration, []))->toBeTrue();
});

it('is_not_empty fails when field is missing from lead data', function () {
  $integration = Integration::factory()->pingPost()->create();

  BuyerEligibilityRule::create(['integration_id' => $integration->id, 'field' => 'injuries', 'operator' => 'is_not_empty', 'value' => null, 'sort_order' => 0]);

  $integration->load('eligibilityRules');
  $checker = app(EligibilityCheckerService::class);

  expect($checker->isEligible($integration, []))->toBeFalse();
});

// ─── Array fields ───────────────────────────────────────────────────────────

it('is_not_empty passes for array field with values', function () {
  Field::factory()->create(['name' => 'injuries', 'is_array' => true]);
  $integration = Integration::factory()->pingPost()->create();

  BuyerEligibilityRule::create(['integration_id' => $integration->id, 'field' => 'injuries', 'operator' => 'is_not_empty', 'value' => null, 'sort_order' => 0]);

  $integration->load('eligibilityRules');
  $checker = app(EligibilityCheckerService::class);

  expect($checker->isEligible($integration, ['injuries' => 'back pain;neck pain']))->toBeTrue();
});

it('is_not_empty fails for array field with empty string', function () {
  Field::factory()->create(['name' => 'injuries', 'is_array' => true]);
  $integration = Integration::factory()->pingPost()->create();

  BuyerEligibilityRule::create(['integration_id' => $integration->id, 'field' => 'injuries', 'operator' => 'is_not_empty', 'value' => null, 'sort_order' => 0]);

  $integration->load('eligibilityRules');
  $checker = app(EligibilityCheckerService::class);

  expect($checker->isEligible($integration, ['injuries' => '']))->toBeFalse();
});

it('is_empty passes for array field with empty string', function () {
  Field::factory()->create(['name' => 'injuries', 'is_array' => true]);
  $integration = Integration::factory()->pingPost()->create();

  BuyerEligibilityRule::create(['integration_id' => $integration->id, 'field' => 'injuries', 'operator' => 'is_empty', 'value' => null, 'sort_order' => 0]);

  $integration->load('eligibilityRules');
  $checker = app(EligibilityCheckerService::class);

  expect($checker->isEligible($integration, ['injuries' => '']))->toBeTrue();
});

it('eq checks if any array element matches', function () {
  Field::factory()->create(['name' => 'injuries', 'is_array' => true]);
  $integration = Integration::factory()->pingPost()->create();

  BuyerEligibilityRule::create(['integration_id' => $integration->id, 'field' => 'injuries', 'operator' => 'eq', 'value' => 'back pain', 'sort_order' => 0]);

  $integration->load('eligibilityRules');
  $checker = app(EligibilityCheckerService::class);

  expect($checker->isEligible($integration, ['injuries' => 'back pain;neck pain']))->toBeTrue();
  expect($checker->isEligible($integration, ['injuries' => 'headache;neck pain']))->toBeFalse();
});

it('in checks if any array element is in the rule values', function () {
  Field::factory()->create(['name' => 'injuries', 'is_array' => true]);
  $integration = Integration::factory()->pingPost()->create();

  BuyerEligibilityRule::create(['integration_id' => $integration->id, 'field' => 'injuries', 'operator' => 'in', 'value' => ['back pain', 'knee pain'], 'sort_order' => 0]);

  $integration->load('eligibilityRules');
  $checker = app(EligibilityCheckerService::class);

  expect($checker->isEligible($integration, ['injuries' => 'back pain;neck pain']))->toBeTrue();
  expect($checker->isEligible($integration, ['injuries' => 'headache;neck pain']))->toBeFalse();
});

it('not_in checks that no array element is in the rule values', function () {
  Field::factory()->create(['name' => 'injuries', 'is_array' => true]);
  $integration = Integration::factory()->pingPost()->create();

  BuyerEligibilityRule::create(['integration_id' => $integration->id, 'field' => 'injuries', 'operator' => 'not_in', 'value' => ['back pain', 'knee pain'], 'sort_order' => 0]);

  $integration->load('eligibilityRules');
  $checker = app(EligibilityCheckerService::class);

  expect($checker->isEligible($integration, ['injuries' => 'headache;neck pain']))->toBeTrue();
  expect($checker->isEligible($integration, ['injuries' => 'back pain;neck pain']))->toBeFalse();
});

it('does not split non-array fields by semicolon', function () {
  Field::factory()->create(['name' => 'comments', 'is_array' => false]);
  $integration = Integration::factory()->pingPost()->create();

  BuyerEligibilityRule::create(['integration_id' => $integration->id, 'field' => 'comments', 'operator' => 'is_not_empty', 'value' => null, 'sort_order' => 0]);

  $integration->load('eligibilityRules');
  $checker = app(EligibilityCheckerService::class);

  // "a;b" should stay as a plain string, not be split
  expect($checker->isEligible($integration, ['comments' => 'hello; world']))->toBeTrue();
});
