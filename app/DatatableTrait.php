<?php

namespace App;

use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Builder;

trait DatatableTrait
{
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
  protected function applySorting(Builder $query, ?string $sort, array $allowedSort, string $defaultSort = 'created_at:desc'): Builder
  {
    $sort = $sort ?: $defaultSort;

    if ($sort) {
      [$col, $dir] = get_sort_data($sort);
      $isAllowSorting = in_array($col, $allowedSort, true);

      if ($isAllowSorting) {
        $query->orderBy($col, $dir);
      } else {
        // Aplicar ordenamiento por defecto si no es válido
        [$defaultCol, $defaultDir] = get_sort_data($defaultSort);
        $query->orderBy($defaultCol, $defaultDir);
      }
    }

    return $query;
  }

  /**
   * Aplica paginación y retorna el resultado paginado
   */
  protected function applyPagination(Builder $query, Request $request, int $defaultPerPage = 10, int $maxPerPage = 100)
  {
    $perPage = (int) $request->input('per_page', $defaultPerPage);
    $perPage = max(1, min($perPage, $maxPerPage));
    $page = (int) $request->input('page', 1);
    $queryParams = $request->query();

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
    int $defaultPerPage = 10,
    int $maxPerPage = 100
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
      $query = $this->applySorting($query, $sort, $allowedSort, $defaultSort);
    }

    // Paginación
    $paginatedResults = $this->applyPagination($query, $request, $defaultPerPage, $maxPerPage);

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
        'per_page' => (int) $request->input('per_page', $defaultPerPage),
      ]
    ];
  }
}
