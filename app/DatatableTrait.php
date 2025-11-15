<?php

namespace App;

use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Builder;

trait DatatableTrait
{
  const DEFAULT_SORT = 'created_at:desc';
  const DEFAULT_PER_PAGE = 10;
  const MAX_PER_PAGE = 100;
  /**
   * Aplica búsqueda global a la consulta
   */
  protected function applyGlobalSearch(Builder $query, string $search, array $searchableColumns): Builder
  {
    if (trim($search) === '') {
      return $query;
    }

    return $query->where(function ($w) use ($search, $searchableColumns) {
      $like = "%{$search}%";
      foreach ($searchableColumns as $column) {
        $w->orWhere($column, 'like', $like);
      }
    });
  }

  /**
   * Aplica filtros por columna a la consulta
   */
  protected function applyColumnFilters(Builder $query, array $filters, array $filterConfig): Builder
  {
    foreach ($filters as $f) {
      $id = $f['id'] ?? null;
      $val = $f['value'] ?? null;

      if ($val === null || $val === '' || (is_array($val) && empty($val))) {
        continue;
      }

      if (!isset($filterConfig[$id])) {
        continue;
      }

      $config = $filterConfig[$id];
      $this->applyFilter($query, $id, $val, $config);
    }

    return $query;
  }

  /**
   * Aplica un filtro específico
   */
  private function applyFilter(Builder $query, string $filterId, $value, array $config): void
  {
    $type = $config['type'] ?? 'exact';
    $column = $config['column'] ?? $filterId;

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
   */
  protected function applySorting(Builder $query, ?string $sort, array $allowedSort): Builder
  {
    $sort = $sort;
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
   */
  protected function processDatatableQuery(
    Builder $query,
    Request $request,
    array $searchableColumns = [],
    array $filterConfig = [],
    array $allowedSort = [],
    string $defaultSort = 'created_at:desc',
  ) {
    // Búsqueda global
    $search = trim((string) $request->input('search', ''));
    if (!empty($searchableColumns)) {
      $query = $this->applyGlobalSearch($query, $search, $searchableColumns);
    }

    // Filtros por columna
    $filters = json_decode($request->input('filters', '[]'), true) ?? [];
    if (!empty($filterConfig)) {
      $query = $this->applyColumnFilters($query, $filters, $filterConfig);
    }

    // Ordenamiento
    $sort = $request->input('sort', $defaultSort);
    if (!empty($allowedSort)) {
      $query = $this->applySorting($query, $sort, $allowedSort);
    }

    // Paginación
    $paginatedResults = $this->applyPagination($query, $request);

    return [
      'rows' => $paginatedResults,
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
