<?php

use App\Enums\LeadQuality\RuleStatus;
use App\Enums\LeadQuality\ValidationLogStatus;
use App\Models\Integration;
use App\Models\Lead;
use App\Models\LeadQualityValidationLog;
use App\Models\LeadQualityValidationRule;
use App\Services\LeadQuality\LeadQualityCheckerService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

$checker = fn() => app(LeadQualityCheckerService::class);

function buildStack(): array
{
  $buyer = Integration::factory()->create(['is_active' => true]);
  $lead = Lead::factory()->create(['fingerprint' => 'fp-checker-' . uniqid()]);
  $rule = LeadQualityValidationRule::factory()->create([
    'status' => RuleStatus::ACTIVE,
    'is_enabled' => true,
    'settings' => ['validity_window' => 15, 'max_attempts' => 3, 'ttl' => 600],
  ]);
  $buyer->validationRules()->attach($rule->id, ['is_enabled' => true]);

  return [$buyer, $lead, $rule];
}

it('returns true when the buyer has no validation rules', function () use ($checker) {
  $buyer = Integration::factory()->create();
  $lead = Lead::factory()->create();

  expect($checker()->isEligibleForQuality($buyer, $lead))->toBeTrue();
  expect($checker()->getSkipReason($buyer, $lead))->toBeNull();
});

it('returns false when rule exists but no validation log was ever recorded', function () use ($checker) {
  [$buyer, $lead] = buildStack();

  expect($checker()->isEligibleForQuality($buyer, $lead))->toBeFalse();
  expect($checker()->getSkipReason($buyer, $lead))->toContain('no validation attempt on record');
});

it('returns true when a verified log exists within the validity window', function () use ($checker) {
  [$buyer, $lead, $rule] = buildStack();

  LeadQualityValidationLog::factory()->create([
    'validation_rule_id' => $rule->id,
    'integration_id' => $buyer->id,
    'lead_id' => $lead->id,
    'fingerprint' => $lead->fingerprint,
    'status' => ValidationLogStatus::VERIFIED,
    'result' => 'pass',
    'resolved_at' => now()->subMinutes(5),
  ]);

  expect($checker()->isEligibleForQuality($buyer, $lead))->toBeTrue();
  expect($checker()->getSkipReason($buyer, $lead))->toBeNull();
});

it('returns false when the verified log is outside the validity window', function () use ($checker) {
  [$buyer, $lead, $rule] = buildStack();

  LeadQualityValidationLog::factory()->create([
    'validation_rule_id' => $rule->id,
    'integration_id' => $buyer->id,
    'lead_id' => $lead->id,
    'fingerprint' => $lead->fingerprint,
    'status' => ValidationLogStatus::VERIFIED,
    'result' => 'pass',
    'resolved_at' => now()->subMinutes(60), // window is 15
  ]);

  expect($checker()->isEligibleForQuality($buyer, $lead))->toBeFalse();
  expect($checker()->getSkipReason($buyer, $lead))->toContain('no verified attempt within validity window');
});

it('surfaces a dedicated reason when the latest log is expired', function () use ($checker) {
  [$buyer, $lead, $rule] = buildStack();

  LeadQualityValidationLog::factory()->create([
    'validation_rule_id' => $rule->id,
    'integration_id' => $buyer->id,
    'lead_id' => $lead->id,
    'fingerprint' => $lead->fingerprint,
    'status' => ValidationLogStatus::EXPIRED,
    'result' => 'fail',
    'expires_at' => now()->subMinutes(1),
  ]);

  expect($checker()->isEligibleForQuality($buyer, $lead))->toBeFalse();
  expect($checker()->getSkipReason($buyer, $lead))->toContain('last attempt expired');
});

it('surfaces a dedicated reason when the latest log is failed', function () use ($checker) {
  [$buyer, $lead, $rule] = buildStack();

  LeadQualityValidationLog::factory()->create([
    'validation_rule_id' => $rule->id,
    'integration_id' => $buyer->id,
    'lead_id' => $lead->id,
    'fingerprint' => $lead->fingerprint,
    'status' => ValidationLogStatus::FAILED,
    'result' => 'fail',
    'resolved_at' => now(),
  ]);

  expect($checker()->isEligibleForQuality($buyer, $lead))->toBeFalse();
  expect($checker()->getSkipReason($buyer, $lead))->toContain('last attempt failed');
});

it('surfaces pending reason when the latest log is still sent/pending', function () use ($checker) {
  [$buyer, $lead, $rule] = buildStack();

  LeadQualityValidationLog::factory()->create([
    'validation_rule_id' => $rule->id,
    'integration_id' => $buyer->id,
    'lead_id' => $lead->id,
    'fingerprint' => $lead->fingerprint,
    'status' => ValidationLogStatus::SENT,
    'expires_at' => now()->addMinutes(5),
    'challenge_reference' => 'VE-sent',
  ]);

  expect($checker()->isEligibleForQuality($buyer, $lead))->toBeFalse();
  expect($checker()->getSkipReason($buyer, $lead))->toContain('challenge pending');
});

it('ignores disabled pivot entries and inactive rules', function () use ($checker) {
  $buyer = Integration::factory()->create();
  $lead = Lead::factory()->create();
  $rule = LeadQualityValidationRule::factory()->draft()->create();
  $buyer->validationRules()->attach($rule->id, ['is_enabled' => false]);

  expect($checker()->isEligibleForQuality($buyer, $lead))->toBeTrue();
});
