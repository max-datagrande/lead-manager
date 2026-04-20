<?php

use App\Enums\LeadQuality\LeadQualityProviderType;
use App\Models\ExternalServiceRequest;
use App\Models\LeadQualityProvider;
use App\Models\User;
use Illuminate\Support\Facades\Http;

use function Pest\Laravel\actingAs;

beforeEach(function () {
  $this->admin = User::factory()->create(['role' => 'admin']);
});

test('test connection endpoint returns ok when Twilio responds 200', function () {
  Http::fake([
    'verify.twilio.com/*' => Http::response(
      [
        'sid' => 'VA' . str_repeat('1', 32),
        'friendly_name' => 'My Verify Service',
      ],
      200,
    ),
  ]);

  $provider = LeadQualityProvider::factory()->create();

  $response = actingAs($this->admin)->postJson(route('lead-quality.providers.test', $provider));

  $response->assertOk();
  $response->assertJson(['ok' => true]);
  expect($response->json('message'))->toContain('My Verify Service');

  expect(
    ExternalServiceRequest::where('module', 'lead_quality')
      ->where('service_name', 'twilio_verify')
      ->where('operation', 'test_connection')
      ->where('service_id', $provider->id)
      ->where('status', 'success')
      ->exists(),
  )->toBeTrue();
});

test('test connection returns 422 when Twilio responds 401', function () {
  Http::fake([
    'verify.twilio.com/*' => Http::response(
      [
        'code' => 20003,
        'message' => 'Authentication Error',
      ],
      401,
    ),
  ]);

  $provider = LeadQualityProvider::factory()->create();

  $response = actingAs($this->admin)->postJson(route('lead-quality.providers.test', $provider));

  $response->assertStatus(422);
  $response->assertJson(['ok' => false]);
  expect($response->json('error'))->toContain('Authentication Error');

  expect(ExternalServiceRequest::where('operation', 'test_connection')->where('status', 'failed')->exists())->toBeTrue();
});

test('test connection fails fast when credentials are incomplete', function () {
  Http::fake();
  $provider = LeadQualityProvider::factory()->create([
    'credentials' => ['account_sid' => ''],
  ]);

  $response = actingAs($this->admin)->postJson(route('lead-quality.providers.test', $provider));

  $response->assertStatus(422);
  expect($response->json('error'))->toContain('credentials');
  Http::assertNothingSent();
});

test('IPQS provider test returns not-implemented error', function () {
  Http::fake();
  $provider = LeadQualityProvider::factory()->ipqs()->create();

  $response = actingAs($this->admin)->postJson(route('lead-quality.providers.test', $provider));

  $response->assertStatus(422);
  expect($response->json('error'))->toContain('placeholder');
  expect($provider->type)->toBe(LeadQualityProviderType::IPQS);
});

test('non-admin cannot hit test endpoint', function () {
  $user = User::factory()->create(['role' => 'user']);
  $provider = LeadQualityProvider::factory()->create();

  actingAs($user)->postJson(route('lead-quality.providers.test', $provider))->assertForbidden();
});
