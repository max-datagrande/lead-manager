<?php

use App\Enums\DispatchStatus;
use App\Enums\LeadQuality\RuleStatus;
use App\Enums\LeadQuality\ValidationLogStatus;
use App\Models\DispatchTimelineLog;
use App\Models\Integration;
use App\Models\Lead;
use App\Models\LeadDispatch;
use App\Models\LeadQualityProvider;
use App\Models\LeadQualityValidationLog;
use App\Models\LeadQualityValidationRule;
use App\Models\Workflow;
use App\Models\WorkflowBuyer;
use App\Services\PingPost\DispatchTimelineService;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;

use function Pest\Laravel\artisan;
use function Pest\Laravel\postJson;

function timelineHostHeader(): array
{
  return ['X-Postman-Auth-Token' => config('services.postman_auth_token', 'test-token')];
}

function seedChallengeReadyWorkflow(array $settings = []): array
{
  $provider = LeadQualityProvider::factory()
    ->active()
    ->create([
      'credentials' => [
        'account_sid' => 'AC' . str_repeat('a', 32),
        'auth_token' => 'token',
        'verify_service_sid' => 'VA' . str_repeat('b', 32),
      ],
    ]);

  $buyer = Integration::factory()->pingPost()->withBuyerConfig()->create();
  $workflow = Workflow::factory()->bestBid()->create();
  WorkflowBuyer::create([
    'workflow_id' => $workflow->id,
    'integration_id' => $buyer->id,
    'position' => 0,
    'buyer_group' => 'primary',
    'is_active' => true,
  ]);

  $rule = LeadQualityValidationRule::factory()
    ->forProvider($provider)
    ->create([
      'status' => RuleStatus::ACTIVE,
      'is_enabled' => true,
      'settings' => array_merge(['channel' => 'sms', 'otp_length' => 6, 'ttl' => 600, 'max_attempts' => 3, 'validity_window' => 15], $settings),
    ]);
  $buyer->validationRules()->attach($rule->id, ['is_enabled' => true]);

  $lead = Lead::factory()->create(['fingerprint' => 'fp-timeline-' . uniqid()]);

  return compact('provider', 'buyer', 'workflow', 'rule', 'lead');
}

beforeEach(function () {
  config(['app.postman_auth_enabled' => true, 'services.postman_auth_token' => 'test-token']);
});

test('challenge/send writes VALIDATION_STARTED and challenge.sent to the timeline', function () {
  Http::fake([
    'verify.twilio.com/*/Verifications' => Http::response(['sid' => 'VE' . str_repeat('c', 32), 'status' => 'pending'], 201),
  ]);

  $stack = seedChallengeReadyWorkflow();

  $send = postJson(
    '/v1/lead-quality/challenge/send',
    [
      'workflow_id' => $stack['workflow']->id,
      'lead_id' => $stack['lead']->id,
      'fingerprint' => $stack['lead']->fingerprint,
      'to' => '+15555551234',
      'channel' => 'sms',
    ],
    timelineHostHeader(),
  );
  $send->assertOk();

  $dispatchId = $send->json('data.dispatch_id');
  $events = DispatchTimelineLog::where('lead_dispatch_id', $dispatchId)->pluck('event')->all();

  expect($events)->toContain(DispatchTimelineService::VALIDATION_STARTED);
  expect($events)->toContain('challenge.sent');

  // The sent row carries the masked destination so the admin timeline is readable.
  $sentRow = DispatchTimelineLog::where('lead_dispatch_id', $dispatchId)->where('event', 'challenge.sent')->first();
  expect($sentRow->message)->toContain('1234');
  expect($sentRow->context)->toHaveKey('masked_destination');
});

test('verify failures at max_attempts write challenge.attempt_failed + VALIDATION_FAILED', function () {
  Http::fake(['verify.twilio.com/*/VerificationCheck' => Http::response(['status' => 'pending'], 200)]);

  $stack = seedChallengeReadyWorkflow(['max_attempts' => 2]);

  $dispatch = LeadDispatch::create([
    'workflow_id' => $stack['workflow']->id,
    'lead_id' => $stack['lead']->id,
    'fingerprint' => $stack['lead']->fingerprint,
    'status' => DispatchStatus::PENDING_VALIDATION,
    'strategy_used' => $stack['workflow']->strategy?->value,
    'started_at' => now(),
  ]);

  $log = LeadQualityValidationLog::factory()->create([
    'validation_rule_id' => $stack['rule']->id,
    'provider_id' => $stack['provider']->id,
    'lead_id' => $stack['lead']->id,
    'lead_dispatch_id' => $dispatch->id,
    'fingerprint' => $stack['lead']->fingerprint,
    'status' => ValidationLogStatus::SENT,
    'challenge_reference' => 'VE-tl',
    'expires_at' => now()->addMinutes(10),
    'attempts_count' => 0,
  ]);

  $token = Crypt::encryptString(json_encode(['log_id' => $log->id, 'fingerprint' => $stack['lead']->fingerprint]));

  // First wrong attempt → retry
  postJson(
    '/v1/lead-quality/challenge/verify',
    ['challenge_token' => $token, 'code' => '000000', 'to' => '+15555551234'],
    timelineHostHeader(),
  )->assertStatus(422);

  // Second wrong attempt → terminal
  postJson(
    '/v1/lead-quality/challenge/verify',
    ['challenge_token' => $token, 'code' => '000000', 'to' => '+15555551234'],
    timelineHostHeader(),
  )->assertStatus(410);

  $events = DispatchTimelineLog::where('lead_dispatch_id', $dispatch->id)->pluck('event')->all();

  expect($events)->toContain('challenge.attempt_failed');
  expect($events)->toContain(DispatchTimelineService::VALIDATION_FAILED);

  $attemptRow = DispatchTimelineLog::where('lead_dispatch_id', $dispatch->id)->where('event', 'challenge.attempt_failed')->first();
  expect($attemptRow->context['attempt_number'])->toBe(1);
  expect($attemptRow->context['retry_remaining'])->toBe(1);

  expect($dispatch->fresh()->status)->toBe(DispatchStatus::VALIDATION_FAILED);
});

test('verify success writes VALIDATION_COMPLETED before dispatching the job', function () {
  Http::fake(['verify.twilio.com/*/VerificationCheck' => Http::response(['status' => 'approved'], 200)]);
  config(['queue.default' => 'sync']);
  // Use an Http::fake for the buyer too so the orchestrator can run to completion.
  Http::fake([
    'verify.twilio.com/*/VerificationCheck' => Http::response(['status' => 'approved'], 200),
    'https://buyer.example.com/ping' => Http::response(['accepted' => 'true', 'bid' => 5.0]),
    'https://buyer.example.com/post' => Http::response(['accepted' => 'true']),
  ]);

  $stack = seedChallengeReadyWorkflow();

  $dispatch = LeadDispatch::create([
    'workflow_id' => $stack['workflow']->id,
    'lead_id' => $stack['lead']->id,
    'fingerprint' => $stack['lead']->fingerprint,
    'status' => DispatchStatus::PENDING_VALIDATION,
    'strategy_used' => $stack['workflow']->strategy?->value,
    'started_at' => now(),
  ]);

  $log = LeadQualityValidationLog::factory()->create([
    'validation_rule_id' => $stack['rule']->id,
    'provider_id' => $stack['provider']->id,
    'lead_id' => $stack['lead']->id,
    'lead_dispatch_id' => $dispatch->id,
    'fingerprint' => $stack['lead']->fingerprint,
    'status' => ValidationLogStatus::SENT,
    'challenge_reference' => 'VE-tl-ok',
    'expires_at' => now()->addMinutes(10),
    'attempts_count' => 0,
  ]);

  $token = Crypt::encryptString(json_encode(['log_id' => $log->id, 'fingerprint' => $stack['lead']->fingerprint]));

  postJson(
    '/v1/lead-quality/challenge/verify',
    ['challenge_token' => $token, 'code' => '123456', 'to' => '+15555551234'],
    timelineHostHeader(),
  )->assertOk();

  $events = DispatchTimelineLog::where('lead_dispatch_id', $dispatch->id)->pluck('event')->all();

  // The challenge side must emit VALIDATION_COMPLETED explicitly — it is the marker
  // that tells the admin "the PENDING phase ended with success" before the orchestrator
  // takes over with its own events.
  expect($events)->toContain(DispatchTimelineService::VALIDATION_COMPLETED);
});

test('expire command writes VALIDATION_FAILED to the timeline', function () {
  $stack = seedChallengeReadyWorkflow();

  $dispatch = LeadDispatch::create([
    'workflow_id' => $stack['workflow']->id,
    'lead_id' => $stack['lead']->id,
    'fingerprint' => $stack['lead']->fingerprint,
    'status' => DispatchStatus::PENDING_VALIDATION,
    'strategy_used' => $stack['workflow']->strategy?->value,
    'started_at' => now(),
  ]);
  LeadQualityValidationLog::factory()->create([
    'validation_rule_id' => $stack['rule']->id,
    'provider_id' => $stack['provider']->id,
    'lead_id' => $stack['lead']->id,
    'lead_dispatch_id' => $dispatch->id,
    'fingerprint' => $stack['lead']->fingerprint,
    'status' => ValidationLogStatus::SENT,
    'challenge_reference' => 'VE-expire',
    'expires_at' => now()->subMinute(),
    'attempts_count' => 0,
  ]);

  artisan('lead-quality:expire-validation')->assertSuccessful();

  $row = DispatchTimelineLog::where('lead_dispatch_id', $dispatch->id)->where('event', DispatchTimelineService::VALIDATION_FAILED)->first();

  expect($row)->not->toBeNull();
  expect($row->context['source'])->toBe('lead-quality:expire-validation');
  expect($row->context['reason'])->toBe('quality_expired');
});
