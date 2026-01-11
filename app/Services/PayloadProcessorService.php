<?php

namespace App\Services;

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
      throw new \Exception("Error processing JSON: " . json_last_error_msg());
    }

    return json_encode($arrayData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
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
            return "___INT___" . (int)$value;
          case 'bool:':
            $boolVal = filter_var($value, FILTER_VALIDATE_BOOLEAN) ? 'true' : 'false';
            return "___BOOL___" . $boolVal;
          case 'float:':
            return "___FLOAT___" . (float)$value;
          default:
            // Para strings y otros tipos, se inyecta como string literal
            return (string)$value;
        }
      },
      $template
    );
  }

  /**
   * Elimina las comillas de los valores marcados con marcas de agua.
   */
  private function releaseTypes(string $jsonString): string
  {
    //Deprecated code (Replace with regex) to prevent complex and bad performance by multiple replacements
    /*  $jsonString = preg_replace('/"___INT___(.*?)"/', '$1', $jsonString);
    $jsonString = preg_replace('/"___BOOL___(.*?)"/', '$1', $jsonString);
    $jsonString = preg_replace('/"___FLOAT___(.*?)"/', '$1', $jsonString);

    return $jsonString; */
    return preg_replace(
      ['/"___INT___(.*?)"/', '/"___BOOL___(.*?)"/', '/"___FLOAT___(.*?)"/'],
      ['$1', '$1', '$1'],
      $jsonString
    );
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
