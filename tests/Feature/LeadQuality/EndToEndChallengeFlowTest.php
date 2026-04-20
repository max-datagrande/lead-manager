<?php

use App\Enums\DispatchStatus;
use App\Enums\LeadQuality\RuleStatus;
use App\Enums\LeadQuality\ValidationLogStatus;
use App\Jobs\PingPost\DispatchLeadJob;
use App\Models\ExternalServiceRequest;
use App\Models\Integration;
use App\Models\Lead;
use App\Models\LeadDispatch;
use App\Models\LeadQualityProvider;
use App\Models\LeadQualityValidationLog;
use App\Models\LeadQualityValidationRule;
use App\Models\Workflow;
use App\Models\WorkflowBuyer;
use Illuminate\Support\Facades\Http;

use function Pest\Laravel\postJson;

/**
 * End-to-end happy path covering the full Lead Quality flow:
 *
 *   1. Landing captures lead + calls /challenge/send
 *   2. Backend creates LeadDispatch PENDING_VALIDATION + sends OTP via Twilio
 *   3. User enters code, landing calls /challenge/verify
 *   4. Backend transitions dispatch to RUNNING and queues DispatchLeadJob
 *   5. DispatchLeadJob is executed synchronously (Queue is not faked here)
 *      so the orchestrator runs real ping + post against fake HTTP
 *   6. Buyer accepts both ping and post → dispatch ends SOLD
 *
 * Every HTTP side effect is mocked; every DB state transition is asserted.
 */

function e2eHostHeader(): array
{
  return ['X-Postman-Auth-Token' => config('services.postman_auth_token', 'test-token')];
}

function buildE2eStack(): array
{
  $provider = LeadQualityProvider::factory()
    ->active()
    ->create([
      'credentials' => [
        'account_sid' => 'AC' . str_repeat('a', 32),
        'auth_token' => 'super-secret-token',
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
      'settings' => ['channel' => 'sms', 'otp_length' => 6, 'ttl' => 600, 'max_attempts' => 3, 'validity_window' => 15],
    ]);
  $buyer->validationRules()->attach($rule->id, ['is_enabled' => true]);

  $lead = Lead::factory()->create(['fingerprint' => 'fp-e2e-' . uniqid()]);

  return compact('provider', 'buyer', 'workflow', 'rule', 'lead');
}

beforeEach(function () {
  config(['app.postman_auth_enabled' => true, 'services.postman_auth_token' => 'test-token']);

  // Run jobs synchronously so the dispatch queued by verify actually executes inline.
  config(['queue.default' => 'sync']);
});

test('full flow: landing captures lead, verifies OTP, dispatch runs to SOLD', function () {
  // Arrange: mock Twilio (send + verify) AND the buyer ping/post.
  Http::fake([
    'verify.twilio.com/*/Verifications' => Http::response(['sid' => 'VE' . str_repeat('c', 32), 'status' => 'pending'], 201),
    'verify.twilio.com/*/VerificationCheck' => Http::response(['status' => 'approved'], 200),
    'https://buyer.example.com/ping' => Http::response(['accepted' => 'true', 'bid' => 12.5]),
    'https://buyer.example.com/post' => Http::response(['accepted' => 'true']),
  ]);

  $stack = buildE2eStack();
  $phone = '+15555551234';

  // Act 1: landing calls /challenge/send.
  $send = postJson(
    '/v1/lead-quality/challenge/send',
    [
      'workflow_id' => $stack['workflow']->id,
      'lead_id' => $stack['lead']->id,
      'fingerprint' => $stack['lead']->fingerprint,
      'to' => $phone,
      'channel' => 'sms',
    ],
    e2eHostHeader(),
  );

  $send->assertOk();
  $send->assertJsonPath('success', true);
  expect($send->json('data.challenges'))->toHaveCount(1);

  $dispatchId = $send->json('data.dispatch_id');
  $challengeToken = $send->json('data.challenges.0.challenge_token');

  // Assert: dispatch in PENDING_VALIDATION, log in SENT, Twilio send was recorded.
  $dispatch = LeadDispatch::findOrFail($dispatchId);
  expect($dispatch->status)->toBe(DispatchStatus::PENDING_VALIDATION);

  $log = LeadQualityValidationLog::where('lead_dispatch_id', $dispatchId)->firstOrFail();
  expect($log->status)->toBe(ValidationLogStatus::SENT);
  expect($log->challenge_reference)->toStartWith('VE');

  expect(
    ExternalServiceRequest::where('module', 'lead_quality')->where('operation', 'send_challenge')->where('status', 'success')->exists(),
  )->toBeTrue();

  // Act 2: landing calls /challenge/verify with the correct code.
  $verify = postJson(
    '/v1/lead-quality/challenge/verify',
    ['challenge_token' => $challengeToken, 'code' => '123456', 'to' => $phone],
    e2eHostHeader(),
  );

  $verify->assertOk();
  $verify->assertJsonPath('data.verified', true);

  // Assert end state: log VERIFIED, dispatch SOLD (sync queue ran the job + orchestrator).
  $log->refresh();
  expect($log->status)->toBe(ValidationLogStatus::VERIFIED);

  $dispatch->refresh();
  expect($dispatch->status)->toBe(DispatchStatus::SOLD);
  expect((float) $dispatch->final_price)->toBeGreaterThan(0);

  // Assert the orchestrator used the SAME LeadDispatch row, not a sibling.
  expect(LeadDispatch::where('fingerprint', $stack['lead']->fingerprint)->count())->toBe(1);

  // Assert Twilio verify was recorded.
  expect(ExternalServiceRequest::where('operation', 'verify_challenge')->where('status', 'success')->exists())->toBeTrue();

  // Assert the buyer got both ping and post calls.
  Http::assertSent(fn($req) => str_contains($req->url(), '/ping'));
  Http::assertSent(fn($req) => str_contains($req->url(), '/post'));
});

test('full flow: landing aborts when user exhausts verify attempts', function () {
  Http::fake([
    'verify.twilio.com/*/Verifications' => Http::response(['sid' => 'VE' . str_repeat('c', 32), 'status' => 'pending'], 201),
    'verify.twilio.com/*/VerificationCheck' => Http::response(['status' => 'pending'], 200),
  ]);

  $stack = buildE2eStack();
  // Shorten the rule so a single wrong attempt is terminal.
  $stack['rule']->update(['settings' => ['channel' => 'sms', 'max_attempts' => 1, 'ttl' => 600, 'validity_window' => 15]]);

  $send = postJson(
    '/v1/lead-quality/challenge/send',
    [
      'workflow_id' => $stack['workflow']->id,
      'lead_id' => $stack['lead']->id,
      'fingerprint' => $stack['lead']->fingerprint,
      'to' => '+15555551234',
      'channel' => 'sms',
    ],
    e2eHostHeader(),
  );
  $send->assertOk();

  $token = $send->json('data.challenges.0.challenge_token');

  // Wrong code → goes straight to failed because max_attempts=1.
  $verify = postJson('/v1/lead-quality/challenge/verify', ['challenge_token' => $token, 'code' => '000000', 'to' => '+15555551234'], e2eHostHeader());
  $verify->assertStatus(410);
  $verify->assertJsonPath('errors.status', 'failed');

  $dispatch = LeadDispatch::findOrFail($send->json('data.dispatch_id'));
  expect($dispatch->status)->toBe(DispatchStatus::VALIDATION_FAILED);
  expect(LeadQualityValidationLog::where('lead_dispatch_id', $dispatch->id)->value('status'))->toBe(ValidationLogStatus::FAILED);

  // No buyer call was ever made — the orchestrator never ran.
  Http::assertNotSent(fn($req) => str_contains($req->url(), 'buyer.example.com'));
});

test('full flow: no applicable rules -> send response is empty, landing is expected to fall back to shareLead', function () {
  Http::fake();

  $buyer = Integration::factory()->pingPost()->withBuyerConfig()->create();
  $workflow = Workflow::factory()->bestBid()->create();
  WorkflowBuyer::create([
    'workflow_id' => $workflow->id,
    'integration_id' => $buyer->id,
    'position' => 0,
    'buyer_group' => 'primary',
    'is_active' => true,
  ]);
  $lead = Lead::factory()->create(['fingerprint' => 'fp-e2e-no-rules']);

  $send = postJson(
    '/v1/lead-quality/challenge/send',
    [
      'workflow_id' => $workflow->id,
      'lead_id' => $lead->id,
      'fingerprint' => $lead->fingerprint,
    ],
    e2eHostHeader(),
  );

  $send->assertOk();
  expect($send->json('data.challenges'))->toHaveCount(0);
  expect($send->json('data.errors'))->toHaveCount(0);

  // The backend still creates a LeadDispatch shell in PENDING_VALIDATION — the landing
  // is expected to ignore it and call shareLead() directly; the expiration command will
  // sweep it later if never referenced.
  $dispatch = LeadDispatch::where('fingerprint', 'fp-e2e-no-rules')->first();
  expect($dispatch)->not->toBeNull();
  expect($dispatch->status)->toBe(DispatchStatus::PENDING_VALIDATION);
  Http::assertNothingSent();
});
