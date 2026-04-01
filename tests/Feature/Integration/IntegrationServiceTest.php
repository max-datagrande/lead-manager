<?php

use App\Models\Company;
use App\Models\Field;
use App\Models\Integration;
use App\Models\IntegrationFieldMapping;
use App\Services\IntegrationService;
use App\Services\IntegrationServiceException;

// ── Helpers ───────────────────────────────────────────────────────────────────

function makeIntegrationPayload(array $overrides = []): array
{
  $company = Company::factory()->create();

  return array_merge([
    'name' => 'Test Integration',
    'type' => 'post-only',
    'is_active' => true,
    'company_id' => $company->id,
    'field_mappings' => [],
    'environments' => [
      [
        'env_type' => 'post',
        'environment' => 'development',
        'url' => 'https://buyer.example.com/post',
        'method' => 'POST',
        'request_headers' => [],
        'request_body' => null,
        'response_config' => null,
        'field_hashes' => [],
      ],
      [
        'env_type' => 'post',
        'environment' => 'production',
        'url' => 'https://buyer.example.com/post',
        'method' => 'POST',
        'request_headers' => [],
        'request_body' => null,
        'response_config' => null,
        'field_hashes' => [],
      ],
    ],
  ], $overrides);
}

// ── createIntegration ─────────────────────────────────────────────────────────

it('creates an integration with field_mappings', function () {
  $field = Field::factory()->create(['name' => 'email']);

  $service = app(IntegrationService::class);
  $service->createIntegration(makeIntegrationPayload([
    'field_mappings' => [
      ['field_id' => $field->id, 'data_type' => 'string', 'default_value' => null, 'value_mapping' => null],
    ],
  ]));

  $integration = Integration::latest()->first();

  expect($integration->tokenMappings)->toHaveCount(1)
    ->and($integration->tokenMappings->first()->field_id)->toBe($field->id)
    ->and($integration->tokenMappings->first()->data_type)->toBe('string');
});

it('creates field_mapping with value_mapping', function () {
  $field = Field::factory()->create(['name' => 'homeowner']);

  $service = app(IntegrationService::class);
  $service->createIntegration(makeIntegrationPayload([
    'field_mappings' => [
      [
        'field_id' => $field->id,
        'data_type' => 'string',
        'default_value' => 'own',
        'value_mapping' => ['own' => '1', 'rent' => '0'],
      ],
    ],
  ]));

  $mapping = IntegrationFieldMapping::where('field_id', $field->id)->first();

  expect($mapping->default_value)->toBe('own')
    ->and($mapping->value_mapping)->toBe(['own' => '1', 'rent' => '0']);
});

it('creates field_hashes per environment', function () {
  $field = Field::factory()->create(['name' => 'email']);
  $company = Company::factory()->create();

  $service = app(IntegrationService::class);
  $service->createIntegration([
    'name' => 'Hash Test',
    'type' => 'post-only',
    'is_active' => true,
    'company_id' => $company->id,
    'field_mappings' => [
      ['field_id' => $field->id, 'data_type' => 'string', 'default_value' => null, 'value_mapping' => null],
    ],
    'environments' => [
      [
        'env_type' => 'post',
        'environment' => 'production',
        'url' => 'https://buyer.example.com/post',
        'method' => 'POST',
        'request_headers' => [],
        'request_body' => null,
        'response_config' => null,
        'field_hashes' => [
          ['field_id' => $field->id, 'is_hashed' => true, 'hash_algorithm' => 'md5', 'hmac_secret' => null],
        ],
      ],
    ],
  ]);

  $integration = Integration::latest()->first();
  $prodEnv = $integration->environments->where('environment', 'production')->first();

  expect($prodEnv->fieldHashes)->toHaveCount(1)
    ->and($prodEnv->fieldHashes->first()->is_hashed)->toBeTrue()
    ->and($prodEnv->fieldHashes->first()->hash_algorithm)->toBe('md5');
});

it('fails validation when field_id does not exist', function () {
  $service = app(IntegrationService::class);

  expect(fn () => $service->createIntegration(makeIntegrationPayload([
    'field_mappings' => [
      ['field_id' => 99999, 'data_type' => 'string', 'default_value' => null],
    ],
  ])))->toThrow(IntegrationServiceException::class);
});

// ── updateIntegration ─────────────────────────────────────────────────────────

it('syncs field_mappings on update — adds new, removes old', function () {
  $fieldA = Field::factory()->create(['name' => 'email']);
  $fieldB = Field::factory()->create(['name' => 'phone']);

  $service = app(IntegrationService::class);
  $service->createIntegration(makeIntegrationPayload([
    'field_mappings' => [
      ['field_id' => $fieldA->id, 'data_type' => 'string', 'default_value' => null, 'value_mapping' => null],
    ],
  ]));

  $integration = Integration::latest()->first();
  expect($integration->tokenMappings)->toHaveCount(1);

  // Update: swap fieldA out, add fieldB
  $service->updateIntegration($integration, makeIntegrationPayload([
    'company_id' => $integration->company_id,
    'field_mappings' => [
      ['field_id' => $fieldB->id, 'data_type' => 'integer', 'default_value' => '0', 'value_mapping' => null],
    ],
  ]));

  $integration->refresh();

  expect($integration->tokenMappings)->toHaveCount(1)
    ->and($integration->tokenMappings->first()->field_id)->toBe($fieldB->id)
    ->and($integration->tokenMappings->first()->data_type)->toBe('integer')
    ->and($integration->tokenMappings->first()->default_value)->toBe('0');
});

it('clears all field_mappings when updated with empty array', function () {
  $field = Field::factory()->create(['name' => 'zip_code']);

  $service = app(IntegrationService::class);
  $service->createIntegration(makeIntegrationPayload([
    'field_mappings' => [
      ['field_id' => $field->id, 'data_type' => 'string', 'default_value' => null, 'value_mapping' => null],
    ],
  ]));

  $integration = Integration::latest()->first();
  expect($integration->tokenMappings)->toHaveCount(1);

  $service->updateIntegration($integration, makeIntegrationPayload([
    'company_id' => $integration->company_id,
    'field_mappings' => [],
  ]));

  $integration->refresh();
  expect($integration->tokenMappings)->toHaveCount(0);
});

// ── duplicateIntegration ──────────────────────────────────────────────────────

it('duplicates integration including field_mappings', function () {
  $field = Field::factory()->create(['name' => 'state']);

  $service = app(IntegrationService::class);
  $service->createIntegration(makeIntegrationPayload([
    'field_mappings' => [
      ['field_id' => $field->id, 'data_type' => 'string', 'default_value' => 'FL', 'value_mapping' => null],
    ],
  ]));

  $original = Integration::latest('id')->first();
  $service->duplicateIntegration($original, 'Copy of Test');

  $copy = Integration::latest('id')->first();

  expect($copy->id)->not->toBe($original->id)
    ->and($copy->name)->toBe('Copy of Test')
    ->and($copy->is_active)->toBeFalsy()
    ->and($copy->tokenMappings)->toHaveCount(1)
    ->and($copy->tokenMappings->first()->field_id)->toBe($field->id)
    ->and($copy->tokenMappings->first()->default_value)->toBe('FL');
});
