<?php

use App\Enums\DispatchStatus;
use App\Enums\LeadQuality\RuleStatus;
use App\Enums\LeadQuality\ValidationLogStatus;
use App\Enums\LeadQuality\ValidationType;
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

function leadQualityHostHeader(): array
{
  return ['X-Postman-Auth-Token' => config('services.postman_auth_token', 'test-token')];
}

function makeLeadQualityTwilioProvider(): LeadQualityProvider
{
  return LeadQualityProvider::factory()
    ->active()
    ->create([
      'credentials' => [
        'account_sid' => 'AC' . str_repeat('a', 32),
        'auth_token' => 'token-' . uniqid(),
        'verify_service_sid' => 'VA' . str_repeat('b', 32),
      ],
    ]);
}

function makeWorkflowWithBuyerAndRule(LeadQualityProvider $provider): array
{
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

  return [$workflow, $buyer, $rule];
}

beforeEach(function () {
  config(['app.postman_auth_enabled' => true, 'services.postman_auth_token' => 'test-token']);
});

test('send issues a challenge and creates a PENDING_VALIDATION dispatch', function () {
  Http::fake([
    'verify.twilio.com/*/Verifications' => Http::response(['sid' => 'VE' . str_repeat('c', 32), 'status' => 'pending'], 201),
  ]);

  $provider = makeLeadQualityTwilioProvider();
  [$workflow] = makeWorkflowWithBuyerAndRule($provider);
  $lead = Lead::factory()->create(['fingerprint' => 'fp-send-ok']);

  $response = postJson(
    '/v1/lead-quality/challenge/send',
    [
      'workflow_id' => $workflow->id,
      'lead_id' => $lead->id,
      'fingerprint' => 'fp-send-ok',
      'to' => '+15555551234',
      'channel' => 'sms',
    ],
    leadQualityHostHeader(),
  );

  $response->assertOk();
  $response->assertJsonPath('success', true);
  expect($response->json('data.challenges'))->toHaveCount(1);
  expect($response->json('data.challenges.0.challenge_token'))->toBeString();
  expect($response->json('data.challenges.0.masked_destination'))->toEndWith('1234');

  $dispatch = LeadDispatch::findOrFail($response->json('data.dispatch_id'));
  expect($dispatch->status)->toBe(DispatchStatus::PENDING_VALIDATION);

  expect(LeadQualityValidationLog::where('lead_dispatch_id', $dispatch->id)->where('status', ValidationLogStatus::SENT)->exists())->toBeTrue();
  expect(ExternalServiceRequest::where('operation', 'send_challenge')->where('status', 'success')->exists())->toBeTrue();
});

test('send succeeds gracefully when no applicable rules apply', function () {
  $buyer = Integration::factory()->pingPost()->withBuyerConfig()->create();
  $workflow = Workflow::factory()->bestBid()->create();
  WorkflowBuyer::create([
    'workflow_id' => $workflow->id,
    'integration_id' => $buyer->id,
    'position' => 0,
    'buyer_group' => 'primary',
    'is_active' => true,
  ]);
  $lead = Lead::factory()->create(['fingerprint' => 'fp-no-rules']);

  $response = postJson(
    '/v1/lead-quality/challenge/send',
    [
      'workflow_id' => $workflow->id,
      'lead_id' => $lead->id,
      'fingerprint' => 'fp-no-rules',
    ],
    leadQualityHostHeader(),
  );

  $response->assertOk();
  expect($response->json('data.challenges'))->toHaveCount(0);
  expect($response->json('data.errors'))->toHaveCount(0);
});

test('send returns 502 and marks dispatch VALIDATION_FAILED when every provider rejects', function () {
  Http::fake([
    'verify.twilio.com/*/Verifications' => Http::response(['code' => 60200, 'message' => 'Invalid To'], 400),
  ]);

  $provider = makeLeadQualityTwilioProvider();
  [$workflow] = makeWorkflowWithBuyerAndRule($provider);
  $lead = Lead::factory()->create(['fingerprint' => 'fp-send-fail']);

  $response = postJson(
    '/v1/lead-quality/challenge/send',
    [
      'workflow_id' => $workflow->id,
      'lead_id' => $lead->id,
      'fingerprint' => 'fp-send-fail',
      'to' => '+15555551234',
      'channel' => 'sms',
    ],
    leadQualityHostHeader(),
  );

  $response->assertStatus(502);
  $response->assertJsonPath('success', false);

  $dispatch = LeadDispatch::where('fingerprint', 'fp-send-fail')->first();
  expect($dispatch)->not->toBeNull();
  expect($dispatch->status)->toBe(DispatchStatus::VALIDATION_FAILED);
});

test('send validates required fields', function () {
  $response = postJson('/v1/lead-quality/challenge/send', [], leadQualityHostHeader());
  $response->assertStatus(422);
  // lead_id is now optional — the controller resolves the lead from fingerprint
  // when it's missing, so only workflow_id + fingerprint are strictly required.
  $response->assertJsonValidationErrors(['workflow_id', 'fingerprint']);
});

test('send seeds lead_snapshot at PENDING_VALIDATION so failed validations keep an audit trail', function () {
  Http::fake([
    'verify.twilio.com/*/Verifications' => Http::response(['sid' => 'VE' . str_repeat('c', 32), 'status' => 'pending'], 201),
  ]);

  $provider = makeLeadQualityTwilioProvider();
  [$workflow] = makeWorkflowWithBuyerAndRule($provider);

  // Lead with one field response so the snapshot has something observable.
  $fingerprint = 'fp-snapshot-seed';
  \App\Models\TrafficLog::factory()->create(['fingerprint' => $fingerprint, 'is_bot' => false, 'utm_source' => 'facebook']);
  $lead = Lead::factory()->create(['fingerprint' => $fingerprint]);
  $field = \App\Models\Field::factory()->create(['name' => 'phone_at_request']);
  \App\Models\LeadFieldResponse::create([
    'lead_id' => $lead->id,
    'field_id' => $field->id,
    'fingerprint' => $fingerprint,
    'value' => '+15555551234',
  ]);

  $response = postJson(
    '/v1/lead-quality/challenge/send',
    [
      'workflow_id' => $workflow->id,
      'fingerprint' => $fingerprint,
      'to' => '+15555551234',
      'channel' => 'sms',
    ],
    leadQualityHostHeader(),
  );
  $response->assertOk();

  $dispatch = LeadDispatch::findOrFail($response->json('data.dispatch_id'));

  // Snapshot populated at challenge-request time.
  expect($dispatch->status)->toBe(DispatchStatus::PENDING_VALIDATION);
  expect($dispatch->lead_snapshot)->toBeArray();
  expect($dispatch->lead_snapshot[$field->id])->toBe('+15555551234');
  expect($dispatch->utm_source)->toBe('facebook');
});

test('send skips sync validation rules (phone_lookup, etc.) from challenge issuance', function () {
  Http::fake([
    'verify.twilio.com/*/Verifications' => Http::response(['sid' => 'VE' . str_repeat('c', 32), 'status' => 'pending'], 201),
  ]);

  $provider = makeLeadQualityTwilioProvider();
  [$workflow, $buyer, $otpRule] = makeWorkflowWithBuyerAndRule($provider);

  // Attach a phone_lookup (sync) rule to the same buyer alongside the OTP one.
  // The challenge/send flow must ignore it — lookups are evaluated at dispatch
  // time, not as an async user-facing challenge.
  $lookupRule = LeadQualityValidationRule::factory()
    ->forProvider($provider)
    ->create([
      'validation_type' => ValidationType::PHONE_LOOKUP,
      'status' => RuleStatus::ACTIVE,
      'is_enabled' => true,
      'settings' => ['validity_window' => 60],
    ]);
  $buyer->validationRules()->attach($lookupRule->id, ['is_enabled' => true]);

  $lead = Lead::factory()->create(['fingerprint' => 'fp-async-filter']);

  $response = postJson(
    '/v1/lead-quality/challenge/send',
    [
      'workflow_id' => $workflow->id,
      'lead_id' => $lead->id,
      'fingerprint' => 'fp-async-filter',
      'to' => '+15555551234',
      'channel' => 'sms',
    ],
    leadQualityHostHeader(),
  );

  $response->assertOk();
  $challenges = $response->json('data.challenges');
  expect($challenges)->toHaveCount(1);
  expect($challenges[0]['rule_id'])->toBe($otpRule->id);
  expect($response->json('data.errors'))->toHaveCount(0);
});

test('send resolves the lead from fingerprint when lead_id is omitted', function () {
  Http::fake([
    'verify.twilio.com/*/Verifications' => Http::response(['sid' => 'VE' . str_repeat('a', 32), 'status' => 'pending'], 201),
  ]);

  $provider = makeLeadQualityTwilioProvider();
  [$workflow] = makeWorkflowWithBuyerAndRule($provider);

  // Seed both the traffic log and the lead so resolveLead's fingerprint path
  // finds a real lead to attach the dispatch to — mirrors what an actual
  // landing produces via the visitor-register flow.
  $fingerprint = 'fp-resolve-by-fingerprint';
  \App\Models\TrafficLog::factory()->create(['fingerprint' => $fingerprint, 'is_bot' => false]);
  $lead = Lead::factory()->create(['fingerprint' => $fingerprint]);

  $response = postJson(
    '/v1/lead-quality/challenge/send',
    [
      'workflow_id' => $workflow->id,
      'fingerprint' => $fingerprint,
      'to' => '+15555551234',
      'channel' => 'sms',
    ],
    leadQualityHostHeader(),
  );

  $response->assertOk();
  $response->assertJsonPath('success', true);
  expect($response->json('data.challenges'))->toHaveCount(1);

  // The dispatch was created attached to the lead we seeded, not a fresh one.
  $dispatch = LeadDispatch::findOrFail($response->json('data.dispatch_id'));
  expect($dispatch->lead_id)->toBe($lead->id);
});
