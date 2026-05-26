<?php

use App\Models\Company;
use App\Models\Field;
use App\Models\Integration;
use App\Models\IntegrationEnvironment;
use App\Models\IntegrationEnvironmentFieldHash;
use App\Models\IntegrationFieldMapping;
use App\Services\IntegrationService;
use App\Services\IntegrationServiceException;

function makeReconcilePayload(array $environments, array $overrides = []): array
{
  $company = Company::factory()->create();

  return array_merge(
    [
      'name' => 'Reconcile Integration',
      'type' => 'post-only',
      'is_active' => true,
      'company_id' => $company->id,
      'field_mappings' => [],
      'environments' => $environments,
    ],
    $overrides,
  );
}

function makeEnv(string $environment, ?string $requestBody, array $fieldHashes = [], string $envType = 'post'): array
{
  return [
    'env_type' => $envType,
    'environment' => $environment,
    'url' => 'https://buyer.example.com/post',
    'method' => 'POST',
    'request_headers' => [],
    'request_body' => $requestBody,
    'response_config' => null,
    'field_hashes' => $fieldHashes,
  ];
}

// ── createIntegration ─────────────────────────────────────────────────────────

it('creates field mappings derived from request_body tokens even when field_mappings payload is empty', function () {
  Field::factory()->create(['id' => 12, 'name' => 'email']);
  Field::factory()->create(['id' => 25, 'name' => 'phone']);

  $body = json_encode(['email' => '{$12}', 'phone' => '{$25}']);

  app(IntegrationService::class)->createIntegration(makeReconcilePayload([makeEnv('development', $body), makeEnv('production', $body)]));

  $integration = Integration::first();
  expect($integration->tokenMappings()->pluck('field_id')->sort()->values()->all())->toBe([12, 25]);
});

it('discards payload field_mappings whose field_id is not referenced in any body', function () {
  Field::factory()->create(['id' => 12]);
  Field::factory()->create(['id' => 71]);

  $body = json_encode(['email' => '{$12}']);

  $payload = makeReconcilePayload(
    [makeEnv('development', $body), makeEnv('production', $body)],
    [
      'field_mappings' => [['field_id' => 12, 'data_type' => 'string'], ['field_id' => 71, 'data_type' => 'integer', 'default_value' => '0']],
    ],
  );

  app(IntegrationService::class)->createIntegration($payload);

  $integration = Integration::first();
  expect($integration->tokenMappings()->pluck('field_id')->all())->toBe([12]);
});

it('throws when a body references a field id that does not exist', function () {
  Field::factory()->create(['id' => 12]);

  $body = json_encode(['email' => '{$12}', 'broken' => '{$999}']);

  expect(
    fn() => app(IntegrationService::class)->createIntegration(makeReconcilePayload([makeEnv('development', $body), makeEnv('production', $body)])),
  )->toThrow(IntegrationServiceException::class, '999');
});

// ── updateIntegration ─────────────────────────────────────────────────────────

it('deletes orphan mappings when the body removes a token', function () {
  Field::factory()->create(['id' => 12]);
  Field::factory()->create(['id' => 71]);

  $bodyBefore = json_encode(['email' => '{$12}', 'days' => '{$71}']);

  app(IntegrationService::class)->createIntegration(makeReconcilePayload([makeEnv('development', $bodyBefore), makeEnv('production', $bodyBefore)]));

  $integration = Integration::first();
  expect($integration->tokenMappings()->pluck('field_id')->sort()->values()->all())->toBe([12, 71]);

  $bodyAfter = json_encode(['email' => '{$12}']);
  $updatePayload = makeReconcilePayload([makeEnv('development', $bodyAfter), makeEnv('production', $bodyAfter)]);

  app(IntegrationService::class)->updateIntegration($integration->fresh(), $updatePayload);

  expect($integration->tokenMappings()->pluck('field_id')->all())->toBe([12]);
});

it('creates a missing mapping with defaults when a new token enters the body', function () {
  Field::factory()->create(['id' => 12]);
  Field::factory()->create(['id' => 103]);

  $bodyBefore = json_encode(['email' => '{$12}']);

  app(IntegrationService::class)->createIntegration(makeReconcilePayload([makeEnv('development', $bodyBefore), makeEnv('production', $bodyBefore)]));

  $integration = Integration::first();

  $bodyAfter = json_encode(['email' => '{$12}', 'days' => '{$103}']);
  app(IntegrationService::class)->updateIntegration(
    $integration->fresh(),
    makeReconcilePayload([makeEnv('development', $bodyAfter), makeEnv('production', $bodyAfter)]),
  );

  $newMapping = IntegrationFieldMapping::query()->where('integration_id', $integration->id)->where('field_id', 103)->first();
  expect($newMapping)->not->toBeNull();
  expect($newMapping->data_type)->toBe('string');
  expect($newMapping->default_value)->toBeNull();
  expect($newMapping->value_mapping)->toBeNull();
});

it('preserves overrides from the payload for tokens present in the body', function () {
  Field::factory()->create(['id' => 12]);

  $body = json_encode(['email' => '{$12}']);

  app(IntegrationService::class)->createIntegration(makeReconcilePayload([makeEnv('development', $body), makeEnv('production', $body)]));

  $integration = Integration::first();

  $updatePayload = makeReconcilePayload(
    [makeEnv('development', $body), makeEnv('production', $body)],
    ['field_mappings' => [['field_id' => 12, 'data_type' => 'integer', 'default_value' => 'fallback', 'value_mapping' => ['auto' => 'AUTOMOBILE']]]],
  );

  app(IntegrationService::class)->updateIntegration($integration->fresh(), $updatePayload);

  $mapping = $integration->tokenMappings()->first();
  expect($mapping->data_type)->toBe('integer');
  expect($mapping->default_value)->toBe('fallback');
  expect($mapping->value_mapping)->toBe(['auto' => 'AUTOMOBILE']);
});

it('is idempotent — saving twice without changes produces stable mappings', function () {
  Field::factory()->create(['id' => 12]);
  Field::factory()->create(['id' => 25]);

  $body = json_encode(['email' => '{$12}', 'phone' => '{$25}']);

  app(IntegrationService::class)->createIntegration(makeReconcilePayload([makeEnv('development', $body), makeEnv('production', $body)]));

  $integration = Integration::first();
  $idsBefore = $integration->tokenMappings()->pluck('id')->sort()->values()->all();

  app(IntegrationService::class)->updateIntegration(
    $integration->fresh(),
    makeReconcilePayload([makeEnv('development', $body), makeEnv('production', $body)]),
  );

  $idsAfter = $integration->tokenMappings()->pluck('id')->sort()->values()->all();
  expect($idsAfter)->toBe($idsBefore);
});

// ── field_hashes reconciliation ──────────────────────────────────────────────

it('reconciles field_hashes per env — drops orphans, creates missing with defaults', function () {
  Field::factory()->create(['id' => 12]);
  Field::factory()->create(['id' => 25]);

  $bodyBefore = json_encode(['email' => '{$12}']);
  $bodyAfter = json_encode(['email' => '{$12}', 'phone' => '{$25}']);

  app(IntegrationService::class)->createIntegration(
    makeReconcilePayload([
      makeEnv('development', $bodyBefore, [['field_id' => 12, 'is_hashed' => true, 'hash_algorithm' => 'md5']]),
      makeEnv('production', $bodyBefore, [['field_id' => 12, 'is_hashed' => true, 'hash_algorithm' => 'md5']]),
    ]),
  );

  $integration = Integration::first();

  app(IntegrationService::class)->updateIntegration(
    $integration->fresh(),
    makeReconcilePayload([
      makeEnv('development', $bodyAfter, [['field_id' => 12, 'is_hashed' => true, 'hash_algorithm' => 'md5']]),
      makeEnv('production', $bodyAfter, [['field_id' => 12, 'is_hashed' => true, 'hash_algorithm' => 'md5']]),
    ]),
  );

  $devEnv = $integration->environments()->where('environment', 'development')->first();
  $hashes = $devEnv->fieldHashes()->pluck('field_id')->sort()->values()->all();
  expect($hashes)->toBe([12, 25]);

  $newHash = $devEnv->fieldHashes()->where('field_id', 25)->first();
  expect($newHash->is_hashed)->toBeFalse();
  expect($newHash->hash_algorithm)->toBeNull();
});

it('drops a hash when its token disappears from the env body', function () {
  Field::factory()->create(['id' => 12]);
  Field::factory()->create(['id' => 25]);

  $bodyBefore = json_encode(['email' => '{$12}', 'phone' => '{$25}']);
  $bodyAfter = json_encode(['email' => '{$12}']);

  app(IntegrationService::class)->createIntegration(
    makeReconcilePayload([
      makeEnv('development', $bodyBefore, [
        ['field_id' => 12, 'is_hashed' => false],
        ['field_id' => 25, 'is_hashed' => true, 'hash_algorithm' => 'md5'],
      ]),
      makeEnv('production', $bodyBefore, [
        ['field_id' => 12, 'is_hashed' => false],
        ['field_id' => 25, 'is_hashed' => true, 'hash_algorithm' => 'md5'],
      ]),
    ]),
  );

  $integration = Integration::first();

  app(IntegrationService::class)->updateIntegration(
    $integration->fresh(),
    makeReconcilePayload([makeEnv('development', $bodyAfter), makeEnv('production', $bodyAfter)]),
  );

  $remainingHashFields = IntegrationEnvironmentFieldHash::query()->pluck('field_id')->unique()->sort()->values()->all();
  expect($remainingHashFields)->toBe([12]);
});

// ── duplicateIntegration ─────────────────────────────────────────────────────

it('duplicateIntegration produces a copy without orphan mappings from the source', function () {
  Field::factory()->create(['id' => 12]);
  Field::factory()->create(['id' => 71]);

  $body = json_encode(['email' => '{$12}']);

  // Create the source with a payload that wants both 12 and 71, but the body only has 12.
  // The reconciler will discard 71 immediately. To simulate a legacy orphan, we insert it
  // directly into the DB after creation.
  app(IntegrationService::class)->createIntegration(makeReconcilePayload([makeEnv('development', $body), makeEnv('production', $body)]));

  $source = Integration::first();

  IntegrationFieldMapping::create([
    'integration_id' => $source->id,
    'field_id' => 71,
    'data_type' => 'integer',
    'default_value' => '0',
    'value_mapping' => null,
  ]);

  expect($source->tokenMappings()->pluck('field_id')->sort()->values()->all())->toBe([12, 71]);

  app(IntegrationService::class)->duplicateIntegration($source->fresh());

  $duplicate = Integration::query()->where('id', '!=', $source->id)->first();
  expect($duplicate->tokenMappings()->pluck('field_id')->all())->toBe([12]);
});
