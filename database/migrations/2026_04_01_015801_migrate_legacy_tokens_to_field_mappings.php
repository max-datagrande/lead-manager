<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Maxidev\Logger\TailLogger;

/**
 * S7 — Migración de datos: tokens legacy → sistema relacional {$field_id}
 *
 * Convierte todos los request_body de integration_environments que usen el
 * formato viejo ({field_name} o {int:field_name}) al nuevo formato {$field_id},
 * y crea las filas correspondientes en integration_field_mappings.
 *
 * Lógica por integration:
 *  1. Recopilar todos los field names únicos de los request_body (ambos formatos)
 *     y de request_mapping_config (para rescatar default_value / value_mapping).
 *  2. Por cada field name → buscar en fields. Si no existe → TailLogger warning, skip.
 *  3. Determinar data_type:
 *       - Si aparece como {int:field_name} en algún body → 'integer' (prioridad)
 *       - Si no, usar request_mapping_config[name].dataType mapeado a snake_case
 *       - Fallback → 'string'
 *  4. Crear fila en integration_field_mappings (insertOrIgnore para idempotencia).
 *  5. En cada request_body: reemplazar {int:field_name} y {field_name} por {$field_id}.
 */
return new class extends Migration {
  /** Mapeo camelCase dataType → columna data_type del modelo */
  private const TYPE_MAP = [
    'integer' => 'integer',
    'int' => 'integer',
    'float' => 'float',
    'boolean' => 'boolean',
    'bool' => 'boolean',
    'string' => 'string',
  ];

  public function up(): void
  {
    // Cargar todos los fields de una vez → lookup por nombre O(1)
    $fieldsByName = DB::table('fields')
      ->get(['id', 'name'])
      ->keyBy('name');

    $integrations = DB::table('integrations')->get(['id', 'name', 'request_mapping_config']);

    foreach ($integrations as $integration) {
      $this->migrateIntegration($integration, $fieldsByName);
    }
  }

  public function down(): void
  {
    // La migración de datos no es reversible de forma segura.
    // down() no hace nada — restaurar desde backup si es necesario.
  }

  // ─────────────────────────────────────────────────────────────────────────

  private function migrateIntegration(object $integration, \Illuminate\Support\Collection $fieldsByName): void
  {
    $mappingConfig = $integration->request_mapping_config ? json_decode($integration->request_mapping_config, true) : [];

    $environments = DB::table('integration_environments')
      ->where('integration_id', $integration->id)
      ->get(['id', 'request_body']);

    // ── Paso 1: recopilar todos los field names únicos ────────────────────
    // intFieldNames: field names que aparecen con prefijo {int:...}
    $intFieldNames = [];
    // allFieldNames: todos los field names encontrados (cualquier formato)
    $allFieldNames = [];

    foreach ($environments as $env) {
      if (!$env->request_body) {
        continue;
      }
      $body = $env->request_body;

      // {int:field_name}
      preg_match_all('/\{int:([a-z_0-9]+)\}/', $body, $intMatches);
      foreach ($intMatches[1] as $name) {
        $intFieldNames[$name] = true;
        $allFieldNames[$name] = true;
      }

      // {field_name} (solo letras/guión_bajo/números, sin prefijo)
      preg_match_all('/\{([a-z_0-9]+)\}/', $body, $plainMatches);
      foreach ($plainMatches[1] as $name) {
        $allFieldNames[$name] = true;
      }
    }

    // También incluir keys de request_mapping_config que no aparezcan en bodies
    foreach (array_keys($mappingConfig) as $name) {
      $allFieldNames[$name] = true;
    }

    if (empty($allFieldNames)) {
      return;
    }

    // ── Paso 2-4: resolver cada field name y crear fila en la tabla ───────
    // fieldIdMap: field_name → field_id (solo los que existen)
    $fieldIdMap = [];

    foreach (array_keys($allFieldNames) as $fieldName) {
      $field = $fieldsByName->get($fieldName);

      if (!$field) {
        TailLogger::saveLog("Token migration: field '{$fieldName}' not found in fields table — skipped", 'migrations/token-refactor', 'warning', [
          'integration_id' => $integration->id,
          'integration_name' => $integration->name,
          'field_name' => $fieldName,
        ]);
        continue;
      }

      $config = $mappingConfig[$fieldName] ?? [];
      $dataType = $this->resolveDataType($fieldName, $config, $intFieldNames);
      $defaultVal = isset($config['defaultValue']) ? (string) $config['defaultValue'] : null;
      $valueMap = !empty($config['value_mapping']) ? json_encode($config['value_mapping']) : null;

      DB::table('integration_field_mappings')->insertOrIgnore([
        'integration_id' => $integration->id,
        'field_id' => $field->id,
        'data_type' => $dataType,
        'default_value' => $defaultVal,
        'value_mapping' => $valueMap,
        'created_at' => now(),
        'updated_at' => now(),
      ]);

      $fieldIdMap[$fieldName] = $field->id;
    }

    if (empty($fieldIdMap)) {
      return;
    }

    // ── Paso 5: reemplazar tokens en cada request_body ────────────────────
    foreach ($environments as $env) {
      if (!$env->request_body) {
        continue;
      }

      $body = $env->request_body;
      $updated = $body;

      foreach ($fieldIdMap as $fieldName => $fieldId) {
        // Primero el formato con prefijo (más específico)
        $updated = str_replace('{int:' . $fieldName . '}', '{$' . $fieldId . '}', $updated);
        // Luego el formato plano
        $updated = str_replace('{' . $fieldName . '}', '{$' . $fieldId . '}', $updated);
      }

      if ($updated !== $body) {
        DB::table('integration_environments')
          ->where('id', $env->id)
          ->update(['request_body' => $updated]);
      }
    }
  }

  /**
   * Determina el data_type final para una field mapping.
   *
   * Prioridad:
   *  1. Si el field name aparece como {int:name} en algún body → 'integer'
   *  2. request_mapping_config[name].dataType (normalizado)
   *  3. 'string' por defecto
   */
  private function resolveDataType(string $fieldName, array $config, array $intFieldNames): string
  {
    if (isset($intFieldNames[$fieldName])) {
      return 'integer';
    }

    $raw = strtolower(trim($config['dataType'] ?? ''));

    return self::TYPE_MAP[$raw] ?? 'string';
  }
};
