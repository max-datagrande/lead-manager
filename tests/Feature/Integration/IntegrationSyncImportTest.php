<?php

use App\Models\Company;
use App\Models\Field;
use App\Models\IntegrationEnvironment;
use App\Models\IntegrationEnvironmentFieldHash;
use App\Models\IntegrationFieldMapping;
use Illuminate\Support\Facades\Http;

use function Pest\Laravel\postJson;

/**
 * Build a production-shaped export payload (the JSON IntegrationController::export()
 * returns) with explicit ids, so we can assert the import preserves the wiring.
 */
function exportPayload(Company $company, Field $field): array
{
  return [
    'integrations' => [
      [
        'id' => 100,
        'company_id' => $company->id,
        'name' => 'Buyer X',
        'type' => 'ping-post',
        'is_active' => true,
        'payload_transformer' => null,
        'use_custom_transformer' => false,
        'user_id' => null,
        'updated_user_id' => null,
      ],
    ],
    'environments' => [
      [
        'id' => 500,
        'integration_id' => 100,
        'environment' => 'production',
        'env_type' => 'post',
        'method' => 'POST',
        'url' => 'https://buyer.example.com/post',
        'request_body' => json_encode(['x' => '{$' . $field->id . '}']),
        'request_headers' => '{}',
        'content_type' => null,
        'authentication_type' => null,
      ],
    ],
    'fieldMappings' => [
      [
        'id' => 900,
        'integration_id' => 100,
        'field_id' => $field->id,
        'data_type' => 'string',
        'default_value' => 'fallback',
        'value_mapping' => ['auto' => 'AUTO', 'work' => 'WORK'],
      ],
    ],
    'fieldHashes' => [
      [
        'id' => 700,
        'integration_environment_id' => 500,
        'field_id' => $field->id,
        'is_hashed' => true,
        'hash_algorithm' => 'sha256',
        'hmac_secret' => null,
      ],
    ],
  ];
}

beforeEach(function () {
  // The import is gated by App::isLocal(); the test env is 'testing', so force 'local'.
  $this->app['env'] = 'local';
  config(['app.production_url' => 'https://prod.example.test']);
});

it('imports field mappings preserving value_mapping (not defaults, not through the reconciler)', function () {
  $company = Company::factory()->create();
  $field = Field::factory()->create(['possible_values' => ['auto', 'work']]);

  Http::fake(['*' => Http::response(exportPayload($company, $field))]);

  postJson(route('api.integrations.import'))->assertOk();

  $mapping = IntegrationFieldMapping::where('integration_id', 100)->where('field_id', $field->id)->first();
  expect($mapping)->not->toBeNull();
  expect($mapping->value_mapping)->toBe(['auto' => 'AUTO', 'work' => 'WORK']);
  expect($mapping->default_value)->toBe('fallback');
  expect($mapping->data_type)->toBe('string');
});

it('imports field hashes linked to the preserved environment id', function () {
  $company = Company::factory()->create();
  $field = Field::factory()->create(['possible_values' => ['auto', 'work']]);

  Http::fake(['*' => Http::response(exportPayload($company, $field))]);

  postJson(route('api.integrations.import'))->assertOk();

  // Environment id from prod must survive so the hash keeps matching.
  $environment = IntegrationEnvironment::find(500);
  expect($environment)->not->toBeNull();
  expect($environment->integration_id)->toBe(100);

  $hash = IntegrationEnvironmentFieldHash::where('integration_environment_id', 500)->where('field_id', $field->id)->first();
  expect($hash)->not->toBeNull();
  expect($hash->is_hashed)->toBeTrue();
  expect($hash->hash_algorithm)->toBe('sha256');
});

it('imports cleanly when prod has not deployed the new export (no mappings key)', function () {
  $company = Company::factory()->create();
  $field = Field::factory()->create();

  $legacyPayload = exportPayload($company, $field);
  unset($legacyPayload['fieldMappings'], $legacyPayload['fieldHashes']);

  Http::fake(['*' => Http::response($legacyPayload)]);

  postJson(route('api.integrations.import'))->assertOk();

  expect(IntegrationFieldMapping::count())->toBe(0);
  expect(IntegrationEnvironmentFieldHash::count())->toBe(0);
  // Environments still import as before.
  expect(IntegrationEnvironment::find(500))->not->toBeNull();
});
