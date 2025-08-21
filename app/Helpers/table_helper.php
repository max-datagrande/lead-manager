<?php

/**
 * Extrae la columna y el orden de una ordenación de tabla
 */
if (!function_exists('get_sort_data')) {
  function get_sort_data(string $sort): array
  {
    [$col, $dir] = array_pad(explode(':', $sort), 2, 'asc');
    $dir = strtolower($dir) === 'desc' ? 'desc' : 'asc';
    return [$col, $dir];
  }
}
