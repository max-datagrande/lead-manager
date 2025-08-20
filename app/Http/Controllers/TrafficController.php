<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\TrafficLog;

class TrafficController extends Controller
{
  /**
   * Display a listing of the resource.
   *
   * @description Muestra una lista paginada de visitantes con información básica
   */
  public function index()
  {
    $perPage = 15;

    // Seleccionamos solo las columnas necesarias para optimizar la consulta
    $visitorsQuery = TrafficLog::select([
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

    // Ordenamos por fecha de creación descendente para mostrar los más recientes primero
    $visitorsQuery->orderBy('created_at', 'desc');

    // Paginamos los resultados
    $visitors = $visitorsQuery->paginate($perPage);

    // Añadimos los parámetros de consulta a los enlaces de paginación
    $visitors->appends(request()->query());

    return Inertia::render('Visitors/Index', [
      'visitors' => $visitors,
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
