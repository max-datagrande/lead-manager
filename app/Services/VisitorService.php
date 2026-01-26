<?php

namespace App\Services;

use App\Models\TrafficLog;

class VisitorService
{
  /**
   * Obtiene la configuración base para el datatable de TrafficLog
   *
   * Retorna la configuración necesaria para procesar el datatable:
   * - Query base con columnas seleccionadas
   * - Columnas disponibles para búsqueda global
   * - Configuración de filtros por columna
   * - Columnas permitidas para ordenamiento
   *
   * @return array Configuración completa del datatable
   */
  public function getDatatableConfig(): array
  {
    // Query base optimizada con columnas necesarias
    $query = TrafficLog::select([
      'id',
      'fingerprint',
      'visit_count',
      'ip_address',
      'device_type',
      'browser',
      'os',
      'referrer',
      'host',
      'path_visited',
      's1',
      's2',
      's3',
      's4',
      's10',
      'click_id',
      'platform',
      'channel',
      'utm_source',
      'utm_term',
      'utm_content',
      'utm_medium',
      'utm_campaign_id',
      'utm_campaign_name',
      'country_code',
      'state',
      'city',
      'postal_code',
      'is_bot',
      'created_at',
      'updated_at'
    ]);

    // Columnas disponibles para búsqueda global
    $searchableColumns = [
      'fingerprint',
      'ip_address',
      'host',
      'path_visited',
      'utm_source',
      'utm_content',
      'utm_medium',
      'country_code',
      'state',
      'city',
      'postal_code',
      'click_id',
      'platform',
      'channel',
      's10'
    ];

    // Configuración de filtros por columna
    $filterConfig = [
      'utm_source' => ['type' => 'exact'],
      'country_code' => ['type' => 'upper'],
      'is_bot' => ['type' => 'exact'],
      'device_type' => ['type' => 'exact'],
      'browser' => ['type' => 'like'],
      'os' => ['type' => 'like'],
      'host' => ['type' => 'like'],
      'state' => ['type' => 'like'],
      'city' => ['type' => 'like'],
      's1' => ['type' => 'like'],
      'from_date' => ['type' => 'from_date', 'column' => 'created_at'],
      'to_date' => ['type' => 'to_date', 'column' => 'created_at'],
    ];

    // Columnas permitidas para ordenamiento
    $allowedSort = [
      'visit_date',
      'created_at',
      'updated_at',
      'host',
      'country_code',
      'city',
      'state',
      'device_type',
      'browser',
      'os',
      'utm_source',
      'visit_count',
      'is_bot',
      'utm_source',
      'utm_content',
      'utm_medium',
      'country_code',
      'state',
      'city',
      'postal_code',
      'click_id',
      'platform',
      'channel',
      's10'
    ];

    return [
      'query' => $query,
      'searchableColumns' => $searchableColumns,
      'filterConfig' => $filterConfig,
      'allowedSort' => $allowedSort
    ];
  }
  public function getExistingStates()
  {
    return TrafficLog::select('state')
      ->whereNotNull('state')
      ->where('state', '<>', '')
      ->distinct()
      ->get()
      ->map(function ($item) {
        return [
          'value' => $item->state,
          'label' => ucfirst($item->state),
        ];
      })
      ->values();
  }
  public function getExistingHosts()
  {
    return TrafficLog::select('host')->distinct()->get()->map(function ($item) {
      return [
        'value' => $item->host,
        'label' => $item->host
      ];
    });
  }
}
