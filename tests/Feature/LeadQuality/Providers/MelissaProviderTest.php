<?php

use App\Models\ExternalServiceRequest;
use App\Models\LeadQualityProvider;
use App\Services\LeadQuality\DTO\PhoneValidationResult;
use App\Services\LeadQuality\Providers\MelissaProvider;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

function melissaRecordResponse(string $results, array $extraRecord = [], array $extraRoot = []): array
{
  return array_merge(
    [
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
    ],
    $extraRoot,
  );
}

function melissaServiceErrorResponse(string $codes): array
{
  return [
    'Version' => '1.0',
    'TransmissionReference' => 'trace',
    'TransmissionResults' => $codes,
    'Records' => [],
  ];
}

beforeEach(function () {
  $this->provider = LeadQualityProvider::factory()->melissa()->create();
});

test('PS22 maps to valid_high_confidence', function () {
  Http::fake([
    'globalphone.melissadata.net/*' => Http::response(melissaRecordResponse('PS01,PS22'), 200),
  ]);

  $result = app(MelissaProvider::class)->validatePhone($this->provider, '8006354772', ['country' => 'US']);

  expect($result)
    ->toBeInstanceOf(PhoneValidationResult::class)
    ->and($result->valid)
    ->toBeTrue()
    ->and($result->classification)
    ->toBe(PhoneValidationResult::CLASS_VALID_HIGH_CONFIDENCE)
    ->and($result->normalizedPhone)
    ->toBe('+18006354772')
    ->and($result->country)
    ->toBe('US')
    ->and($result->resultCodes)
    ->toContain('PS22');
});

test('PS01 alone maps to valid_low_confidence', function () {
  Http::fake([
    'globalphone.melissadata.net/*' => Http::response(melissaRecordResponse('PS01'), 200),
  ]);

  $result = app(MelissaProvider::class)->validatePhone($this->provider, '8006354772');

  expect($result->valid)->toBeTrue()->and($result->classification)->toBe(PhoneValidationResult::CLASS_VALID_LOW_CONFIDENCE);
});

test('PE01 maps to invalid_phone', function () {
  Http::fake([
    'globalphone.melissadata.net/*' => Http::response(melissaRecordResponse('PE01'), 200),
  ]);

  $result = app(MelissaProvider::class)->validatePhone($this->provider, '0000');

  expect($result->valid)
    ->toBeFalse()
    ->and($result->classification)
    ->toBe(PhoneValidationResult::CLASS_INVALID_PHONE)
    ->and($result->error)
    ->not->toBeNull();
});

test('PE11 maps to disconnected_phone', function () {
  Http::fake([
    'globalphone.melissadata.net/*' => Http::response(melissaRecordResponse('PE11'), 200),
  ]);

  $result = app(MelissaProvider::class)->validatePhone($this->provider, '8006354772');

  expect($result->valid)->toBeFalse()->and($result->classification)->toBe(PhoneValidationResult::CLASS_DISCONNECTED_PHONE);
});

test('PS19 maps to high_risk_phone', function () {
  Http::fake([
    'globalphone.melissadata.net/*' => Http::response(melissaRecordResponse('PS01,PS19'), 200),
  ]);

  $result = app(MelissaProvider::class)->validatePhone($this->provider, '8006354772');

  expect($result->valid)->toBeFalse()->and($result->classification)->toBe(PhoneValidationResult::CLASS_HIGH_RISK_PHONE);
});

test('PS18 maps to compliance_risk and is treated as valid', function () {
  Http::fake([
    'globalphone.melissadata.net/*' => Http::response(melissaRecordResponse('PS01,PS18,PS22'), 200),
  ]);

  $result = app(MelissaProvider::class)->validatePhone($this->provider, '8006354772');

  expect($result->valid)->toBeTrue()->and($result->classification)->toBe(PhoneValidationResult::CLASS_COMPLIANCE_RISK);
});

test('PS09 line type is exposed as voip', function () {
  Http::fake([
    'globalphone.melissadata.net/*' => Http::response(melissaRecordResponse('PS01,PS09,PS22'), 200),
  ]);

  $result = app(MelissaProvider::class)->validatePhone($this->provider, '8006354772');

  expect($result->lineType)->toBe('voip');
});

test('GE05 service-level error returns validation_error', function () {
  Http::fake([
    'globalphone.melissadata.net/*' => Http::response(melissaServiceErrorResponse('GE05'), 200),
  ]);

  $result = app(MelissaProvider::class)->validatePhone($this->provider, '8006354772');

  expect($result->valid)
    ->toBeFalse()
    ->and($result->classification)
    ->toBe(PhoneValidationResult::CLASS_VALIDATION_ERROR)
    ->and($result->error)
    ->toContain('license');
});

test('connection timeout returns validation_error', function () {
  Http::fake(function () {
    throw new ConnectionException('Timeout');
  });

  $result = app(MelissaProvider::class)->validatePhone($this->provider, '8006354772');

  expect($result->valid)
    ->toBeFalse()
    ->and($result->classification)
    ->toBe(PhoneValidationResult::CLASS_VALIDATION_ERROR)
    ->and($result->error)
    ->toContain('timed out');
});

test('missing license_key returns validation_error without hitting Melissa', function () {
  $provider = LeadQualityProvider::factory()
    ->melissa()
    ->create([
      'credentials' => ['license_key' => ''],
    ]);
  Http::fake();

  $result = app(MelissaProvider::class)->validatePhone($provider, '8006354772');

  expect($result->valid)
    ->toBeFalse()
    ->and($result->classification)
    ->toBe(PhoneValidationResult::CLASS_VALIDATION_ERROR)
    ->and($result->error)
    ->toContain('license');

  Http::assertNothingSent();
});

test('empty phone returns invalid_phone without hitting Melissa', function () {
  Http::fake();

  $result = app(MelissaProvider::class)->validatePhone($this->provider, '');

  expect($result->valid)->toBeFalse()->and($result->classification)->toBe(PhoneValidationResult::CLASS_INVALID_PHONE);

  Http::assertNothingSent();
});

test('ExternalServiceRequest is persisted with masked license key', function () {
  Http::fake([
    'globalphone.melissadata.net/*' => Http::response(melissaRecordResponse('PS22'), 200),
  ]);

  app(MelissaProvider::class)->validatePhone($this->provider, '8006354772');

  $log = ExternalServiceRequest::query()
    ->where('module', 'lead_quality')
    ->where('service_name', 'melissa')
    ->where('operation', 'phone_validate')
    ->where('service_id', $this->provider->id)
    ->latest('id')
    ->first();

  expect($log)
    ->not->toBeNull()
    ->and($log->status)
    ->toBe('success')
    ->and($log->request_url)
    ->toContain('id=***')
    ->and($log->request_url)
    ->not->toContain($this->provider->credentials['license_key']);
});

test('testConnection succeeds with PS22 response', function () {
  Http::fake([
    'globalphone.melissadata.net/*' => Http::response(melissaRecordResponse('PS01,PS22'), 200),
  ]);

  $result = app(MelissaProvider::class)->testConnection($this->provider);

  expect($result->ok)->toBeTrue()->and($result->message)->toContain('Melissa');

  expect(
    ExternalServiceRequest::query()->where('service_name', 'melissa')->where('operation', 'test_connection')->where('status', 'success')->exists(),
  )->toBeTrue();
});

test('testConnection fails on GE05', function () {
  Http::fake([
    'globalphone.melissadata.net/*' => Http::response(melissaServiceErrorResponse('GE05'), 200),
  ]);

  $result = app(MelissaProvider::class)->testConnection($this->provider);

  expect($result->ok)->toBeFalse()->and($result->error)->toContain('license');
});

test('testConnection fails fast when license_key is missing', function () {
  $provider = LeadQualityProvider::factory()
    ->melissa()
    ->create([
      'credentials' => [],
    ]);
  Http::fake();

  $result = app(MelissaProvider::class)->testConnection($provider);

  expect($result->ok)->toBeFalse()->and($result->error)->toContain('credentials');

  Http::assertNothingSent();
});

test('async sendChallenge throws ProviderNotEnabledException', function () {
  $provider = LeadQualityProvider::factory()->melissa()->create();

  app(MelissaProvider::class)->sendChallenge($provider, new App\Models\LeadQualityValidationRule(), new App\Models\LeadQualityValidationLog(), []);
})->throws(App\Exceptions\LeadQuality\ProviderNotEnabledException::class);
