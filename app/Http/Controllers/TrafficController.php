<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use App\Models\TrafficLog;
use Illuminate\Http\Request;
class TrafficController extends Controller
{
  /**
   * Display a listing of the resource.
   *
   * @description Muestra una lista paginada de visitantes con información básica
   */
  public function index(Request $req)
  {
    // Seleccionamos solo las columnas necesarias para optimizar la consulta
    $q = TrafficLog::select([
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

    // --- BÚSQUEDA GLOBAL (opcional) ---
    $search = trim((string) $req->input('search', ''));
    if ($search !== '') {
      $q->where(function ($w) use ($search) {
        $like = "%{$search}%";
        $w->where('fingerprint', 'like', $like)
          ->orWhere('ip_address', 'like', $like)
          ->orWhere('host', 'like', $like)
          ->orWhere('path_visited', 'like', $like)
          ->orWhere('traffic_source', 'like', $like);
      });
    }
    // --- FILTROS POR COLUMNA ---
    $filters = json_decode($req->input('filters', '[]'), true) ?? [];
    foreach ($filters as $f) {
      $id = $f['id'] ?? null;
      $val = $f['value'] ?? null;
      if ($val === null || $val === '' || (is_array($val) && empty($val))) continue;
      
      switch ($id) {
        case 'traffic_source':
          if (is_array($val)) {
            $q->whereIn('traffic_source', $val);
          } else {
            $q->where('traffic_source', $val);
          }
          break;
        case 'country_code':
          $q->where('country_code', strtoupper($val));
          break;
        case 'is_bot':
          $q->where('is_bot', (int) $val);
          break;
        case 'device_type':
          if (is_array($val)) {
            $q->whereIn('device_type', $val);
          } else {
            $q->where('device_type', $val);
          }
          break;
        case 'browser':
          if (is_array($val)) {
            $q->where(function($query) use ($val) {
              foreach ($val as $browser) {
                $query->orWhere('browser', 'like', "%{$browser}%");
              }
            });
          } else {
            $q->where('browser', 'like', "%{$val}%");
          }
          break;
        case 'os':
          $q->where('os', 'like', "%{$val}%");
          break;
        case 'host':
          $q->where('host', 'like', "%{$val}%");
          break;
        case 'state':
          $q->where('state', 'like', "%{$val}%");
          break;
        case 'city':
          $q->where('city', 'like', "%{$val}%");
          break;
        case 'visit_date_from':
          $q->whereDate('visit_date', '>=', $val);
          break;
        case 'visit_date_to':
          $q->whereDate('visit_date', '<=', $val);
          break;
          // Agrega más filtros permitidos aquí
      }
    }
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

    $sort = $req->input('sort'); // ej: "created_at:desc"

    if ($sort) {
      [$col, $dir] = get_sort_data($sort);
      $isAllowSorting = in_array($col, $allowedSort, true);
      if ($isAllowSorting) {
        $q->orderBy($col, $dir);
      }
    } else {
      $q->orderByDesc('created_at'); //Sort por defecto
    }
    // --- PAGINACIÓN ---
    $perPage = (int) $req->input('per_page', 10);
    $perPage = max(1, min($perPage, 100));
    $page = (int) $req->input('page', 1);
    $queryParams = $req->query();
    $p = $q->paginate($perPage, ['*'], 'page', $page)->appends($queryParams);
    return Inertia::render('Visitors/Index', [
      'data' => $p,
      'meta' => [
        'total' => $p->total(),
        'per_page' => $p->perPage(),
        'current_page' => $p->currentPage(),
        'last_page' => $p->lastPage(),
        'from' => $p->firstItem(),
        'to' => $p->lastItem(),
      ],
      'state' => [
        'search' => $search,
        'filters' => $filters,
        'sort' => $sort,
        'page' => $page,
        'per_page' => $perPage,
      ],
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
