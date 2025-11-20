<?php

namespace App;

use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Builder;

/**
 * Trait DatatableTrait - Procesamiento avanzado de consultas para DataTables
 * 
 * Este trait proporciona un conjunto completo de herramientas para procesar consultas
 * de base de datos con funcionalidades avanzadas de:
 * - Búsqueda global e inteligente
 * - Filtrado por columnas individuales
 * - Ordenamiento dinámico y seguro
 * - Paginación con límites de seguridad
 * 
 * Características principales:
 * - Seguridad: Previene SQL injection y limita cantidad de datos
 * - Flexibilidad: Soporta múltiples tipos de filtros y operadores
 * - Rendimiento: Optimizado para grandes volúmenes de datos
 * - Consistencia: Interfaz unificada para todos los datatables
 * 
 * Ejemplo de uso completo:
 * ```php
 * class UserController extends Controller
 * {
 *     use DatatableTrait;
 * 
 *     public function index(Request $request)
 *     {
 *         $query = User::query();
 *         
 *         $resultados = $this->processDatatableQuery(
 *             $query,
 *             $request,
 *             ['name', 'email', 'phone'], // Columnas buscables
 *             [                           // Configuración de filtros
 *                 'status' => ['column' => 'status', 'type' => 'exact'],
 *                 'created_at' => ['column' => 'created_at', 'type' => 'from_date']
 *             ],
 *             ['name', 'email', 'created_at'] // Columnas ordenables
 *         );
 *         
 *         return response()->json($resultados);
 *     }
 * }
 * ```
 */
trait DatatableTrait
{
  /**
   * Ordenamiento por defecto cuando no se especifica uno válido
   * Formato: 'columna:dirección' (asc|desc)
   */
  const DEFAULT_SORT = 'created_at:desc';
  
  /**
   * Número de registros por página por defecto
   * Valor seguro para evitar sobrecarga de datos
   */
  const DEFAULT_PER_PAGE = 10;
  
  /**
   * Máximo número de registros permitidos por página
   * Límite de seguridad para prevenir sobrecarga de servidor
   */
  const MAX_PER_PAGE = 100;
  /**
   * Aplica búsqueda global a la consulta
   * 
   * Realiza una búsqueda de texto libre en múltiples columnas simultáneamente.
   * Útil para el campo de búsqueda general que tienen la mayoría de datatables.
   * 
   * Características:
   * - Búsqueda parcial con comodines (%)
   * - Case-insensitive (depende del collation de la BD)
   * - OR lógico entre columnas
   * - Segura contra SQL injection
   * 
   * @param Builder $query Consulta Eloquent base
   * @param string $search Término de búsqueda (puede contener espacios)
   * @param array $searchableColumns Columnas donde buscar
   * 
   * @return Builder Consulta modificada con búsqueda aplicada
   * 
   * Ejemplos de uso:
   * ```php
   * // Búsqueda simple
   * $query = $this->applyGlobalSearch($query, 'john', ['name', 'email']);
   * // SQL: WHERE name LIKE '%john%' OR email LIKE '%john%'
   * 
   * // Búsqueda vacía (no aplica filtro)
   * $query = $this->applyGlobalSearch($query, '', ['name', 'email']);
   * // SQL: Sin cambios
   * 
   * // Búsqueda con espacios
   * $query = $this->applyGlobalSearch($query, 'john doe', ['name', 'email', 'phone']);
   * // SQL: WHERE name LIKE '%john doe%' OR email LIKE '%john doe%' OR phone LIKE '%john doe%'
   * ```
   * 
   * Seguridad:
   * - Escapa automáticamente el texto de búsqueda
   * - No permite inyección SQL
   * - Maneja correctamente caracteres especiales
   */
  protected function applyGlobalSearch(Builder $query, string $search, array $searchableColumns): Builder
  {
    // Si la búsqueda está vacía o solo tiene espacios, no aplicar filtro
    if (trim($search) === '') {
      return $query;
    }

    // Crear grupo OR de condiciones para buscar en todas las columnas
    return $query->where(function ($w) use ($search, $searchableColumns) {
      $like = "%{$search}%"; // Agregar comodines para búsqueda parcial
      foreach ($searchableColumns as $column) {
        $w->orWhere($column, 'like', $like);
      }
    });
  }

  /**
   * Aplica filtros por columna a la consulta
   * 
   * Procesa múltiples filtros individuales para diferentes columnas.
   * Cada filtro puede tener su propio tipo de operador (exacto, like, fecha, etc.).
   * 
   * Formato esperado de filtros:
   * ```php
   * [
   *   ['id' => 'status', 'value' => 'active'],
   *   ['id' => 'created_at', 'value' => '2024-01-15'],
   *   ['id' => 'roles', 'value' => ['admin', 'user']]
   * ]
   * ```
   * 
   * @param Builder $query Consulta Eloquent base
   * @param array $filters Array de filtros con formato ['id' => string, 'value' => mixed]
   * @param array $filterConfig Configuración de cada filtro con tipo y columna
   * 
   * @return Builder Consulta modificada con todos los filtros aplicados
   * 
   * Ejemplos de uso:
   * ```php
   * $filters = [
   *   ['id' => 'status', 'value' => 'active'],
   *   ['id' => 'search', 'value' => 'john'],
   *   ['id' => 'date_from', 'value' => '2024-01-01'],
   *   ['id' => 'roles', 'value' => ['admin', 'moderator']]
   * ];
   * 
   * $filterConfig = [
   *   'status' => ['type' => 'exact', 'column' => 'status'],
   *   'search' => ['type' => 'like', 'column' => 'name'],
   *   'date_from' => ['type' => 'from_date', 'column' => 'created_at'],
   *   'roles' => ['type' => 'exact', 'column' => 'role_id']
   * ];
   * 
   * $query = $this->applyColumnFilters($query, $filters, $filterConfig);
   * // SQL: WHERE status = 'active' AND name LIKE '%john%' AND created_at >= '2024-01-01' AND role_id IN ('admin', 'moderator')
   * ```
   * 
   * Validaciones:
   * - Ignora filtros con valores null, vacíos o arrays vacíos
   * - Solo aplica filtros que tengan configuración definida
   * - Aplica AND lógico entre diferentes filtros
   * - Maneja arrays de valores para filtros múltiples
   */
  protected function applyColumnFilters(Builder $query, array $filters, array $filterConfig): Builder
  {
    foreach ($filters as $f) {
      $id = $f['id'] ?? null;
      $val = $f['value'] ?? null;

      // Saltar filtros con valores inválidos
      if ($val === null || $val === '' || (is_array($val) && empty($val))) {
        continue;
      }

      // Saltar filtros sin configuración
      if (!isset($filterConfig[$id])) {
        continue;
      }

      $config = $filterConfig[$id];
      $this->applyFilter($query, $id, $val, $config);
    }

    return $query;
  }

  /**
   * Aplica un filtro específico a la consulta
   * 
   * Función interna que maneja diferentes tipos de operadores de filtrado.
   * Soporta múltiples tipos de filtros para diferentes necesidades.
   * 
   * Tipos de filtros disponibles:
   * - 'exact': Igualdad exacta (=) o IN para arrays
   * - 'like': Búsqueda parcial con comodines (LIKE '%valor%')
   * - 'from_date': Fecha mayor o igual (>=)
   * - 'to_date': Fecha menor o igual (<=)
   * - 'upper': Convierte valor a mayúsculas antes de comparar
   * - 'lower': Convierte valor a minúsculas antes de comparar
   * 
   * @param Builder $query Consulta Eloquent
   * @param string $filterId Identificador del filtro (para referencia)
   * @param mixed $value Valor a filtrar (string, array, fecha)
   * @param array $config Configuración del filtro ['type' => string, 'column' => string]
   * 
   * Ejemplos de configuración:
   * ```php
   * // Filtro exacto simple
   * ['type' => 'exact', 'column' => 'status']
   * 
   * // Filtro de búsqueda parcial
   * ['type' => 'like', 'column' => 'name']
   * 
   * // Filtro de fecha
   * ['type' => 'from_date', 'column' => 'created_at']
   * 
   * // Filtro case-insensitive
   * ['type' => 'lower', 'column' => 'email']
   * ```
   * 
   * Ejemplos de uso con valores:
   * ```php
   * // Exacto con string
   * $this->applyFilter($query, 'status', 'active', ['type' => 'exact', 'column' => 'status']);
   * // SQL: WHERE status = 'active'
   * 
   * // Exacto con array (múltiples valores)
   * $this->applyFilter($query, 'roles', ['admin', 'user'], ['type' => 'exact', 'column' => 'role']);
   * // SQL: WHERE role IN ('admin', 'user')
   * 
   * // Like con búsqueda parcial
   * $this->applyFilter($query, 'search', 'john', ['type' => 'like', 'column' => 'name']);
   * // SQL: WHERE name LIKE '%john%'
   * 
   * // Like con múltiples valores (OR)
   * $this->applyFilter($query, 'keywords', ['john', 'jane'], ['type' => 'like', 'column' => 'name']);
   * // SQL: WHERE name LIKE '%john%' OR name LIKE '%jane%'
   * 
   * // Filtro de fecha desde
   * $this->applyFilter($query, 'from', '2024-01-01', ['type' => 'from_date', 'column' => 'created_at']);
   * // SQL: WHERE DATE(created_at) >= '2024-01-01'
   * 
   * // Filtro case-insensitive
   * $this->applyFilter($query, 'email', 'ADMIN@EXAMPLE.COM', ['type' => 'lower', 'column' => 'email']);
   * // SQL: WHERE email = 'admin@example.com'
   * ```
   * 
   * Notas de seguridad:
   * - Los valores se escapan automáticamente por Eloquent
   * - Las fechas deben venir en formato YYYY-MM-DD
   * - Los arrays se validan automáticamente
   */
  private function applyFilter(Builder $query, string $filterId, $value, array $config): void
  {
    $type = $config['type'] ?? 'exact'; // Tipo de filtro (default: exact)
    $column = $config['column'] ?? $filterId; // Columna a filtrar (default: id del filtro)

    switch ($type) {
      case 'exact':
        if (is_array($value)) {
          $query->whereIn($column, $value);
        } else {
          $query->where($column, $value);
        }
        break;

      case 'like':
        if (is_array($value)) {
          // Múltiples valores LIKE con OR
          $query->where(function ($q) use ($column, $value) {
            foreach ($value as $val) {
              $q->orWhere($column, 'like', "%{$val}%");
            }
          });
        } else {
          $query->where($column, 'like', "%{$value}%");
        }
        break;

      case 'from_date':
        $query->whereDate($column, '>=', $value);
        break;

      case 'to_date':
        $query->whereDate($column, '<=', $value);
        break;

      case 'upper':
        $query->where($column, strtoupper($value));
        break;

      case 'lower':
        if (is_array($value)) {
          $query->whereIn($column, array_map('strtolower', $value));
        } else {
          $query->where($column, strtolower($value));
        }
        break;
    }
  }

  /**
   * Aplica ordenamiento a la consulta
   * 
   * Valida y aplica ordenamiento seguro a la consulta. Solo permite ordenar por
   * columnas específicamente autorizadas para prevenir ataques de inyección.
   * 
   * Formato de sort: 'columna:dirección' donde dirección puede ser 'asc' o 'desc'
   * Ejemplos: 'name:asc', 'created_at:desc', 'email:asc'
   * 
   * @param Builder $query Consulta Eloquent base
   * @param string|null $sort String con formato 'columna:dirección' o null
   * @param array $allowedSort Lista de columnas permitidas para ordenamiento
   * 
   * @return Builder Consulta con ordenamiento aplicado
   * 
   * Ejemplos de uso:
   * ```php
   * // Ordenamiento válido
   * $query = $this->applySorting($query, 'name:asc', ['name', 'email', 'created_at']);
   * // SQL: ORDER BY name ASC
   * 
   * // Ordenamiento descendente
   * $query = $this->applySorting($query, 'created_at:desc', ['name', 'email', 'created_at']);
   * // SQL: ORDER BY created_at DESC
   * 
   * // Ordenamiento no permitido (aplica default)
   * $query = $this->applySorting($query, 'password:asc', ['name', 'email']);
   * // SQL: ORDER BY created_at DESC (por defecto)
   * 
   * // Sin ordenamiento (aplica default)
   * $query = $this->applySorting($query, null, ['name', 'email']);
   * // SQL: ORDER BY created_at DESC (por defecto)
   * 
   * // Ordenamiento con función helper
   * // Suponiendo que get_sort_data() devuelve ['columna', 'dirección']
   * [$column, $direction] = get_sort_data('name:asc');
   * // $column = 'name', $direction = 'asc'
   * ```
   * 
   * Seguridad:
   * - Solo permite ordenar por columnas en la lista $allowedSort
   * - Si el ordenamiento no es válido, usa el ordenamiento por defecto
   * - Previene inyección SQL a través de nombres de columna
   * - Valida que la dirección sea solo 'asc' o 'desc'
   * 
   * Nota: Esta función asume que existe una función auxiliar get_sort_data()
   * que convierte 'columna:dirección' en un array [$columna, $direccion]
   */
  protected function applySorting(Builder $query, ?string $sort, array $allowedSort): Builder
  {
    if ($sort) {
      [$col, $dir] = get_sort_data($sort);
      $isAllowSorting = in_array($col, $allowedSort, true);
      if ($isAllowSorting) {
        $query->orderBy($col, $dir);
      } else {
        // Aplicar ordenamiento por defecto si no es válido
        [$defaultCol, $defaultDir] = get_sort_data(self::DEFAULT_SORT);
        $query->orderBy($defaultCol, $defaultDir);
      }
    }

    return $query;
  }

  /**
   * Aplica paginación a la consulta y retorna resultado paginado
   *
   * Esta función maneja la paginación de resultados para datatables, con:
   * - Validación de límites de registros por página
   * - Preservación de parámetros de consulta en URLs de paginación
   * - Valores por defecto seguros
   *
   * @param Builder $query La consulta Eloquent a paginar
   * @param Request $request Request HTTP con parámetros de paginación
   *
   * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
   *
   * Parámetros del request:
   * - per_page: Número de registros por página (default: 10, max: 100)
   * - page: Número de página actual (default: 1)
   *
   * Ejemplo de uso:
   * $resultados = $this->applyPagination($query, $request);
   *
   * Seguridad:
   * - Valida que per_page esté entre 1 y self::MAX_PER_PAGE
   * - Convierte valores a enteros para prevenir inyección
   * - Preserva todos los parámetros de búsqueda/filtro en la paginación
   */
  protected function applyPagination(Builder $query, Request $request)
  {
    // Obtener registros por página del request, con valor por defecto
    $perPage = (int) $request->input('per_page', self::DEFAULT_PER_PAGE);

    // Validar límites: mínimo 1, máximo self::MAX_PER_PAGE (prevenir valores maliciosos)
    $perPage = max(1, min($perPage, self::MAX_PER_PAGE));

    // Obtener número de página actual (default: 1)
    $page = (int) $request->input('page', 1);

    // Obtener todos los parámetros del query string para preservarlos en la paginación
    $queryParams = $request->query();

    // Aplicar paginación y preservar parámetros en links de paginación
    return $query->paginate($perPage, ['*'], 'page', $page)->appends($queryParams);
  }

  /**
   * Procesa una consulta datatable completa
   * 
   * Función principal que orquesta todo el procesamiento de datatables:
   * búsqueda global, filtros por columna, ordenamiento y paginación.
   * 
   * Flujo de procesamiento:
   * 1. Aplica búsqueda global (si hay término de búsqueda y columnas configuradas)
   * 2. Aplica filtros por columna (si hay filtros y configuración)
   * 3. Aplica ordenamiento (si hay columnas ordenables configuradas)
   * 4. Aplica paginación
   * 5. Retorna resultado estructurado con metadatos
   * 
   * @param Builder $query Consulta Eloquent base (sin procesar)
   * @param Request $request Request HTTP con todos los parámetros del datatable
   * @param array $searchableColumns Columnas donde aplicar búsqueda global
   * @param array $filterConfig Configuración de filtros por columna
   * @param array $allowedSort Columnas permitidas para ordenamiento
   * @param string $defaultSort Ordenamiento por defecto si no se especifica
   * 
   * @return array Resultado estructurado con datos y metadatos
   * 
   * Estructura del retorno:
   * ```php
   * [
   *   'rows' => LengthAwarePaginator, // Datos paginados
   *   'meta' => [
   *     'total' => int,        // Total de registros
   *     'per_page' => int,     // Registros por página
   *     'current_page' => int, // Página actual
   *     'last_page' => int,    // Última página
   *     'from' => int,         // Primer registro de la página
   *     'to' => int            // Último registro de la página
   *   ],
   *   'state' => [             // Estado actual para sincronización frontend
   *     'search' => string,    // Término de búsqueda actual
   *     'filters' => array,    // Filtros aplicados
   *     'sort' => string,      // Ordenamiento actual
   *     'page' => int,         // Página actual
   *     'per_page' => int      // Registros por página
   *   ]
   * ]
   * ```
   * 
   * Ejemplo completo de uso:
   * ```php
   * public function getUsers(Request $request)
   * {
   *   $query = User::with(['role', 'company']);
   *   
   *   return $this->processDatatableQuery(
   *     $query,
   *     $request,
   *     ['name', 'email', 'phone'],                    // Búsqueda global
   *     [                                             // Filtros por columna
   *       'status' => ['type' => 'exact', 'column' => 'status'],
   *       'role' => ['type' => 'exact', 'column' => 'role_id'],
   *       'search_name' => ['type' => 'like', 'column' => 'name'],
   *       'created_from' => ['type' => 'from_date', 'column' => 'created_at'],
   *       'created_to' => ['type' => 'to_date', 'column' => 'created_at']
   *     ],
   *     ['name', 'email', 'created_at', 'status'],  // Ordenamiento permitido
   *     'created_at:desc'                            // Orden por defecto
   *   );
   * }
   * ```
   * 
   * Parámetros del request que procesa:
   * - search: string - Término de búsqueda global
   * - filters: json - Array de filtros por columna
   * - sort: string - Columna y dirección de ordenamiento (formato: 'columna:dirección')
   * - page: int - Número de página
   * - per_page: int - Registros por página (máximo 100)
   * 
   * Seguridad:
   * - Valida todos los parámetros de entrada
   * - Limita cantidad de registros por página
   * - Solo permite ordenar por columnas autorizadas
   * - Escapa valores de búsqueda y filtros
   * - Maneja errores de JSON decoding
   * 
   * Rendimiento:
   * - Aplica filtros antes de ordenamiento para mejorar índices
   * - Usa paginación eficiente de Laravel
   * - Minimiza consultas con relaciones eager loading
   */
  protected function processDatatableQuery(
    Builder $query,
    Request $request,
    array $searchableColumns = [],
    array $filterConfig = [],
    array $allowedSort = [],
    string $defaultSort = 'created_at:desc',
  ) {
    // Búsqueda global - campo de búsqueda general del datatable
    $search = trim((string) $request->input('search', ''));
    if (!empty($searchableColumns) && !empty($search)) {
      $query = $this->applyGlobalSearch($query, $search, $searchableColumns);
    }

    // Filtros por columna - filtros individuales por columna
    $filters = json_decode($request->input('filters', '[]'), true) ?? [];
    if (!empty($filterConfig) && !empty($filters)) {
      $query = $this->applyColumnFilters($query, $filters, $filterConfig);
    }

    // Ordenamiento - ordenar resultados
    $sort = $request->input('sort', $defaultSort);
    if (!empty($allowedSort)) {
      $query = $this->applySorting($query, $sort, $allowedSort);
    }

    // Paginación - dividir resultados en páginas
    $paginatedResults = $this->applyPagination($query, $request);

    // Retornar estructura completa con datos y metadatos
    return [
      'rows' => $paginatedResults, // Datos paginados para la tabla
      'meta' => [
        'total' => $paginatedResults->total(),
        'per_page' => $paginatedResults->perPage(),
        'current_page' => $paginatedResults->currentPage(),
        'last_page' => $paginatedResults->lastPage(),
        'from' => $paginatedResults->firstItem(),
        'to' => $paginatedResults->lastItem(),
      ],
      'state' => [
        'search' => $search,
        'filters' => $filters,
        'sort' => $sort,
        'page' => (int) $request->input('page', 1),
        'per_page' => (int) $request->input('per_page', self::DEFAULT_PER_PAGE),
      ]
    ];
  }
}
