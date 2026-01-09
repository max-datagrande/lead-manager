<?php

/**
 * Limpia objetos vacíos y reindexa arrays secuenciales para evitar
 * que json_encode genere objetos con llaves numéricas.
 */

if (!function_exists('clean_payload_structure')) {
  function clean_payload_structure($input)
  {
    foreach ($input as &$value) {
      if (is_array($value)) {
        $value = clean_payload_structure($value);
      }
    }

    // 1. Filtramos elementos vacíos (null, string vacío o arrays que quedaron vacíos)
    $filtered = array_filter($input, function ($item) {
      return !($item === null || $item === '' || (is_array($item) && empty($item)));
    });

    // 2. Determinamos si es un array asociativo (objeto) o una lista (array)
    // Si todas las llaves son números, es una lista y debemos reindexar con array_values
    $keys = array_keys($filtered);
    $isSequential = true;
    foreach ($keys as $key) {
      if (!is_int($key)) {
        $isSequential = false;
        break;
      }
    }

    return $isSequential ? array_values($filtered) : $filtered;
  }
}
