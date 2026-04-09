<?php

namespace App\Services;

use App\Models\Integration;
use App\Models\IntegrationEnvironment;
use Maxidev\Logger\TailLogger;

/**
 * Servicio PayloadProcessorService
 * * Este servicio se encarga de transformar plantillas JSON con tokens dinámicos en estructuras
 * de datos válidas, manejando la conversión de tipos (casting) y la limpieza de estructuras
 * anidadas para asegurar la compatibilidad con proveedores externos.
 */
class PayloadProcessorService
{
  /**
   * Procesa un JSON template con tokens y los convierte a sus tipos reales.
   * * @param string $template El JSON con tokens como "{int:age}", "{bool:status}" o "{dob}"
   * @param array $data Los datos reales para reemplazar en los tokens.
   * @return string JSON final procesado, tipado y formateado.
   * @throws \Exception Si el JSON resultante no es válido.
   */
  public function process(string $template, array $data): string
  {
    // 1. Reemplazo inicial con marcas de agua temporales para identificar tipos de datos.
    $processed = $this->injectTokens($template, $data);
    // 2. Limpieza de comillas mediante Regex para convertir strings en tipos reales (int, bool, float).
    $processed = $this->releaseTypes($processed);

    // 3. Validación y Normalización Final.
    $arrayData = json_decode($processed, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
      throw new \Exception('Error processing JSON: ' . json_last_error_msg());
    }

    return json_encode($arrayData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
  }
  public function processUrl(string $url, array $data): string
  {
    $processed = $this->injectTokens($url, $data);
    return $this->releaseTypes($processed);
  }

  public static function generateReplacements(array $leadData, array $mappingConfig): array
  {
    $originalValues = [];
    $mappedValues = [];
    $finalReplacements = [];

    foreach ($mappingConfig as $tokenName => $config) {
      $value = $leadData[$tokenName] ?? ($config['defaultValue'] ?? '');

      $originalValues[$tokenName] = $value;

      if (isset($config['value_mapping']) && array_key_exists($value, $config['value_mapping'])) {
        $value = $config['value_mapping'][$value];
      }

      $mappedValues[$tokenName] = $value;

      if (is_array($value) || is_object($value)) {
        $value = json_encode($value);
      }

      $finalReplacements[$tokenName] = (string) $value;
    }

    return [
      'originalValues' => $originalValues,
      'mappedValues' => $mappedValues,
      'finalReplacements' => $finalReplacements,
    ];
  }

  /**
   * Resolve {$field_id} tokens in a template using the relational token system.
   *
   * Replaces each {$N} placeholder with the lead's field value, applying
   * value_mapping, data_type casting and per-environment hash config in order.
   *
   * @param  string                  $template    request_body template with {$N} tokens
   * @param  Integration             $integration With tokenMappings + field eager-loaded
   * @param  IntegrationEnvironment  $environment With fieldHashes eager-loaded
   * @param  array<string, mixed>    $leadData    Lead fields keyed by field name
   */
  /**
   * Build the replacement map for all tokens in one pass.
   *
   * Returns an array keyed by both token formats:
   *   '{$field_id}'  => watermarked_value   (new format)
   *   '{field_name}' => watermarked_value   (legacy format — headers/URLs)
   *
   * Call this once per request, then pass the map to applyReplacements() for
   * each template (body, headers, URL) to avoid redundant recalculation.
   *
   * @param  Integration             $integration  With tokenMappings.field eager-loaded
   * @param  IntegrationEnvironment  $environment  With fieldHashes eager-loaded
   * @param  array<string, mixed>    $leadData     Lead fields keyed by field name
   * @return array<string, string>
   */
  public function buildReplacements(Integration $integration, IntegrationEnvironment $environment, array $leadData): array
  {
    $replacements = [];
    $hashConfigs = $environment->fieldHashes->keyBy('field_id');

    foreach ($integration->tokenMappings as $mapping) {
      if (!$mapping->field) {
        continue;
      }

      $raw = $leadData[$mapping->field->name] ?? ($mapping->default_value ?? '');
      $value = $mapping->value_mapping[$raw] ?? $raw;

      $hashConfig = $hashConfigs->get($mapping->field_id);
      if ($hashConfig?->is_hashed && $hashConfig->hash_algorithm) {
        $value = $this->hashValue((string) $value, $hashConfig->hash_algorithm, $hashConfig->hmac_secret);
      }

      $watermarked = match ($mapping->data_type) {
        'integer' => '___INT___' . (int) $value,
        'float' => '___FLOAT___' . (float) $value,
        'boolean' => '___BOOL___' . (filter_var($value, FILTER_VALIDATE_BOOLEAN) ? 'true' : 'false'),
        default => $this->jsonEscapeValue((string) $value),
      };

      $replacements['{$' . $mapping->field_id . '}'] = $watermarked;
      $replacements['{' . $mapping->field->name . '}'] = $watermarked;
    }

    return $replacements;
  }

  /**
   * Apply a precomputed replacement map to a template and release type watermarks.
   *
   * @param  string                $template     Body, headers JSON, or URL string
   * @param  array<string, string> $replacements From buildReplacements()
   */
  public function applyReplacements(string $template, array $replacements): string
  {
    if (empty($template)) {
      return '';
    }

    $afterReplace = str_replace(array_keys($replacements), array_values($replacements), $template);
    TailLogger::saveLog('After str_replace (before releaseTypes)', 'debug/payload-processor', 'info', [
      'replacements' => $replacements,
      'result' => $afterReplace,
    ]);

    $afterRelease = $this->releaseTypes($afterReplace);
    TailLogger::saveLog('After releaseTypes', 'debug/payload-processor', 'info', [
      'result' => $afterRelease,
    ]);

    return $afterRelease;
  }

  /**
   * Convenience wrapper: build replacements and apply them in one call.
   * Use buildReplacements() + applyReplacements() directly when you need to
   * apply the same map to multiple templates (body, headers, URL).
   */
  public function resolveTokens(string $template, Integration $integration, IntegrationEnvironment $environment, array $leadData): string
  {
    return $this->applyReplacements($template, $this->buildReplacements($integration, $environment, $leadData));
  }

  /**
   * Escape a string value so it is safe to inject inside a JSON string literal.
   * Uses json_encode to handle \n, \t, ", \ etc., then strips the outer quotes.
   */
  private function jsonEscapeValue(string $value): string
  {
    return substr(json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), 1, -1);
  }

  /**
   * Hash a string value using the configured algorithm.
   *
   * @param  string       $value      The raw value to hash
   * @param  string       $algorithm  md5 | sha1 | sha256 | sha512 | base64 | hmac_sha256
   * @param  string|null  $secret     Required for hmac_sha256
   */
  private function hashValue(string $value, string $algorithm, ?string $secret): string
  {
    return match ($algorithm) {
      'md5' => md5($value),
      'sha1' => sha1($value),
      'sha256' => hash('sha256', $value),
      'sha512' => hash('sha512', $value),
      'base64' => base64_encode($value),
      'hmac_sha256' => hash_hmac('sha256', $value, $secret ?? ''),
      default => $value,
    };
  }

  /**
   * Inyecta los valores en el string usando marcas de agua para tipos específicos.
   */
  private function injectTokens(string $template, array $data): string
  {
    //Deprecated code (Replace with regex) to prevent complex and bad performance by multiple replacements
    /* foreach ($data as $key => $value) {
      // Placeholder para Enteros
      $template = str_replace("{int:$key}", "___INT___$value", $template);
      // Placeholder para Booleans
      $boolVal = filter_var($value, FILTER_VALIDATE_BOOLEAN) ? 'true' : 'false';
      $template = str_replace("{bool:$key}", "___BOOL___$boolVal", $template);

      // Placeholder para Floats
      $template = str_replace("{float:$key}", "___FLOAT___$value", $template);

      // Reemplazo estándar para Strings
      $template = str_replace('{'. $key.'}', (string)$value, $template);
    }

    return $template; */
    // 1. Reemplazo de tokens en una sola pasada usando Regex Callback
    // Soporta: {token}, {int:token}, {bool:token}, {float:token}

    return preg_replace_callback(
      '/\{((?:int|bool|float):)?([\w.-]+)\}/',
      function ($matches) use ($data) {
        $modifier = $matches[1]; // "int:", "bool:", "float:" o vacío
        $tokenName = $matches[2]; // el nombre del campo

        // Si el token no existe en el set de datos, lo mantenemos intacto
        if (!isset($data[$tokenName])) {
          return $matches[0];
        }

        $value = $data[$tokenName];

        switch ($modifier) {
          case 'int:':
            return '___INT___' . (int) $value;
          case 'bool:':
            $boolVal = filter_var($value, FILTER_VALIDATE_BOOLEAN) ? 'true' : 'false';
            return '___BOOL___' . $boolVal;
          case 'float:':
            return '___FLOAT___' . (float) $value;
          default:
            // Para strings y otros tipos, se inyecta como string literal
            return (string) $value;
        }
      },
      $template,
    );
  }

  /**
   * Convierte valores con watermark a sus tipos reales quitando las comillas que los envuelven.
   *
   * El sistema de watermarks funciona así:
   *   1. buildReplacements() marca los valores tipados: "age" → ___INT___25
   *   2. str_replace() inserta esos valores en el template JSON
   *   3. releaseTypes() (esta función) quita las comillas JSON que envuelven el valor,
   *      convirtiendo "___INT___25" (string) → 25 (integer nativo en JSON)
   *
   * Los tokens pueden aparecer en 3 contextos distintos dentro del JSON:
   *
   *   Contexto 1 — Valor JSON directo (comillas regulares):
   *     Template:  "age": "{$100}"
   *     Después:   "age": "___INT___25"
   *     Resultado: "age": 25
   *
   *   Contexto 2 — Dentro de un JSON anidado como string (comillas escapadas \"):
   *     Template:  "data": "{\"age\":\"{$100}\"}"
   *     Después:   "data": "{\"age\":\"___INT___25\"}"
   *     Resultado: "data": "{\"age\":25}"
   *
   *   Contexto 3 — Sin comillas (token insertado sin wrapper):
   *     Template:  "data": "{\"age\":{$100}}"
   *     Después:   "data": "{\"age\":___INT___25}"
   *     Resultado: "data": "{\"age\":25}"
   */
  private function releaseTypes(string $jsonString): string
  {
    $notEscaped = '(?<!\\\\)';
    $escaped = '\\\\"';
    $capture = '$1';

    $watermarks = [
      'integer' => '-?\d+',
      'boolean' => 'true|false',
      'float' => '-?[\d.]+',
    ];

    foreach ($watermarks as $type => $valuePattern) {
      $marker = match ($type) {
        'integer' => '___INT___',
        'boolean' => '___BOOL___',
        'float' => '___FLOAT___',
      };

      // ── Contexto 1: comillas regulares "___INT___25" → 25
      $jsonString = preg_replace("/{$notEscaped}\"{$marker}({$valuePattern}){$notEscaped}\"/", $capture, $jsonString);

      // ── Contexto 2: comillas escapadas \"___INT___25\" → 25 (JSON anidado como string)
      $jsonString = preg_replace("/{$escaped}{$marker}({$valuePattern}){$escaped}/", $capture, $jsonString);

      // ── Contexto 3: sin comillas ___INT___25 → 25 (fallback)
      $jsonString = preg_replace("/{$marker}({$valuePattern})/", $capture, $jsonString);
    }

    return $jsonString;
  }

  /**
   * Apply a Twig payload transformer to an already-resolved payload array.
   * Returns the original payload if no transformer is configured or if transformation fails.
   */
  public function applyTwigTransformer(Integration $integration, array $payload): array
  {
    if (!$integration->use_custom_transformer || empty($integration->payload_transformer)) {
      return $payload;
    }

    TailLogger::saveLog('Twig transformer input', 'debug/twig-transformer', 'info', [
      'integration_id' => $integration->id,
      'integration_name' => $integration->name,
      'payload_input' => $payload,
      'twig_template' => $integration->payload_transformer,
    ]);

    try {
      $loader = new \Twig\Loader\ArrayLoader([
        'index.html' => $integration->payload_transformer,
      ]);
      $twig = new \Twig\Environment($loader);

      $twig->addFunction(
        new \Twig\TwigFunction(
          'output_json',
          function ($data) {
            return json_encode($data);
          },
          ['is_safe' => ['html']],
        ),
      );

      $rendered = $twig->render('index.html', ['data' => $payload]);
      $transformed = json_decode($rendered, true);

      TailLogger::saveLog('Twig transformer output', 'debug/twig-transformer', 'info', [
        'integration_id' => $integration->id,
        'rendered_raw' => $rendered,
        'decoded' => $transformed,
        'json_valid' => json_last_error() === JSON_ERROR_NONE,
      ]);

      if (json_last_error() === JSON_ERROR_NONE && is_array($transformed)) {
        return $transformed;
      }

      TailLogger::saveLog('Twig transformer produced invalid JSON', 'payload-processor', 'warning', [
        'integration_id' => $integration->id,
        'rendered' => $rendered,
      ]);
    } catch (\Throwable $e) {
      TailLogger::saveLog('Twig payload transformation failed', 'payload-processor', 'error', [
        'integration_id' => $integration->id,
        'error' => $e->getMessage(),
      ]);
    }

    return $payload;
  }
}

/**
 * --- EJEMPLO DE INTEGRACIÓN CON TU FUNCIÓN ACTUAL ---
 * * Supongamos que esta función vive en tu LeadService o similar.
 * * public function parseParams(array $leadData, string $template, array $mappingConfig): string
 * {
 * if (empty($template)) {
 * return '';
 * }
 *
 * // Preparamos el array de datos limpios para el PayloadProcessorService
 * $processedData = [];
 *
 * foreach ($mappingConfig as $tokenName => $config) {
 * $value = $leadData[$tokenName] ?? $config['defaultValue'] ?? '';
 *
 * // Mapeo de valores (ej: "male" -> 1)
 * if (isset($config['value_mapping']) && array_key_exists($value, $config['value_mapping'])) {
 * $value = $config['value_mapping'][$value];
 * }
 *
 * // Si es objeto/array lo serializamos
 * if (is_array($value) || is_object($value)) {
 * $value = json_encode($value);
 * }
 *
 * $processedData[$tokenName] = $value;
 * }
 *
 * if (empty($processedData)) {
 * return $template;
 * }
 *
 * // Invocamos al nuevo servicio para que maneje el tipado y la estructura
 * // En lugar de un simple str_replace manual, dejamos que el servicio haga la magia
 * $processor = new \App\Services\PayloadProcessorService();
 * return $processor->process($template, $processedData);
 * }
 * * Ejemplo de Template esperado en BD:
 * {
 * "lead_id": "{int:cptype}",
 * "zip": "{zip_code}",
 * "is_test": "{bool:is_test_mode}",
 * "price": "{float:bid_amount}"
 * }
 */
