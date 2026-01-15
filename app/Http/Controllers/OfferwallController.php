<?php

namespace App\Http\Controllers;

use App\Models\OfferwallConversion;
use App\Models\Integration;
use App\Models\OfferwallMix;
use Illuminate\Http\Request;
use Inertia\Inertia;

class OfferwallController extends Controller
{
  /**
   * Display a listing of the resource.
   */
  public function index(Request $request)
  {
    // Eager-load de integraciones (y compañía) para que el frontend tenga los datos completos
    $rows = OfferwallMix::with(['integrations', 'integrations.company'])->get();
    return Inertia::render('offerwall/index', [
      'rows' => $rows,
      'state' => $request->only(['sort', 'search']),
    ]);
  }

  /**
   * Get all offerwall integrations.
   */
  public function getOfferwallIntegrations()
  {
    $integrations = Integration::with('company:id,name')
      ->where('type', 'offerwall')
      ->get();

    return response()->json($integrations);
  }

  /**
   * Display a listing of the offerwall conversions.
   */
  public function conversions(Request $request)
  {
    // Iniciamos la query usando Joins para máxima eficiencia
    // Eliminamos 'latestTrafficLog' del eager loading ya que usamos el join directo
    $query = OfferwallConversion::with(['integration.company'])
      ->select([
        'offerwall_conversions.*',
        'integrations.company_id as company_id',
        'traffic_logs.host as host_name'
      ])
      // INNER JOIN con integrations para obtener la empresa
      ->join('integrations', 'offerwall_conversions.integration_id', '=', 'integrations.id')
      // LEFT JOIN con traffic_logs para obtener el host
      ->leftJoin('traffic_logs', function ($join) {
        $join->on('offerwall_conversions.fingerprint', '=', 'traffic_logs.fingerprint');
      });

    // Filtro de búsqueda global
    if ($search = $request->input('search')) {
      $query->where(function ($q) use ($search) {
        $q->where('offerwall_conversions.click_id', 'like', '%' . $search . '%')
          ->orWhere('offerwall_conversions.utm_source', 'like', '%' . $search . '%')
          ->orWhere('offerwall_conversions.utm_medium', 'like', '%' . $search . '%')
          ->orWhere('offerwall_conversions.fingerprint', 'like', '%' . $search . '%')
          ->orWhere('offerwall_conversions.tracked_fields->cptype', 'like', '%' . $search . '%')
          ->orWhere('offerwall_conversions.tracked_fields->placement_id', 'like', '%' . $search . '%')
          ->orWhere('integrations.name', 'like', '%' . $search . '%');
      });
    }

    // Aplicar filtros por columna
    $columnFilters = json_decode($request->input('filters', '[]'), true);
    foreach ($columnFilters as $filter) {
      if (isset($filter['id']) && isset($filter['value'])) {
        $val = (array) $filter['value'];
        switch ($filter['id']) {
          case 'from_date':
            $query->where('offerwall_conversions.created_at', '>=', $filter['value']);
            break;
          case 'to_date':
            $query->where('offerwall_conversions.created_at', '<=', $filter['value']);
            break;
          case 'integration':
            $query->whereIn('offerwall_conversions.integration_id', $val);
            break;
          case 'company':
            $query->whereIn('integrations.company_id', $val);
            break;
          case 'host':
            $query->whereIn('traffic_logs.host', $val);
            break;
          case 'pathname':
            $query->whereIn('offerwall_conversions.pathname', $val);
            break;
          case 'cptype':
            $query->whereIn('offerwall_conversions.tracked_fields->cptype', $val);
            break;
          case 'placement_id':
            $query->whereIn('offerwall_conversions.tracked_fields->placement_id', $val);
            break;
          case 'state':
            $query->whereIn('offerwall_conversions.tracked_fields->state', $val);
            break;
        }
      }
    }

    // Cálculo del payout total antes de la paginación
    $totalPayout = $query->sum('offerwall_conversions.amount');

    // Aplicar ordenamiento
    $sort = $request->input('sort', 'created_at:desc');
    [$sortColumn, $sortDirection] = get_sort_data($sort);

    if (in_array($sortColumn, ['cptype', 'placement_id', 'state'])) {
      $query->orderBy("offerwall_conversions.tracked_fields->$sortColumn", $sortDirection);
    } elseif ($sortColumn === 'company') {
      $query->orderBy('integrations.company_id', $sortDirection);
    } elseif ($sortColumn === 'host') {
      $query->orderBy('traffic_logs.host', $sortDirection);
    } elseif ($sortColumn === 'integration') {
      $query->orderBy('offerwall_conversions.integration_id', $sortDirection);
    } else {
      $query->orderBy("offerwall_conversions.$sortColumn", $sortDirection);
    }

    // Paginación con agrupamiento para evitar duplicados por el join
    $perPage = $request->input('per_page', 15);
    $conversions = $query->groupBy('offerwall_conversions.id', 'integrations.company_id', 'traffic_logs.host')
      ->paginate($perPage)
      ->withQueryString();

    // Transformación de la colección para normalizar los campos virtuales
    $conversions->getCollection()->transform(function ($conversion) {
      $conversion->host = $conversion->host_name;
      $conversion->cptype = $conversion->tracked_fields['cptype'] ?? null;
      $conversion->placement_id = $conversion->tracked_fields['placement_id'] ?? null;
      $conversion->state = $conversion->tracked_fields['state'] ?? null;
      return $conversion;
    });

    $integrations = Integration::select('id', 'name')->get()->map(function ($integration) {
      return ['value' => (string) $integration->id, 'label' => $integration->name];
    });

    $companies = \App\Models\Integration::with('company')
      ->where('type', 'offerwall')
      ->whereNotNull('company_id')
      ->get()
      ->unique('company_id')
      ->map(function ($integration) {
        return ['value' => (string) $integration->company_id, 'label' => $integration->company->name];
      })
      ->values();

    $paths = OfferwallConversion::select('pathname')
      ->distinct()
      ->whereNotNull('pathname')
      ->orderBy('pathname')
      ->pluck('pathname')
      ->map(fn($p) => ['value' => $p, 'label' => $p]);

    //Company
    $companies = Integration::with('company')
      ->where('type', 'offerwall')
      ->whereNotNull('company_id')
      ->get()
      ->unique('company_id')
      ->map(function ($integration) {
        return ['value' => (string) $integration->company_id, 'label' => $integration->company->name];
      })
      ->values();
    //Paths
    $paths = OfferwallConversion::select('pathname')
      ->distinct()
      ->whereNotNull('pathname')
      ->orderBy('pathname')
      ->pluck('pathname')
      ->map(function ($pathname) {
        return ['value' => $pathname, 'label' => $pathname];
      });

    // Hosts - Obtener hosts únicos a través de traffic logs
    $hosts = \App\Models\TrafficLog::select('host')
      ->whereIn('fingerprint', function ($query) {
        $query->select('fingerprint')->from('offerwall_conversions');
      })
      ->distinct()
      ->whereNotNull('host')
      ->orderBy('host')
      ->pluck('host')
      ->map(function ($host) {
        return ['value' => $host, 'label' => $host];
      });

    // CPType - Obtener valores únicos del campo cptype
    $cptypes = OfferwallConversion::query()
      ->select('tracked_fields->cptype as value')
      ->distinct()
      ->whereNotNull('tracked_fields->cptype')
      ->orderBy('value')
      ->pluck('value')
      ->map(function ($value) {
        return ['value' => $value, 'label' => $value];
      });

    // Placements - Obtener valores únicos del campo placement_id
    $placements = OfferwallConversion::query()
      ->select('tracked_fields->placement_id as value')
      ->distinct()
      ->whereNotNull('tracked_fields->placement_id')
      ->orderBy('value')
      ->pluck('value')
      ->map(function ($value) {
        return ['value' => $value, 'label' => $value];
      });

    // State - Obtener valores únicos del campo state
    $states = OfferwallConversion::query()
      ->select('tracked_fields->state as value')
      ->distinct()
      ->whereNotNull('tracked_fields->state')
      ->orderBy('value')
      ->pluck('value')
      ->map(function ($value) {
        return ['value' => $value, 'label' => $value];
      });

    $state =  [
      'filters' => $columnFilters,
      'sort' => $sort,
      'search' => $search,
    ];

    return Inertia::render('offerwall/conversions', [
      'rows' => $conversions,
      'state' => $state,
      'meta' => [
        'total' => $conversions->total(),
        'per_page' => $conversions->perPage(),
        'current_page' => $conversions->currentPage(),
        'last_page' => $conversions->lastPage(),
      ],
      'data' => [
        'integrations' => $integrations,
        'companies' => $companies,
        'paths' => $paths,
        'hosts' => $hosts,
        'cptypes' => $cptypes,
        'placements' => $placements,
        'states' => $states,
      ],
      'totalPayout' => $totalPayout,
    ]);
  }

  public function conversionReport(Request $request)
  {
    return new \Symfony\Component\HttpFoundation\StreamedResponse(function () use ($request) {
      // Set delimiter based on client OS for Excel compatibility
      $delimiter = $request->input('os') === 'windows' ? ';' : ',';

      // Iniciamos la query usando Joins para máxima eficiencia (igual que en conversions)
      $query = OfferwallConversion::with(['integration.company'])
        ->select([
          'offerwall_conversions.*',
          'integrations.company_id as company_id',
          'traffic_logs.host as host_name'
        ])
        ->join('integrations', 'offerwall_conversions.integration_id', '=', 'integrations.id')
        ->leftJoin('traffic_logs', function ($join) {
          $join->on('offerwall_conversions.fingerprint', '=', 'traffic_logs.fingerprint');
        });

      // Filtro de búsqueda global
      if ($search = $request->input('search')) {
        $query->where(function ($q) use ($search) {
          $q->where('offerwall_conversions.click_id', 'like', '%' . $search . '%')
            ->orWhere('offerwall_conversions.utm_source', 'like', '%' . $search . '%')
            ->orWhere('offerwall_conversions.utm_medium', 'like', '%' . $search . '%')
            ->orWhere('offerwall_conversions.fingerprint', 'like', '%' . $search . '%')
            ->orWhere('offerwall_conversions.tracked_fields->cptype', 'like', '%' . $search . '%')
            ->orWhere('offerwall_conversions.tracked_fields->placement_id', 'like', '%' . $search . '%')
            ->orWhere('integrations.name', 'like', '%' . $search . '%');
        });
      }

      // Aplicar filtros por columna
      $columnFilters = json_decode($request->input('filters', '[]'), true);
      foreach ($columnFilters as $filter) {
        if (isset($filter['id']) && isset($filter['value'])) {
          $val = (array) $filter['value'];
          switch ($filter['id']) {
            case 'from_date':
              $query->where('offerwall_conversions.created_at', '>=', $filter['value']);
              break;
            case 'to_date':
              $query->where('offerwall_conversions.created_at', '<=', $filter['value']);
              break;
            case 'integration':
              $query->whereIn('offerwall_conversions.integration_id', $val);
              break;
            case 'company':
              $query->whereIn('integrations.company_id', $val);
              break;
            case 'host':
              $query->whereIn('traffic_logs.host', $val);
              break;
            case 'pathname':
              $query->whereIn('offerwall_conversions.pathname', $val);
              break;
            case 'cptype':
              $query->whereIn('offerwall_conversions.tracked_fields->cptype', $val);
              break;
            case 'placement_id':
              $query->whereIn('offerwall_conversions.tracked_fields->placement_id', $val);
              break;
            case 'state':
              $query->whereIn('offerwall_conversions.tracked_fields->state', $val);
              break;
          }
        }
      }

      // Aplicar ordenamiento
      $sort = $request->input('sort', 'created_at:desc');
      [$sortColumn, $sortDirection] = get_sort_data($sort);

      if (in_array($sortColumn, ['cptype', 'placement_id', 'state'])) {
        $query->orderBy("offerwall_conversions.tracked_fields->$sortColumn", $sortDirection);
      } elseif ($sortColumn === 'company') {
        $query->orderBy('integrations.company_id', $sortDirection);
      } elseif ($sortColumn === 'host') {
        $query->orderBy('traffic_logs.host', $sortDirection);
      } elseif ($sortColumn === 'integration') {
        $query->orderBy('offerwall_conversions.integration_id', $sortDirection);
      } else {
        $query->orderBy("offerwall_conversions.$sortColumn", $sortDirection);
      }

      $handle = fopen('php://output', 'w');
      // Add CSV headers
      fputcsv($handle, [
        'ID',
        'Fingerprint',
        'Integration',
        'Company',
        'Payout',
        'CPType',
        'Placement ID',
        'Pathname',
        'Click ID',
        'UTM Source',
        'UTM Medium',
        'Converted At',
      ], $delimiter);

      foreach ($query->cursor() as $conversion) {
        fputcsv($handle, [
          $conversion->id,
          $conversion->fingerprint,
          $conversion->integration->name,
          $conversion->integration->company->name,
          $conversion->amount,
          $conversion->tracked_fields['cptype'] ?? '',
          $conversion->tracked_fields['placement_id'] ?? '',
          $conversion->pathname,
          $conversion->click_id,
          $conversion->utm_source,
          $conversion->utm_medium,
          $conversion->created_at->toDateTimeString(),
        ], $delimiter);
      }

      fclose($handle);
    }, 200, [
      'Content-Type' => 'text/csv',
      'Content-Disposition' => 'attachment; filename="conversions-report-' . now()->format('Y-m-d_H-i-s') . '.csv"',
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
    $request->validate([
      'name' => 'required|string|max:255',
      'description' => 'nullable|string',
      'integration_ids' => 'required|array|min:1',
      'integration_ids.*' => 'exists:integrations,id'
    ]);
    try {
      $offerwallMix = OfferwallMix::create([
        'name' => $request->name,
        'description' => $request->description,
        'is_active' => true,
      ]);

      // Attach integrations to the mix
      $offerwallMix->integrations()->attach($request->integration_ids);

      return response()->json([
        'success' => true,
        'message' => 'Offerwall mix created successfully',
        'data' => $offerwallMix->load('integrations')
      ], 201);
    } catch (\Exception $e) {
      return response()->json([
        'success' => false,
        'message' => 'Error creating offerwall mix: ' . $e->getMessage()
      ], 500);
    }
  }

  /**
   * Display the specified resource.
   */
  public function show(OfferwallMix $offerwallMix)
  {
    $offerwallMix->load(['integrations', 'integrations.company']);

    return response()->json([
      'success' => true,
      'data' => $offerwallMix
    ]);
  }

  /**
   * Show the form for editing the specified resource.
   */
  public function edit(OfferwallMix $offerwallMix)
  {
    $offerwallMix->load(['integrations', 'integrations.company']);

    return response()->json([
      'success' => true,
      'data' => $offerwallMix
    ]);
  }

  /**
   * Update the specified resource in storage.
   */
  public function update(Request $request, OfferwallMix $offerwallMix)
  {
    $request->validate([
      'name' => 'required|string|max:255',
      'description' => 'nullable|string',
      'integration_ids' => 'required|array|min:1',
      'integration_ids.*' => 'exists:integrations,id'
    ]);

    try {
      $offerwallMix->update([
        'name' => $request->name,
        'description' => $request->description,
      ]);

      // Sync integrations (this will remove old ones and add new ones)
      $offerwallMix->integrations()->sync($request->integration_ids);

      return response()->json([
        'success' => true,
        'message' => 'Offerwall mix updated successfully',
        'data' => $offerwallMix->load('integrations')
      ], 200);
    } catch (\Exception $e) {
      return response()->json([
        'success' => false,
        'message' => 'Error updating offerwall mix: ' . $e->getMessage()
      ], 500);
    }
  }

  /**
   * Remove the specified resource from storage.
   */
  public function destroy(OfferwallMix $offerwallMix)
  {
    $offerwallMix->delete();
    add_flash_message(type: "success", message: "Offerwall mix deleted successfully.");
    return  back();
  }
}
