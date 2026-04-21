<?php

use App\Models\LeadQualityProvider;
use App\Models\LeadQualityValidationLog;
use App\Models\LeadQualityValidationRule;
use App\Services\LeadQuality\Providers\TwilioVerifyProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(Tests\TestCase::class, RefreshDatabase::class);

function makeTwilioStack(): array
{
  $provider = LeadQualityProvider::factory()->create([
    'credentials' => [
      'account_sid' => 'AC' . str_repeat('a', 32),
      'auth_token' => 'token-xyz',
      'verify_service_sid' => 'VA' . str_repeat('b', 32),
    ],
  ]);
  $rule = LeadQualityValidationRule::factory()->forProvider($provider)->create();
  $log = LeadQualityValidationLog::factory()->create([
    'validation_rule_id' => $rule->id,
    'provider_id' => $provider->id,
  ]);

  return [$provider, $rule, $log];
}

it('sendChallenge succeeds and returns Twilio sid', function () {
  Http::fake([
    'verify.twilio.com/*/Verifications' => Http::response(
      [
        'sid' => 'VE' . str_repeat('c', 32),
        'status' => 'pending',
      ],
      201,
    ),
  ]);

  [$provider, $rule, $log] = makeTwilioStack();
  $service = app(TwilioVerifyProvider::class);

  $result = $service->sendChallenge($provider, $rule, $log, ['to' => '+15555551234', 'channel' => 'sms']);

  expect($result->sent)->toBeTrue();
  expect($result->reference)->toStartWith('VE');
  expect($result->maskedDestination)->toEndWith('1234');
});

it('sendChallenge returns failure when Twilio rejects with 400', function () {
  Http::fake([
    'verify.twilio.com/*/Verifications' => Http::response(
      [
        'code' => 60200,
        'message' => 'Invalid parameter To',
      ],
      400,
    ),
  ]);

  [$provider, $rule, $log] = makeTwilioStack();
  $service = app(TwilioVerifyProvider::class);

  $result = $service->sendChallenge($provider, $rule, $log, ['to' => 'garbage', 'channel' => 'sms']);

  expect($result->sent)->toBeFalse();
  expect($result->error)->toContain('Invalid parameter');
});

it('sendChallenge refuses to call Twilio when credentials are missing', function () {
  Http::fake();
  [$provider, $rule, $log] = makeTwilioStack();
  $provider->update(['credentials' => ['account_sid' => '']]);
  $provider->refresh();
  $service = app(TwilioVerifyProvider::class);

  $result = $service->sendChallenge($provider, $rule, $log, ['to' => '+15555551234']);

  expect($result->sent)->toBeFalse();
  Http::assertNothingSent();
});

it('verifyChallenge returns verified when Twilio status is approved', function () {
  Http::fake([
    'verify.twilio.com/*/VerificationCheck' => Http::response(
      [
        'status' => 'approved',
      ],
      200,
    ),
  ]);

  [$provider, $rule, $log] = makeTwilioStack();
  $service = app(TwilioVerifyProvider::class);

  $result = $service->verifyChallenge($provider, $rule, $log, '123456', ['to' => '+15555551234']);

  expect($result->verified)->toBeTrue();
});

it('verifyChallenge returns failure when Twilio status is not approved', function () {
  Http::fake([
    'verify.twilio.com/*/VerificationCheck' => Http::response(
      [
        'status' => 'pending',
      ],
      200,
    ),
  ]);

  [$provider, $rule, $log] = makeTwilioStack();
  $service = app(TwilioVerifyProvider::class);

  $result = $service->verifyChallenge($provider, $rule, $log, '999999', ['to' => '+15555551234']);

  expect($result->verified)->toBeFalse();
  expect($result->error)->toContain('pending');
});

it('sendChallenge normalizes a 10-digit US number to E.164 before hitting Twilio', function () {
  Http::fake([
    'verify.twilio.com/*/Verifications' => Http::response(['sid' => 'VE' . str_repeat('c', 32), 'status' => 'pending'], 201),
  ]);

  [$provider, $rule, $log] = makeTwilioStack();
  $service = app(TwilioVerifyProvider::class);

  // Landing sent raw digits (worst-case common input). Provider should prepend +1.
  $service->sendChallenge($provider, $rule, $log, ['to' => '9542353075', 'channel' => 'sms']);

  Http::assertSent(function ($request) {
    parse_str((string) $request->body(), $parsed);
    return str_contains((string) $request->url(), '/Verifications') && ($parsed['To'] ?? null) === '+19542353075';
  });
});

it('sendChallenge preserves email destinations untouched', function () {
  Http::fake([
    'verify.twilio.com/*/Verifications' => Http::response(['sid' => 'VE' . str_repeat('d', 32), 'status' => 'pending'], 201),
  ]);

  [$provider, $rule, $log] = makeTwilioStack();
  $service = app(TwilioVerifyProvider::class);

  $service->sendChallenge($provider, $rule, $log, ['to' => 'user@example.com', 'channel' => 'email']);

  Http::assertSent(function ($request) {
    parse_str((string) $request->body(), $parsed);
    return ($parsed['To'] ?? null) === 'user@example.com' && ($parsed['Channel'] ?? null) === 'email';
  });
});

it('testConnection succeeds when service exists', function () {
  Http::fake([
    'verify.twilio.com/*' => Http::response(
      [
        'sid' => 'VA' . str_repeat('b', 32),
        'friendly_name' => 'Prod Verify',
      ],
      200,
    ),
  ]);

  [$provider] = makeTwilioStack();
  $service = app(TwilioVerifyProvider::class);

  $result = $service->testConnection($provider);

  expect($result->ok)->toBeTrue();
  expect($result->message)->toContain('Prod Verify');
});
