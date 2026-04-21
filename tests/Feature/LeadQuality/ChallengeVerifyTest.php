<?php

use App\Enums\DispatchStatus;
use App\Enums\LeadQuality\RuleStatus;
use App\Enums\LeadQuality\ValidationLogStatus;
use App\Jobs\PingPost\DispatchLeadJob;
use App\Models\Integration;
use App\Models\Lead;
use App\Models\LeadDispatch;
use App\Models\LeadQualityProvider;
use App\Models\LeadQualityValidationLog;
use App\Models\LeadQualityValidationRule;
use App\Models\Workflow;
use App\Models\WorkflowBuyer;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

use function Pest\Laravel\postJson;

function verifyHostHeader(): array
{
  return ['X-Postman-Auth-Token' => config('services.postman_auth_token', 'test-token')];
}

function seedSentChallenge(array $settings = []): array
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
      'settings' => array_merge(['ttl' => 600, 'max_attempts' => 3, 'validity_window' => 15], $settings),
    ]);
  $buyer->validationRules()->attach($rule->id, ['is_enabled' => true]);

  $lead = Lead::factory()->create(['fingerprint' => 'fp-verify-' . uniqid()]);

  $dispatch = LeadDispatch::create([
    'workflow_id' => $workflow->id,
    'lead_id' => $lead->id,
    'fingerprint' => $lead->fingerprint,
    'status' => DispatchStatus::PENDING_VALIDATION,
    'strategy_used' => $workflow->strategy?->value,
    'started_at' => now(),
  ]);

  $log = LeadQualityValidationLog::factory()->create([
    'validation_rule_id' => $rule->id,
    'provider_id' => $provider->id,
    'integration_id' => $buyer->id,
    'lead_id' => $lead->id,
    'lead_dispatch_id' => $dispatch->id,
    'fingerprint' => $lead->fingerprint,
    'status' => ValidationLogStatus::SENT,
    'challenge_reference' => 'VE' . str_repeat('c', 32),
    'expires_at' => now()->addMinutes(10),
    'attempts_count' => 0,
  ]);

  $token = Crypt::encryptString(json_encode(['log_id' => $log->id, 'fingerprint' => $lead->fingerprint]));

  return compact('provider', 'rule', 'buyer', 'workflow', 'lead', 'dispatch', 'log', 'token');
}

beforeEach(function () {
  config(['app.postman_auth_enabled' => true, 'services.postman_auth_token' => 'test-token']);
});

test('verify approves the challenge and transitions dispatch to RUNNING', function () {
  Queue::fake();
  Http::fake(['verify.twilio.com/*/VerificationCheck' => Http::response(['status' => 'approved'], 200)]);

  $ctx = seedSentChallenge();

  $response = postJson(
    '/v1/lead-quality/challenge/verify',
    [
      'challenge_token' => $ctx['token'],
      'code' => '123456',
      'to' => '+15555551234',
    ],
    verifyHostHeader(),
  );

  $response->assertOk();
  $response->assertJsonPath('success', true);
  $response->assertJsonPath('data.verified', true);

  expect($ctx['log']->fresh()->status)->toBe(ValidationLogStatus::VERIFIED);
  expect($ctx['dispatch']->fresh()->status)->toBe(DispatchStatus::RUNNING);

  Queue::assertPushed(DispatchLeadJob::class, function (DispatchLeadJob $job) use ($ctx) {
    return $job->leadDispatchId === $ctx['dispatch']->id && $job->workflowId === $ctx['workflow']->id && $job->leadId === $ctx['lead']->id;
  });
});

test('verify with wrong code below max_attempts returns retry remaining', function () {
  Http::fake(['verify.twilio.com/*/VerificationCheck' => Http::response(['status' => 'pending'], 200)]);

  $ctx = seedSentChallenge(['max_attempts' => 3]);

  $response = postJson(
    '/v1/lead-quality/challenge/verify',
    [
      'challenge_token' => $ctx['token'],
      'code' => '000000',
      'to' => '+15555551234',
    ],
    verifyHostHeader(),
  );

  $response->assertStatus(422);
  $response->assertJsonPath('errors.status', 'retry');
  $response->assertJsonPath('errors.retry_remaining', 2);

  expect($ctx['log']->fresh()->attempts_count)->toBe(1);
  expect($ctx['dispatch']->fresh()->status)->toBe(DispatchStatus::PENDING_VALIDATION);
});

test('verify closes out dispatch as VALIDATION_FAILED once max_attempts is reached', function () {
  Http::fake(['verify.twilio.com/*/VerificationCheck' => Http::response(['status' => 'pending'], 200)]);

  $ctx = seedSentChallenge(['max_attempts' => 1]);

  $response = postJson(
    '/v1/lead-quality/challenge/verify',
    [
      'challenge_token' => $ctx['token'],
      'code' => '000000',
      'to' => '+15555551234',
    ],
    verifyHostHeader(),
  );

  $response->assertStatus(410);
  $response->assertJsonPath('errors.status', 'failed');

  expect($ctx['log']->fresh()->status)->toBe(ValidationLogStatus::FAILED);
  expect($ctx['dispatch']->fresh()->status)->toBe(DispatchStatus::VALIDATION_FAILED);
});

test('verify marks expired when challenge already passed expiration', function () {
  Http::fake();

  $ctx = seedSentChallenge();
  $ctx['log']->update(['expires_at' => now()->subMinute()]);

  $response = postJson(
    '/v1/lead-quality/challenge/verify',
    [
      'challenge_token' => $ctx['token'],
      'code' => '123456',
      'to' => '+15555551234',
    ],
    verifyHostHeader(),
  );

  $response->assertStatus(410);
  $response->assertJsonPath('errors.status', 'expired');

  expect($ctx['log']->fresh()->status)->toBe(ValidationLogStatus::EXPIRED);
  expect($ctx['dispatch']->fresh()->status)->toBe(DispatchStatus::VALIDATION_FAILED);
  Http::assertNothingSent();
});

test('verify rejects tampered tokens', function () {
  Http::fake();

  $response = postJson(
    '/v1/lead-quality/challenge/verify',
    [
      'challenge_token' => 'not-a-real-token',
      'code' => '123456',
    ],
    verifyHostHeader(),
  );

  $response->assertStatus(404);
  $response->assertJsonPath('errors.status', 'invalid_token');
  Http::assertNothingSent();
});

test('verify validates required fields', function () {
  $response = postJson('/v1/lead-quality/challenge/verify', [], verifyHostHeader());
  $response->assertStatus(422);
  $response->assertJsonValidationErrors(['challenge_token', 'code']);
});
