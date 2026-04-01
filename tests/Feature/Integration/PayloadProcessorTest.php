<?php

use App\Models\Field;
use App\Models\Integration;
use App\Models\IntegrationEnvironment;
use App\Services\PayloadProcessorService;

/**
 * Tests for PayloadProcessorService::resolveTokens()
 *
 * Template structure mirrors integration id=4 in production:
 * {
 *   "pubcampaignid": "{$cptype_id}",    -- string + value_mapping (AMS → 14489, AGPX → 14490)
 *   "consumerIP":    "{$client_ip_id}", -- string, no mapping
 *   "state":         "{$state_id}",     -- string, no mapping, default=FL
 *   "vertical":      2                  -- static integer (not a token)
 * }
 */

// ── Helpers ───────────────────────────────────────────────────────────────────

function makeProcessorFixture(): array
{
  $stateField    = Field::factory()->create(['name' => 'state']);
  $cptypeField   = Field::factory()->create(['name' => 'cptype']);
  $clientIpField = Field::factory()->create(['name' => 'client_ip']);
  $userAgentField = Field::factory()->create(['name' => 'user_agent']);

  $integration = Integration::factory()->create(['type' => 'offerwall']);

  $integration->tokenMappings()->createMany([
    [
      'field_id'      => $stateField->id,
      'data_type'     => 'string',
      'default_value' => 'FL',
      'value_mapping' => null,
    ],
    [
      'field_id'      => $cptypeField->id,
      'data_type'     => 'string',
      'default_value' => 'AGPX',
      'value_mapping' => ['AMS' => '14489', 'AGPX' => '14490', 'SPP' => '14558'],
    ],
    [
      'field_id'      => $clientIpField->id,
      'data_type'     => 'string',
      'default_value' => null,
      'value_mapping' => null,
    ],
    [
      'field_id'      => $userAgentField->id,
      'data_type'     => 'integer',
      'default_value' => '0',
      'value_mapping' => null,
    ],
  ]);

  $template = json_encode([
    'pubcampaignid' => '{$' . $cptypeField->id . '}',
    'vertical'      => 2,
    'consumerIP'    => '{$' . $clientIpField->id . '}',
    'state'         => '{$' . $stateField->id . '}',
    'score'         => '{$' . $userAgentField->id . '}',
  ]);

  $env = $integration->environments()->create([
    'env_type'        => 'offerwall',
    'environment'     => 'production',
    'url'             => 'https://example.com',
    'method'          => 'POST',
    'request_headers' => '[]',
    'request_body'    => $template,
  ]);

  $integration->load(['tokenMappings.field', 'environments.fieldHashes']);
  $env = $integration->environments->first();

  return compact('integration', 'env', 'stateField', 'cptypeField', 'clientIpField', 'userAgentField');
}

// ── Tests ─────────────────────────────────────────────────────────────────────

it('replaces string tokens with lead values', function () {
  ['integration' => $integration, 'env' => $env] = makeProcessorFixture();

  $result = app(PayloadProcessorService::class)->resolveTokens(
    $env->request_body,
    $integration,
    $env,
    ['state' => 'TX', 'cptype' => 'AMS', 'client_ip' => '1.2.3.4', 'user_agent' => '99'],
  );

  $payload = json_decode($result, true);

  expect($payload['state'])->toBe('TX')
    ->and($payload['consumerIP'])->toBe('1.2.3.4');
});

it('applies value_mapping before inserting the token', function () {
  ['integration' => $integration, 'env' => $env] = makeProcessorFixture();

  $result = app(PayloadProcessorService::class)->resolveTokens(
    $env->request_body,
    $integration,
    $env,
    ['cptype' => 'AMS', 'client_ip' => '1.2.3.4', 'state' => 'CA', 'user_agent' => '0'],
  );

  $payload = json_decode($result, true);

  expect($payload['pubcampaignid'])->toBe('14489'); // AMS → value_mapping → 14489
});

it('uses default_value when field is absent from lead data', function () {
  ['integration' => $integration, 'env' => $env] = makeProcessorFixture();

  $result = app(PayloadProcessorService::class)->resolveTokens(
    $env->request_body,
    $integration,
    $env,
    [], // no lead data at all
  );

  $payload = json_decode($result, true);

  // state default = FL, cptype default = AGPX → value_mapping → 14490
  expect($payload['state'])->toBe('FL')
    ->and($payload['pubcampaignid'])->toBe('14490');
});

it('strips quotes for integer data_type tokens in JSON', function () {
  ['integration' => $integration, 'env' => $env] = makeProcessorFixture();

  $result = app(PayloadProcessorService::class)->resolveTokens(
    $env->request_body,
    $integration,
    $env,
    ['user_agent' => '42', 'cptype' => 'AMS', 'client_ip' => '1.1.1.1', 'state' => 'FL'],
  );

  $payload = json_decode($result, true);

  // integer data_type → value must be a PHP int, not a string
  expect($payload['score'])->toBe(42)
    ->and($payload['score'])->toBeInt();
});

it('does not corrupt JSON when integer token appears inside a nested escaped string', function () {
  // Mirrors production integration 4: the "data" field is a JSON-encoded string
  // that itself contains {$user_agent_id} mapped as integer.
  // releaseTypes() must NOT strip the escaped quotes (\") inside that nested string.
  ['integration' => $integration, 'env' => $env, 'userAgentField' => $userAgentField, 'stateField' => $stateField, 'clientIpField' => $clientIpField] = makeProcessorFixture();

  // Build a template where an integer-typed token sits inside an escaped JSON string value
  $nestedTemplate = json_encode([
    'outer' => 'ok',
    'data'  => '{"ua":"{$' . $userAgentField->id . '}","state":"{$' . $stateField->id . '}"}',
  ]);

  $result = app(PayloadProcessorService::class)->resolveTokens(
    $nestedTemplate,
    $integration,
    $env,
    ['user_agent' => 'Mozilla/5.0', 'state' => 'TX', 'client_ip' => '1.1.1.1', 'cptype' => 'AMS'],
  );

  // JSON must be valid — the integer watermark inside the escaped string must not corrupt it
  expect(json_decode($result, true))->not->toBeNull()
    ->and(json_last_error())->toBe(JSON_ERROR_NONE);
});

it('resolves legacy {field_name} tokens in headers and URLs', function () {
  ['integration' => $integration, 'env' => $env] = makeProcessorFixture();

  // Headers using old-format tokens (not yet migrated to {$field_id})
  // Uses string-typed fields (client_ip, state) to avoid integer casting side-effects
  $headersTemplate = json_encode([
    'Content-Type' => 'application/json',
    'X-IP'         => '{client_ip}',
    'X-State'      => '{state}',
  ]);

  $result = app(PayloadProcessorService::class)->resolveTokens(
    $headersTemplate,
    $integration,
    $env,
    ['client_ip' => '9.9.9.9', 'state' => 'TX', 'user_agent' => '0', 'cptype' => 'AMS'],
  );

  $headers = json_decode($result, true);
  expect($headers['X-IP'])->toBe('9.9.9.9')
    ->and($headers['X-State'])->toBe('TX');
});

it('returns empty string for empty template', function () {
  ['integration' => $integration, 'env' => $env] = makeProcessorFixture();

  $result = app(PayloadProcessorService::class)->resolveTokens('', $integration, $env, []);

  expect($result)->toBe('');
});
