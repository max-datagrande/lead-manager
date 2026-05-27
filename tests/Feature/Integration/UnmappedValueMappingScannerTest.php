<?php

use App\Models\Field;
use App\Models\Integration;
use App\Models\IntegrationFieldMapping;
use App\Models\IntegrationMappingFinding;
use App\Services\Integrations\UnmappedValueMappingScanner;

/**
 * Put a {$<fieldId>} token in a production request_body so the scanner (which
 * treats the bodies as source of truth) sees the field in use.
 */
function putTokenInBody(Integration $integration, Field $field): void
{
  $integration->environments()->create([
    'environment' => 'production',
    'env_type' => 'post',
    'url' => 'https://buyer.example.com/post',
    'method' => 'POST',
    'request_headers' => '{}',
    'request_body' => json_encode(['x' => '{$' . $field->id . '}']),
  ]);
}

function makeMapping(Integration $integration, Field $field, ?array $valueMapping = null): IntegrationFieldMapping
{
  return IntegrationFieldMapping::create([
    'integration_id' => $integration->id,
    'field_id' => $field->id,
    'data_type' => 'string',
    'default_value' => null,
    'value_mapping' => $valueMapping,
  ]);
}

function runScan(): array
{
  return app(UnmappedValueMappingScanner::class)->scan();
}

it('detects a body token with possible_values and no mapping row (integration-31 case)', function () {
  $integration = Integration::factory()->create(['is_active' => true]);
  $field = Field::factory()->create(['name' => 'accident_when_in_days', 'possible_values' => ['0-7', '8-30']]);
  putTokenInBody($integration, $field);
  // No mapping row at all — mirrors integrations not re-saved since the reconcile.

  $result = runScan();

  expect($result['new_count'])->toBe(1);
  $finding = IntegrationMappingFinding::first();
  expect($finding->integration_id)->toBe($integration->id);
  expect($finding->field_id)->toBe($field->id);
  expect($finding->status)->toBe(IntegrationMappingFinding::STATUS_OPEN);
});

it('detects a body token whose mapping row has an empty value_mapping', function () {
  $integration = Integration::factory()->create(['is_active' => true]);
  $field = Field::factory()->create(['possible_values' => ['auto', 'work']]);
  putTokenInBody($integration, $field);
  makeMapping($integration, $field, null);

  expect(runScan()['new_count'])->toBe(1);
});

it('ignores inactive integrations', function () {
  $integration = Integration::factory()->create(['is_active' => false]);
  $field = Field::factory()->create(['possible_values' => ['auto', 'work']]);
  putTokenInBody($integration, $field);

  expect(runScan()['new_count'])->toBe(0);
  expect(IntegrationMappingFinding::count())->toBe(0);
});

it('ignores fields without possible_values', function () {
  $integration = Integration::factory()->create(['is_active' => true]);
  $field = Field::factory()->create(['possible_values' => null]);
  putTokenInBody($integration, $field);

  expect(runScan()['new_count'])->toBe(0);
  expect(IntegrationMappingFinding::count())->toBe(0);
});

it('ignores a field whose token is not present in any body', function () {
  $integration = Integration::factory()->create(['is_active' => true]);
  $field = Field::factory()->create(['possible_values' => ['auto', 'work']]);
  // Mapping row exists but no body references it — an orphan, not an alert.
  makeMapping($integration, $field, null);

  expect(runScan()['new_count'])->toBe(0);
  expect(IntegrationMappingFinding::count())->toBe(0);
});

it('ignores fields whose value_mapping is already configured', function () {
  $integration = Integration::factory()->create(['is_active' => true]);
  $field = Field::factory()->create(['possible_values' => ['auto', 'work']]);
  putTokenInBody($integration, $field);
  makeMapping($integration, $field, ['auto' => 'AUTOMOBILE']);

  expect(runScan()['new_count'])->toBe(0);
  expect(IntegrationMappingFinding::count())->toBe(0);
});

it('does not duplicate a finding on a second scan; refreshes last_seen', function () {
  $integration = Integration::factory()->create(['is_active' => true]);
  $field = Field::factory()->create(['possible_values' => ['auto', 'work']]);
  putTokenInBody($integration, $field);

  runScan();
  $firstSeen = IntegrationMappingFinding::first()->last_seen_at;

  $second = runScan();

  expect($second['new_count'])->toBe(0);
  expect(IntegrationMappingFinding::count())->toBe(1);
  expect(IntegrationMappingFinding::first()->last_seen_at->gte($firstSeen))->toBeTrue();
});

it('auto-resolves a finding once the value_mapping is configured', function () {
  $integration = Integration::factory()->create(['is_active' => true]);
  $field = Field::factory()->create(['possible_values' => ['auto', 'work']]);
  putTokenInBody($integration, $field);
  $mapping = makeMapping($integration, $field, null);

  runScan();
  expect(IntegrationMappingFinding::first()->status)->toBe(IntegrationMappingFinding::STATUS_OPEN);

  $mapping->update(['value_mapping' => ['auto' => 'AUTOMOBILE']]);
  $result = runScan();

  expect($result['resolved'])->toBe(1);
  $finding = IntegrationMappingFinding::first();
  expect($finding->status)->toBe(IntegrationMappingFinding::STATUS_RESOLVED);
  expect($finding->resolved_at)->not->toBeNull();
});

it('reopens a resolved finding if the problem reappears', function () {
  $integration = Integration::factory()->create(['is_active' => true]);
  $field = Field::factory()->create(['possible_values' => ['auto', 'work']]);
  putTokenInBody($integration, $field);
  $mapping = makeMapping($integration, $field, null);

  runScan();
  $mapping->update(['value_mapping' => ['auto' => 'AUTOMOBILE']]);
  runScan();
  expect(IntegrationMappingFinding::first()->status)->toBe(IntegrationMappingFinding::STATUS_RESOLVED);

  $mapping->update(['value_mapping' => null]);
  $result = runScan();

  expect($result['new_count'])->toBe(1);
  expect(IntegrationMappingFinding::first()->status)->toBe(IntegrationMappingFinding::STATUS_OPEN);
});

it('respects an ignored finding — does not reopen nor count as new', function () {
  $integration = Integration::factory()->create(['is_active' => true]);
  $field = Field::factory()->create(['possible_values' => ['auto', 'work']]);
  putTokenInBody($integration, $field);

  runScan();
  IntegrationMappingFinding::first()->update(['status' => IntegrationMappingFinding::STATUS_IGNORED]);

  $result = runScan();

  expect($result['new_count'])->toBe(0);
  expect(IntegrationMappingFinding::first()->status)->toBe(IntegrationMappingFinding::STATUS_IGNORED);
});

// ── Command ───────────────────────────────────────────────────────────────────

it('command persists findings and enters the Slack notification branch on new findings', function () {
  $integration = Integration::factory()->create(['is_active' => true]);
  $field = Field::factory()->create(['possible_values' => ['auto', 'work']]);
  putTokenInBody($integration, $field);

  // Non-production in tests → the branch is reached but the send is skipped.
  $this->artisan('integrations:scan-unmapped-value-mappings')->expectsOutputToContain('Slack notification skipped')->assertExitCode(0);

  expect(IntegrationMappingFinding::where('status', IntegrationMappingFinding::STATUS_OPEN)->count())->toBe(1);
});

it('command skips the Slack branch entirely with --no-slack', function () {
  $integration = Integration::factory()->create(['is_active' => true]);
  $field = Field::factory()->create(['possible_values' => ['auto', 'work']]);
  putTokenInBody($integration, $field);

  $this->artisan('integrations:scan-unmapped-value-mappings --no-slack')->doesntExpectOutputToContain('Slack notification')->assertExitCode(0);

  expect(IntegrationMappingFinding::count())->toBe(1);
});
