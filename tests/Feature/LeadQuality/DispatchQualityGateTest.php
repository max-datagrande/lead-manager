<?php

use App\Enums\DispatchStatus;
use App\Enums\LeadQuality\RuleStatus;
use App\Enums\LeadQuality\ValidationLogStatus;
use App\Models\Integration;
use App\Models\Lead;
use App\Models\LeadDispatch;
use App\Models\LeadQualityProvider;
use App\Models\LeadQualityValidationLog;
use App\Models\LeadQualityValidationRule;
use App\Models\Workflow;
use App\Models\WorkflowBuyer;
use App\Services\PingPost\DispatchOrchestrator;
use Illuminate\Support\Facades\Http;

function buildDispatchScenario(): array
{
  $provider = LeadQualityProvider::factory()->active()->create();
  $buyer = Integration::factory()->pingPost()->withBuyerConfig()->create();
  $workflow = Workflow::factory()->bestBid()->create();
  WorkflowBuyer::create([
    'workflow_id' => $workflow->id,
    'integration_id' => $buyer->id,
    'position' => 0,
    'buyer_group' => 'primary',
    'is_active' => true,
  ]);
  $lead = Lead::factory()->create(['fingerprint' => 'fp-gate-' . uniqid()]);

  return compact('provider', 'buyer', 'workflow', 'lead');
}

function attachRule(array $s, array $ruleOverrides = []): LeadQualityValidationRule
{
  $rule = LeadQualityValidationRule::factory()
    ->forProvider($s['provider'])
    ->create(
      array_merge(
        [
          'status' => RuleStatus::ACTIVE,
          'is_enabled' => true,
          'settings' => ['validity_window' => 15, 'max_attempts' => 3, 'ttl' => 600],
        ],
        $ruleOverrides,
      ),
    );
  $s['buyer']->validationRules()->attach($rule->id, ['is_enabled' => true]);

  return $rule;
}

it('safety net filters the buyer when no verified log exists for the rule', function () {
  $s = buildDispatchScenario();
  attachRule($s);

  Http::fake([
    'https://buyer.example.com/*' => Http::response(['accepted' => 'true']),
  ]);

  $dispatch = app(DispatchOrchestrator::class)->dispatch($s['workflow'], $s['lead'], $s['lead']->fingerprint);

  expect($dispatch->status)->toBe(DispatchStatus::NOT_SOLD);
  Http::assertNothingSent(); // buyer never reached ping/post because it was filtered
});

it('safety net lets the buyer through when a verified log is within the window', function () {
  $s = buildDispatchScenario();
  $rule = attachRule($s);

  LeadQualityValidationLog::factory()->create([
    'validation_rule_id' => $rule->id,
    'provider_id' => $s['provider']->id,
    'integration_id' => $s['buyer']->id,
    'lead_id' => $s['lead']->id,
    'fingerprint' => $s['lead']->fingerprint,
    'status' => ValidationLogStatus::VERIFIED,
    'result' => 'pass',
    'resolved_at' => now()->subMinutes(2),
  ]);

  Http::fake([
    'https://buyer.example.com/ping' => Http::response(['accepted' => 'true', 'bid' => 10.0]),
    'https://buyer.example.com/post' => Http::response(['accepted' => 'true']),
  ]);

  $dispatch = app(DispatchOrchestrator::class)->dispatch($s['workflow'], $s['lead'], $s['lead']->fingerprint);

  expect($dispatch->status)->toBe(DispatchStatus::SOLD);
});

it('reuses an existing PENDING_VALIDATION dispatch instead of creating a new one', function () {
  $s = buildDispatchScenario();
  $rule = attachRule($s);

  // Pre-existing dispatch as the challenge flow would leave it
  $existing = LeadDispatch::create([
    'workflow_id' => $s['workflow']->id,
    'lead_id' => $s['lead']->id,
    'fingerprint' => $s['lead']->fingerprint,
    'status' => DispatchStatus::PENDING_VALIDATION,
    'strategy_used' => $s['workflow']->strategy?->value,
    'started_at' => now(),
  ]);

  // Simulate a verified log freshly inserted by ChallengeVerifierService
  LeadQualityValidationLog::factory()->create([
    'validation_rule_id' => $rule->id,
    'provider_id' => $s['provider']->id,
    'integration_id' => $s['buyer']->id,
    'lead_id' => $s['lead']->id,
    'lead_dispatch_id' => $existing->id,
    'fingerprint' => $s['lead']->fingerprint,
    'status' => ValidationLogStatus::VERIFIED,
    'result' => 'pass',
    'resolved_at' => now(),
  ]);

  Http::fake([
    'https://buyer.example.com/ping' => Http::response(['accepted' => 'true', 'bid' => 10.0]),
    'https://buyer.example.com/post' => Http::response(['accepted' => 'true']),
  ]);

  $dispatch = app(DispatchOrchestrator::class)->dispatch($s['workflow'], $s['lead'], $s['lead']->fingerprint, existingDispatch: $existing);

  expect($dispatch->id)->toBe($existing->id); // same row — not a sibling
  expect($dispatch->status)->toBe(DispatchStatus::SOLD);
  expect(LeadDispatch::where('fingerprint', $s['lead']->fingerprint)->count())->toBe(1);
});
