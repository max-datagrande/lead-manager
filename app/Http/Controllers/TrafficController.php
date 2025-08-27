<?php

namespace App\Http\Controllers;

use App\DatatableTrait;
use Inertia\Inertia;
use App\Models\TrafficLog;
use Illuminate\Http\Request;

class TrafficController extends Controller
{
  use DatatableTrait;

  /**
   * Display a listing of the resource.
   */
  public function index(Request $req)
  {
    // Seleccionamos solo las columnas necesarias para optimizar la consulta
    $query = TrafficLog::select([
      'id',
      'fingerprint',
      'visit_date',
      'visit_count',
      'ip_address',
      'device_type',
      'browser',
      'os',
      'country_code',
      'state',
      'city',
      'traffic_source',
      'traffic_medium',
      'host',
      'path_visited',
      'referrer',
      'is_bot',
      'created_at',
      'updated_at'
    ]);

    // Configuración de búsqueda global
    $searchableColumns = [
      'fingerprint',
      'ip_address',
      'host',
      'path_visited',
      'traffic_source'
    ];

    // Configuración de filtros
    $filterConfig = [
      'traffic_source' => ['type' => 'exact'],
      'country_code' => ['type' => 'upper'],
      'is_bot' => ['type' => 'exact'],
      'device_type' => ['type' => 'exact'],
      'browser' => ['type' => 'like'],
      'os' => ['type' => 'like'],
      'host' => ['type' => 'like'],
      'state' => ['type' => 'like'],
      'city' => ['type' => 'like'],
      'from_date' => ['type' => 'from_date', 'column' => 'created_at'],
      'to_date' => ['type' => 'to_date', 'column' => 'created_at'],
    ];

    // Configuración de ordenamiento
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
      'traffic_source',
      'visit_count',
      'is_bot'
    ];

    // Procesar consulta usando el trait
    $result = $this->processDatatableQuery(
      $query,
      $req,
      $searchableColumns,
      $filterConfig,
      $allowedSort,
      'created_at:desc',
      10,
      100
    );

    // Datos adicionales para filtros
    $hosts = TrafficLog::select('host')->distinct()->get()->map(function ($item) {
      return [
        'value' => $item->host,
        'label' => $item->host
      ];
    });

    $states = TrafficLog::select('state')
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

    return Inertia::render('visitors/index', [
      'rows' => $result['rows'],
      'meta' => $result['meta'],
      'state' => $result['state'],
      'data' => [
        'hosts' => $hosts,
        'states' => $states
      ]
    ]);
  }

  /**
   * Show the form for creating a new resource.
   */
  public function create()
  {
    //
  }

  /**
   * Store a newly created resource in storage.
   */
  public function store(Request $request)
  {
    //
  }

  /**
   * Display the specified resource.
   */
  public function show(TrafficLog $trafficLog)
  {
    //
  }

  /**
   * Show the form for editing the specified resource.
   */
  public function edit(TrafficLog $trafficLog)
  {
    //
  }

  /**
   * Update the specified resource in storage.
   */
  public function update(Request $request, TrafficLog $trafficLog)
  {
    //
  }

  /**
   * Remove the specified resource from storage.
   */
  public function destroy(TrafficLog $trafficLog)
  {
    //
  }
}
