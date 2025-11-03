<?php

use phpDocumentor\Reflection\Types\Boolean;

if (!function_exists('get_error_stack')) {
  function get_error_stack(Exception $e, bool $isDev = false): array
  {//obtener pila de errores
    $errors = [];
    $current = $e;
    while ($current) {
      $data = [
        'message' => $current->getMessage(),
      ];
      if ($isDev) {
        $data['file'] = basename($current->getFile());
        $data['line'] = $current->getLine();
      }
      $errors[] = $data;
      $current = $current->getPrevious();
    }
    return $errors;
  }
}
