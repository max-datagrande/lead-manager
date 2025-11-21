<?php

namespace App\Http\Controllers;

use App\Traits\DatatableTrait;
use Inertia\Inertia;
use App\Models\TrafficLog;
use App\Services\VisitorService;
use Illuminate\Http\Request;

class VisitorController extends Controller
{
  use DatatableTrait;

  public function __construct(protected VisitorService $visitorService) {}
  /**
   * Display a listing of the resource.
   */
  public function index(Request $req)
  {
    // Obtener configuración del datatable desde el servicio
    $config = $this->visitorService->getDatatableConfig();

    // Procesar consulta usando el trait con la configuración del servicio
    $result = $this->processDatatableQuery(
      query: $config['query'],
      request: $req,
      searchableColumns: $config['searchableColumns'],
      filterConfig: $config['filterConfig'],
      allowedSort: $config['allowedSort'],
      defaultSort: 'created_at:desc'
    );

    // Datos adicionales para filtros
    $data = [
      'hosts' => $this->visitorService->getExistingHosts(),
      'states' => $this->visitorService->getExistingStates()
    ];

    return Inertia::render('visitors/index', [
      'rows' => $result['rows'],
      'meta' => $result['meta'],
      'state' => $result['state'],
      'data' => $data
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
