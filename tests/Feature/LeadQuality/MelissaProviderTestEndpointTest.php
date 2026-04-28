<?php

use App\Models\ExternalServiceRequest;
use App\Models\LeadQualityProvider;
use App\Models\User;
use Illuminate\Support\Facades\Http;

use function Pest\Laravel\actingAs;

function melissaAdminTesterResponse(string $results): array
{
  return [
    'Version' => '1.0',
    'TransmissionResults' => '',
    'Records' => [
      [
        'RecordID' => '1',
        'Results' => $results,
        'PhoneNumber' => '+18006354772',
        'CountryAbbreviation' => 'US',
        'Carrier' => 'Test Carrier',
      ],
    ],
  ];
}

beforeEach(function () {
  $this->admin = User::factory()->create(['role' => 'admin']);
});

test('admin tester returns 200 with classification when Melissa accepts', function () {
  Http::fake([
    'globalphone.melissadata.net/*' => Http::response(melissaAdminTesterResponse('PS01,PS22'), 200),
  ]);

  $provider = LeadQualityProvider::factory()->melissa()->create();

  $response = actingAs($this->admin)->postJson(route('lead-quality.providers.test-validate-phone', $provider), [
    'phone' => '8006354772',
    'country' => 'US',
  ]);

  $response->assertOk();
  $response->assertJsonPath('ok', true);
  $response->assertJsonPath('valid', true);
  $response->assertJsonPath('classification', 'valid_high_confidence');
  $response->assertJsonPath('country', 'US');
  expect($response->json('result_codes'))->toContain('PS22');
});

test('admin tester returns 422 when classification is technical_error', function () {
  Http::fake([
    'globalphone.melissadata.net/*' => Http::response(['Version' => '1.0', 'TransmissionResults' => 'GE05', 'Records' => []], 200),
  ]);

  $provider = LeadQualityProvider::factory()->melissa()->create();

  $response = actingAs($this->admin)->postJson(route('lead-quality.providers.test-validate-phone', $provider), ['phone' => '8006354772']);

  $response->assertStatus(422);
  $response->assertJsonPath('ok', false);
  $response->assertJsonPath('classification', 'validation_error');
  expect($response->json('error'))->toContain('license');
});

test('admin tester returns 200 valid:false for hard rejection (PE11)', function () {
  Http::fake([
    'globalphone.melissadata.net/*' => Http::response(melissaAdminTesterResponse('PE11'), 200),
  ]);

  $provider = LeadQualityProvider::factory()->melissa()->create();

  $response = actingAs($this->admin)->postJson(route('lead-quality.providers.test-validate-phone', $provider), ['phone' => '8006354772']);

  // Hard rejection by Melissa is `ok: true` from the controller's POV — the
  // call succeeded technically — but `valid: false`.
  $response->assertOk();
  $response->assertJsonPath('ok', true);
  $response->assertJsonPath('valid', false);
  $response->assertJsonPath('classification', 'disconnected_phone');
});

test('admin tester records the request as test_validate_phone (not phone_validate)', function () {
  Http::fake([
    'globalphone.melissadata.net/*' => Http::response(melissaAdminTesterResponse('PS22'), 200),
  ]);

  $provider = LeadQualityProvider::factory()->melissa()->create();

  actingAs($this->admin)->postJson(route('lead-quality.providers.test-validate-phone', $provider), ['phone' => '8006354772']);

  expect(
    ExternalServiceRequest::query()
      ->where('service_name', 'melissa')
      ->where('operation', 'test_validate_phone')
      ->where('service_id', $provider->id)
      ->where('status', 'success')
      ->exists(),
  )->toBeTrue();

  // And it does NOT pollute the production phone_validate operation.
  expect(ExternalServiceRequest::query()->where('operation', 'phone_validate')->exists())->toBeFalse();
});

test('admin tester rejects non-Melissa providers', function () {
  Http::fake();

  $provider = LeadQualityProvider::factory()->create(); // twilio_verify

  $response = actingAs($this->admin)->postJson(route('lead-quality.providers.test-validate-phone', $provider), ['phone' => '8006354772']);

  $response->assertStatus(422);
  $response->assertJsonPath('ok', false);
  Http::assertNothingSent();
});

test('admin tester validates required fields', function () {
  $provider = LeadQualityProvider::factory()->melissa()->create();

  $response = actingAs($this->admin)->postJson(route('lead-quality.providers.test-validate-phone', $provider), []);

  $response->assertStatus(422);
  $response->assertJsonValidationErrors(['phone']);
});

test('non-admin cannot hit the admin tester endpoint', function () {
  $provider = LeadQualityProvider::factory()->melissa()->create();
  $user = User::factory()->create(['role' => 'user']);

  actingAs($user)
    ->postJson(route('lead-quality.providers.test-validate-phone', $provider), ['phone' => '8006354772'])
    ->assertForbidden();
});
