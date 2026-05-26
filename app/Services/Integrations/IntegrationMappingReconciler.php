<?php

namespace App\Services\Integrations;

use App\Models\Field;
use App\Models\Integration;
use App\Models\IntegrationEnvironment;
use App\Models\IntegrationEnvironmentFieldHash;
use App\Models\IntegrationFieldMapping;
use App\Services\IntegrationServiceException;
use App\Support\RequestBodyTokenExtractor;
use Illuminate\Support\Collection;
use Maxidev\Logger\TailLogger;

/**
 * Source-of-truth reconciler for the implicit link between request_body tokens
 * ({$<field_id>}) and the integration_field_mappings / integration_environment_field_hashes
 * tables.
 *
 * The bodies are authoritative: any field_id referenced as a token must have a
 * mapping row; any mapping row without a token in some body is dropped. The
 * payload from the form acts only as a dictionary of overrides keyed by field_id.
 */
class IntegrationMappingReconciler
{
  /**
   * @param  array<int, array{field_id: int, data_type?: string, default_value?: string|null, value_mapping?: array|null}>  $mappingOverrides
   * @param  array<int, array<int, array{field_id: int, is_hashed?: bool, hash_algorithm?: string|null, hmac_secret?: string|null}>>  $hashOverridesByEnv  Keyed by environment id.
   */
  public function reconcile(Integration $integration, array $mappingOverrides = [], array $hashOverridesByEnv = []): void
  {
    $integration->load('environments', 'tokenMappings');

    $tokens = $integration->getAllRequestBodyTokens();

    $this->assertTokensReferenceExistingFields($integration, $tokens);

    $this->reconcileMappings($integration, $tokens, $mappingOverrides);

    foreach ($integration->environments as $env) {
      $this->reconcileHashes($env, $hashOverridesByEnv[$env->id] ?? []);
    }
  }

  /**
   * @param  array<int, int>  $tokens
   */
  private function assertTokensReferenceExistingFields(Integration $integration, array $tokens): void
  {
    if (empty($tokens)) {
      return;
    }

    $existing = Field::query()->whereIn('id', $tokens)->pluck('id')->all();
    $missing = array_values(array_diff($tokens, $existing));

    if (empty($missing)) {
      return;
    }

    $first = $missing[0];
    throw new IntegrationServiceException("Token {\$" . $first . '} en el request_body referencia un field que no existe.', [
      'integration_id' => $integration->id,
      'missing_field_ids' => $missing,
    ]);
  }

  /**
   * @param  array<int, int>  $tokens
   * @param  array<int, array{field_id: int, data_type?: string, default_value?: string|null, value_mapping?: array|null}>  $overrides
   */
  private function reconcileMappings(Integration $integration, array $tokens, array $overrides): void
  {
    $current = $integration->tokenMappings->keyBy('field_id');
    $tokenSet = array_fill_keys($tokens, true);
    $overridesByField = collect($overrides)->keyBy('field_id');

    $orphans = $current->reject(fn($mapping, $fieldId) => isset($tokenSet[$fieldId]));

    foreach ($orphans as $orphan) {
      $hadDefault = $orphan->default_value !== null && $orphan->default_value !== '';
      $hadValueMapping = is_array($orphan->value_mapping) && !empty($orphan->value_mapping);

      if ($hadDefault || $hadValueMapping) {
        TailLogger::saveLog('Deleted orphan field mapping with user configuration.', 'integrations/reconcile', 'info', [
          'integration_id' => $integration->id,
          'action' => 'deleted_with_data',
          'field_id' => $orphan->field_id,
          'had_default' => $hadDefault,
          'had_value_mapping' => $hadValueMapping,
        ]);
      }
    }

    if ($orphans->isNotEmpty()) {
      IntegrationFieldMapping::query()
        ->where('integration_id', $integration->id)
        ->whereIn('field_id', $orphans->pluck('field_id')->all())
        ->delete();
    }

    foreach ($tokens as $fieldId) {
      $override = $overridesByField->get($fieldId);
      $attributes = [
        'data_type' => $override['data_type'] ?? 'string',
        'default_value' => $override['default_value'] ?? null,
        'value_mapping' => !empty($override['value_mapping']) ? $override['value_mapping'] : null,
      ];

      $wasNew = !$current->has($fieldId);

      IntegrationFieldMapping::updateOrCreate(['integration_id' => $integration->id, 'field_id' => $fieldId], $attributes);

      if ($wasNew) {
        TailLogger::saveLog('Created missing field mapping with defaults.', 'integrations/reconcile', 'info', [
          'integration_id' => $integration->id,
          'action' => 'created_with_defaults',
          'field_id' => $fieldId,
        ]);
      }
    }
  }

  /**
   * @param  array<int, array{field_id: int, is_hashed?: bool, hash_algorithm?: string|null, hmac_secret?: string|null}>  $overrides
   */
  private function reconcileHashes(IntegrationEnvironment $env, array $overrides): void
  {
    $tokens = RequestBodyTokenExtractor::extractFieldIds($env->request_body);
    $env->load('fieldHashes');
    $current = $env->fieldHashes->keyBy('field_id');
    $tokenSet = array_fill_keys($tokens, true);
    $overridesByField = collect($overrides)->keyBy('field_id');

    $orphans = $current->reject(fn($hash, $fieldId) => isset($tokenSet[$fieldId]));

    foreach ($orphans as $orphan) {
      if ($orphan->is_hashed) {
        TailLogger::saveLog('Deleted orphan field hash with hashing enabled.', 'integrations/reconcile', 'info', [
          'integration_id' => $env->integration_id,
          'integration_environment_id' => $env->id,
          'action' => 'deleted_hash_with_data',
          'field_id' => $orphan->field_id,
          'hash_algorithm' => $orphan->hash_algorithm,
        ]);
      }
    }

    if ($orphans->isNotEmpty()) {
      IntegrationEnvironmentFieldHash::query()
        ->where('integration_environment_id', $env->id)
        ->whereIn('field_id', $orphans->pluck('field_id')->all())
        ->delete();
    }

    foreach ($tokens as $fieldId) {
      $override = $overridesByField->get($fieldId);
      $attributes = [
        'is_hashed' => $override['is_hashed'] ?? false,
        'hash_algorithm' => $override['hash_algorithm'] ?? null,
        'hmac_secret' => $override['hmac_secret'] ?? null,
      ];

      IntegrationEnvironmentFieldHash::updateOrCreate(['integration_environment_id' => $env->id, 'field_id' => $fieldId], $attributes);
    }
  }
}
