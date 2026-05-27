<?php

use App\Models\Field;
use App\Models\Integration;
use App\Models\IntegrationFieldMapping;
use App\Models\Workflow;
use App\Services\Integrations\IntegrationSchemaCollector;

function schemaCollector(): IntegrationSchemaCollector
{
  return app(IntegrationSchemaCollector::class);
}

/**
 * Put a production request_body referencing each field as a {$<id>} token.
 *
 * @param array<int, int> $fieldIds
 */
function envWithTokens(Integration $integration, array $fieldIds, ?string $rawBody = null): void
{
  if ($rawBody === null) {
    $body = [];
    foreach ($fieldIds as $id) {
      $body['k' . $id] = '{$' . $id . '}';
    }
    $rawBody = json_encode($body);
  }

  $integration->environments()->create([
    'environment' => 'production',
    'env_type' => 'post',
    'url' => 'https://buyer.example.com/post',
    'method' => 'POST',
    'request_headers' => '{}',
    'request_body' => $rawBody,
  ]);
}

function attachIntegration(Workflow $workflow, Integration $integration, int $position, bool $isActive = true): void
{
  $workflow->integrations()->attach($integration->id, [
    'position' => $position,
    'is_fallback' => false,
    'buyer_group' => 'primary',
    'is_active' => $isActive,
  ]);
}

// ── Integration shape ───────────────────────────────────────────────────────

it('includes only fields referenced as body tokens', function () {
  $used = Field::factory()->create(['name' => 'email']);
  Field::factory()->create(['name' => 'phone']); // not referenced
  $integration = Integration::factory()->create();
  envWithTokens($integration, [$used->id]);

  $result = schemaCollector()->forIntegration($integration);

  expect($result['schema'])->toHaveKey('email');
  expect($result['schema'])->not->toHaveKey('phone');
  expect($result['meta']['source'])->toBe('integration');
  expect($result['meta']['field_count'])->toBe(1);
});

it('emits enum and an in: rule for fields with possible_values', function () {
  $field = Field::factory()->create(['name' => 'gender', 'possible_values' => ['male', 'female']]);
  $integration = Integration::factory()->create();
  envWithTokens($integration, [$field->id]);

  $entry = schemaCollector()->forIntegration($integration)['schema']['gender'];

  expect($entry['enum'])->toBe(['male', 'female']);
  expect($entry['rules'])->toBe('required|string|in:male,female');
});

it('omits enum when possible_values is empty or null', function () {
  $empty = Field::factory()->create(['name' => 'notes', 'possible_values' => []]);
  $null = Field::factory()->create(['name' => 'comment', 'possible_values' => null]);
  $integration = Integration::factory()->create();
  envWithTokens($integration, [$empty->id, $null->id]);

  $schema = schemaCollector()->forIntegration($integration)['schema'];

  expect($schema['notes'])->not->toHaveKey('enum');
  expect($schema['comment'])->not->toHaveKey('enum');
  expect($schema['notes']['rules'])->toBe('required|string');
});

it('marks array fields with array + each + note (integration shape)', function () {
  $field = Field::factory()->create(['name' => 'injuries', 'is_array' => true, 'possible_values' => []]);
  $integration = Integration::factory()->create();
  envWithTokens($integration, [$field->id]);

  $entry = schemaCollector()->forIntegration($integration)['schema']['injuries'];

  expect($entry['array'])->toBeTrue();
  expect($entry['rules'])->toBe('required|array');
  expect($entry['each'])->toBe('string');
  expect($entry['note'])->toBe(IntegrationSchemaCollector::ARRAY_FIELD_NOTE);
});

it('includes in: inside each for array fields with possible_values', function () {
  $field = Field::factory()->create(['name' => 'tags', 'is_array' => true, 'possible_values' => ['a', 'b']]);
  $integration = Integration::factory()->create();
  envWithTokens($integration, [$field->id]);

  $entry = schemaCollector()->forIntegration($integration)['schema']['tags'];

  expect($entry['each'])->toBe('string|in:a,b');
  expect($entry['enum'])->toBe(['a', 'b']);
});

it('sets required=false + default when the mapping has a default_value', function () {
  $field = Field::factory()->create(['name' => 'country']);
  $integration = Integration::factory()->create();
  envWithTokens($integration, [$field->id]);
  IntegrationFieldMapping::create([
    'integration_id' => $integration->id,
    'field_id' => $field->id,
    'data_type' => 'string',
    'default_value' => 'US',
    'value_mapping' => null,
  ]);

  $entry = schemaCollector()->forIntegration($integration)['schema']['country'];

  expect($entry['required'])->toBeFalse();
  expect($entry['default'])->toBe('US');
  expect($entry['rules'])->toStartWith('nullable');
});

it('marks required=true when there is no default_value', function () {
  $field = Field::factory()->create(['name' => 'first_name']);
  $integration = Integration::factory()->create();
  envWithTokens($integration, [$field->id]);

  $entry = schemaCollector()->forIntegration($integration)['schema']['first_name'];

  expect($entry['required'])->toBeTrue();
  expect($entry['rules'])->toBe('required|string');
});

it('ignores the mapping data_type — base type stays string', function () {
  $field = Field::factory()->create(['name' => 'age']);
  $integration = Integration::factory()->create();
  envWithTokens($integration, [$field->id]);
  IntegrationFieldMapping::create([
    'integration_id' => $integration->id,
    'field_id' => $field->id,
    'data_type' => 'integer',
    'default_value' => null,
    'value_mapping' => null,
  ]);

  $entry = schemaCollector()->forIntegration($integration)['schema']['age'];

  expect($entry['rules'])->toBe('required|string');
});

it('records a warning for a token referencing a missing field, without throwing', function () {
  $integration = Integration::factory()->create();
  envWithTokens($integration, [], json_encode(['x' => '{$999999}']));

  $result = schemaCollector()->forIntegration($integration);

  expect($result['schema'])->toBe([]);
  expect($result['meta']['warnings'])->toContain('References missing field #999999');
});

// ── Workflow shape ────────────────────────────────────────────────────────────

it('aggregates every integration of a workflow, deduped, without required/default/rules', function () {
  $email = Field::factory()->create(['name' => 'email']);
  $state = Field::factory()->create(['name' => 'state', 'possible_values' => ['AL', 'AK']]);
  $injuries = Field::factory()->create(['name' => 'injuries', 'is_array' => true, 'possible_values' => []]);

  $i1 = Integration::factory()->create();
  envWithTokens($i1, [$email->id, $state->id]);
  $i2 = Integration::factory()->create();
  envWithTokens($i2, [$email->id, $injuries->id]); // email shared across both

  $workflow = Workflow::factory()->create();
  attachIntegration($workflow, $i1, 0);
  attachIntegration($workflow, $i2, 1);

  $result = schemaCollector()->forWorkflow($workflow);

  expect(array_keys($result['schema']))->toEqualCanonicalizing(['email', 'state', 'injuries']);
  expect($result['meta']['source'])->toBe('workflow');
  expect($result['meta']['integration_count'])->toBe(2);
  expect($result['meta']['field_count'])->toBe(3);

  expect($result['schema']['email'])->toBe(['array' => false]);
  expect($result['schema']['state'])->toBe(['array' => false, 'enum' => ['AL', 'AK']]);
  expect($result['schema']['injuries'])->toBe(['array' => true, 'note' => IntegrationSchemaCollector::ARRAY_FIELD_NOTE]);
});

it('includes integrations even when the workflow_buyers pivot is inactive', function () {
  $field = Field::factory()->create(['name' => 'email']);
  $integration = Integration::factory()->create();
  envWithTokens($integration, [$field->id]);

  $workflow = Workflow::factory()->create();
  attachIntegration($workflow, $integration, 0, isActive: false);

  $result = schemaCollector()->forWorkflow($workflow);

  expect($result['schema'])->toHaveKey('email');
  expect($result['meta']['integration_count'])->toBe(1);
});
