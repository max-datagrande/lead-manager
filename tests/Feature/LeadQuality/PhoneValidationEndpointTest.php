<?php

use App\Models\ExternalServiceRequest;
use App\Models\LeadQualityProvider;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

use function Pest\Laravel\postJson;

function phoneValidationHostHeader(): array
{
  return ['X-Postman-Auth-Token' => config('services.postman_auth_token', 'test-token')];
}

function makeMelissaResponse(string $results, array $extraRecord = []): array
{
  return [
    'Version' => '1.0',
    'TransmissionReference' => 'trace',
    'TransmissionResults' => '',
    'Records' => [
      array_merge(
        [
          'RecordID' => '1',
          'Results' => $results,
          'PhoneNumber' => '+18006354772',
          'CountryAbbreviation' => 'US',
          'CountryName' => 'United States',
          'Carrier' => 'Test Carrier',
        ],
        $extraRecord,
      ),
    ],
  ];
}

beforeEach(function () {
  config(['app.postman_auth_enabled' => true, 'services.postman_auth_token' => 'test-token']);
  Cache::flush();
});

test('returns 200 valid:true for PS22 response', function () {
  Http::fake([
    'globalphone.melissadata.net/*' => Http::response(makeMelissaResponse('PS01,PS22'), 200),
  ]);
  LeadQualityProvider::factory()->melissa()->create();

  $response = postJson(
    '/v1/lead-quality/phone/validate',
    ['fingerprint' => 'fp-1', 'phone' => '8006354772', 'country' => 'US'],
    phoneValidationHostHeader(),
  );

  $response->assertOk();
  $response->assertJsonPath('success', true);
  $response->assertJsonPath('data.valid', true);
  $response->assertJsonPath('data.classification', 'valid_high_confidence');
  $response->assertJsonPath('data.country', 'US');
});

test('returns 200 valid:false for PE01 response', function () {
  Http::fake([
    'globalphone.melissadata.net/*' => Http::response(makeMelissaResponse('PE01'), 200),
  ]);
  LeadQualityProvider::factory()->melissa()->create();

  $response = postJson(
    '/v1/lead-quality/phone/validate',
    ['fingerprint' => 'fp-2', 'phone' => '0000', 'country' => 'US'],
    phoneValidationHostHeader(),
  );

  $response->assertOk();
  $response->assertJsonPath('success', true);
  $response->assertJsonPath('data.valid', false);
  $response->assertJsonPath('data.classification', 'invalid_phone');
});

test('returns 200 valid:false for disposable PS19', function () {
  Http::fake([
    'globalphone.melissadata.net/*' => Http::response(makeMelissaResponse('PS01,PS19'), 200),
  ]);
  LeadQualityProvider::factory()->melissa()->create();

  $response = postJson(
    '/v1/lead-quality/phone/validate',
    ['fingerprint' => 'fp-3', 'phone' => '8006354772', 'country' => 'US'],
    phoneValidationHostHeader(),
  );

  $response->assertOk();
  $response->assertJsonPath('data.valid', false);
  $response->assertJsonPath('data.classification', 'high_risk_phone');
});

test('returns 502 when Melissa license is invalid (GE05)', function () {
  Http::fake([
    'globalphone.melissadata.net/*' => Http::response(['Version' => '1.0', 'TransmissionResults' => 'GE05', 'Records' => []], 200),
  ]);
  LeadQualityProvider::factory()->melissa()->create();

  $response = postJson('/v1/lead-quality/phone/validate', ['fingerprint' => 'fp-4', 'phone' => '8006354772'], phoneValidationHostHeader());

  $response->assertStatus(502);
  $response->assertJsonPath('success', false);
  expect($response->json('message'))->toContain('license');
});

test('returns 502 when no Melissa provider is configured', function () {
  Http::fake();

  $response = postJson('/v1/lead-quality/phone/validate', ['fingerprint' => 'fp-5', 'phone' => '8006354772'], phoneValidationHostHeader());

  $response->assertStatus(502);
  $response->assertJsonPath('success', false);
  Http::assertNothingSent();
});

test('returns 502 when configured Melissa provider is disabled', function () {
  Http::fake();
  LeadQualityProvider::factory()
    ->melissa()
    ->create(['is_enabled' => false]);

  $response = postJson('/v1/lead-quality/phone/validate', ['fingerprint' => 'fp-6', 'phone' => '8006354772'], phoneValidationHostHeader());

  $response->assertStatus(502);
  Http::assertNothingSent();
});

test('rejects invalid payloads (422)', function () {
  $response = postJson('/v1/lead-quality/phone/validate', [], phoneValidationHostHeader());

  $response->assertStatus(422);
  $response->assertJsonValidationErrors(['fingerprint', 'phone']);
});

test('caches identical phone calls within TTL — second call hits cache only', function () {
  Http::fake([
    'globalphone.melissadata.net/*' => Http::response(makeMelissaResponse('PS01,PS22'), 200),
  ]);
  LeadQualityProvider::factory()->melissa()->create();

  postJson(
    '/v1/lead-quality/phone/validate',
    ['fingerprint' => 'fp-cache-1', 'phone' => '8006354772', 'country' => 'US'],
    phoneValidationHostHeader(),
  )->assertOk();

  postJson(
    '/v1/lead-quality/phone/validate',
    ['fingerprint' => 'fp-cache-2', 'phone' => '8006354772', 'country' => 'US'],
    phoneValidationHostHeader(),
  )->assertOk();

  // Only one upstream HTTP call and one external_service_requests row.
  Http::assertSentCount(1);
  expect(ExternalServiceRequest::where('service_name', 'melissa')->where('operation', 'phone_validate')->count())->toBe(1);
});

test('cache key normalizes equivalent phone shapes', function () {
  Http::fake([
    'globalphone.melissadata.net/*' => Http::response(makeMelissaResponse('PS01,PS22'), 200),
  ]);
  LeadQualityProvider::factory()->melissa()->create();

  // Same number expressed two ways — should resolve to one cache entry.
  postJson(
    '/v1/lead-quality/phone/validate',
    ['fingerprint' => 'fp-norm-1', 'phone' => '(800) 635-4772', 'country' => 'US'],
    phoneValidationHostHeader(),
  )->assertOk();

  postJson(
    '/v1/lead-quality/phone/validate',
    ['fingerprint' => 'fp-norm-2', 'phone' => '+1 800 635 4772', 'country' => 'US'],
    phoneValidationHostHeader(),
  )->assertOk();

  Http::assertSentCount(1);
});
