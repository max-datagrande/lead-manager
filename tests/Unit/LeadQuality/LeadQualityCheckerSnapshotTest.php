<?php

use App\Enums\LeadQuality\RuleStatus;
use App\Enums\LeadQuality\ValidationLogStatus;
use App\Models\Integration;
use App\Models\Lead;
use App\Models\LeadQualityValidationLog;
use App\Models\LeadQualityValidationRule;
use App\Services\LeadQuality\LeadQualityCheckerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(Tests\TestCase::class, RefreshDatabase::class);

$checker = fn() => app(LeadQualityCheckerService::class);

it('prefetch answers isEligibleForQuality without additional queries per buyer', function () use ($checker) {
  $lead = Lead::factory()->create(['fingerprint' => 'fp-prefetch']);
  $buyers = Integration::factory()->count(5)->create();

  $rule = LeadQualityValidationRule::factory()->create([
    'status' => RuleStatus::ACTIVE,
    'is_enabled' => true,
    'settings' => ['validity_window' => 15],
  ]);

  // Attach the rule to all 5 buyers and create a single verified log covering all of them.
  foreach ($buyers as $buyer) {
    $buyer->validationRules()->attach($rule->id, ['is_enabled' => true]);
  }
  LeadQualityValidationLog::factory()->create([
    'validation_rule_id' => $rule->id,
    'lead_id' => $lead->id,
    'fingerprint' => $lead->fingerprint,
    'status' => ValidationLogStatus::VERIFIED,
    'result' => 'pass',
    'resolved_at' => now()->subMinutes(2),
  ]);

  $snapshot = $checker()->prefetchForBuyers($buyers, $lead);

  DB::enableQueryLog();
  foreach ($buyers as $buyer) {
    expect($checker()->isEligibleForQuality($buyer, $lead, [], $snapshot))->toBeTrue();
  }
  $queriesDuringCheck = DB::getQueryLog();
  DB::disableQueryLog();

  // Zero queries issued during the per-buyer check once the snapshot is built.
  expect($queriesDuringCheck)->toHaveCount(0);
});

it('prefetch produces a bounded query count regardless of buyer count', function () use ($checker) {
  $lead = Lead::factory()->create(['fingerprint' => 'fp-prefetch-bounded']);
  $buyers = Integration::factory()->count(10)->create();
  $rule = LeadQualityValidationRule::factory()->create([
    'status' => RuleStatus::ACTIVE,
    'is_enabled' => true,
    'settings' => ['validity_window' => 15],
  ]);
  foreach ($buyers as $buyer) {
    $buyer->validationRules()->attach($rule->id, ['is_enabled' => true]);
  }

  DB::enableQueryLog();
  $snapshot = $checker()->prefetchForBuyers($buyers, $lead);
  $queries = DB::getQueryLog();
  DB::disableQueryLog();

  // Rules + their eager-loaded buyers + verified logs + latest logs. Bounded, and
  // critically: it does not scale with buyer count.
  expect(count($queries))->toBeLessThanOrEqual(5);
  expect($snapshot->rulesFor($buyers->first()->id))->not->toBeEmpty();
});

it('prefetch reports the same skip reason as per-buyer mode for a rule with expired log', function () use ($checker) {
  $lead = Lead::factory()->create(['fingerprint' => 'fp-prefetch-expired']);
  $buyer = Integration::factory()->create();
  $rule = LeadQualityValidationRule::factory()->create([
    'status' => RuleStatus::ACTIVE,
    'is_enabled' => true,
    'settings' => ['validity_window' => 15],
  ]);
  $buyer->validationRules()->attach($rule->id, ['is_enabled' => true]);

  LeadQualityValidationLog::factory()->create([
    'validation_rule_id' => $rule->id,
    'lead_id' => $lead->id,
    'fingerprint' => $lead->fingerprint,
    'status' => ValidationLogStatus::EXPIRED,
    'result' => 'fail',
    'expires_at' => now()->subMinutes(5),
  ]);

  $snapshot = $checker()->prefetchForBuyers([$buyer], $lead);

  expect($checker()->isEligibleForQuality($buyer, $lead, [], $snapshot))->toBeFalse();
  expect($checker()->getSkipReason($buyer, $lead, [], $snapshot))->toContain('last attempt expired');
});
