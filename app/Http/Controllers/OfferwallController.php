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
  /**
   * Display a listing of the offerwall conversions.
   */
  public function conversions(Request $request)
  {
    $query = $this->buildConversionsQuery($request);

    // Cálculo del payout total antes de la paginación
    // Clonamos la query para no afectar la paginación con el sum
    $totalPayout = (clone $query)->sum('offerwall_conversions.amount');

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

    $cptypes = OfferwallConversion::query()
      ->select('tracked_fields->cptype as value')
      ->distinct()
      ->whereNotNull('tracked_fields->cptype')
      ->orderBy('value')
      ->pluck('value')
      ->map(function ($value) {
        return ['value' => $value, 'label' => $value];
      });

    $placements = OfferwallConversion::query()
      ->select('tracked_fields->placement_id as value')
      ->distinct()
      ->whereNotNull('tracked_fields->placement_id')
      ->orderBy('value')
      ->pluck('value')
      ->map(function ($value) {
        return ['value' => $value, 'label' => $value];
      });

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
      'filters' => json_decode($request->input('filters', '[]'), true),
      'sort' => $request->input('sort', 'created_at:desc'),
      'search' => $request->input('search'),
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
      $query = $this->buildConversionsQuery($request);
      $delimiter = $request->input('os') === 'windows' ? ';' : ',';

      $handle = fopen('php://output', 'w');
      fputcsv($handle, [
        'ID', 'Fingerprint', 'Integration', 'Company', 'Payout', 'CPType',
        'Placement ID', 'Pathname', 'Click ID', 'UTM Source', 'UTM Medium', 'Converted At',
      ], $delimiter);

      // Usamos groupBy en el cursor para evitar duplicados del join
      foreach ($query->groupBy('offerwall_conversions.id')->cursor() as $conversion) {
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
   * Builds the base query for offerwall conversions with all filters and sorting.
   *
   * @param Request $request
   * @return \Illuminate\Database\Eloquent\Builder
   */
  private function buildConversionsQuery(Request $request)
  {
    $query = OfferwallConversion::with(['integration.company'])
      ->select([
        'offerwall_conversions.*',
        'traffic_logs.host as host_name'
      ])
      ->join('integrations', 'offerwall_conversions.integration_id', '=', 'integrations.id')
      ->leftJoin('traffic_logs', 'offerwall_conversions.fingerprint', '=', 'traffic_logs.fingerprint');

    // Global search
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

    // Column filters
    $columnFilters = json_decode($request->input('filters', '[]'), true);
    foreach ($columnFilters as $filter) {
      if (isset($filter['id']) && isset($filter['value'])) {
        $val = (array) $filter['value'];
        switch ($filter['id']) {
          case 'from_date': $query->where('offerwall_conversions.created_at', '>=', $filter['value']); break;
          case 'to_date': $query->where('offerwall_conversions.created_at', '<=', $filter['value']); break;
          case 'integration': $query->whereIn('offerwall_conversions.integration_id', $val); break;
          case 'company': $query->whereIn('integrations.company_id', $val); break;
          case 'host': $query->whereIn('traffic_logs.host', $val); break;
          case 'pathname': $query->whereIn('offerwall_conversions.pathname', $val); break;
          case 'cptype': $query->whereIn('offerwall_conversions.tracked_fields->cptype', $val); break;
          case 'placement_id': $query->whereIn('offerwall_conversions.tracked_fields->placement_id', $val); break;
          case 'state': $query->whereIn('offerwall_conversions.tracked_fields->state', $val); break;
        }
      }
    }

    // Sorting
    $sort = $request->input('sort', 'created_at:desc');
    [$sortColumn, $sortDirection] = get_sort_data($sort);

    if (in_array($sortColumn, ['cptype', 'placement_id', 'state'])) {
      $query->orderBy("offerwall_conversions.tracked_fields->$sortColumn", $sortDirection);
    } elseif ($sortColumn === 'company') {
      // Assuming sorting by company name, which would require another join or denormalized column.
      // For now, sorting by company_id as a proxy.
      $query->orderBy('integrations.company_id', $sortDirection);
    } elseif ($sortColumn === 'host') {
      $query->orderBy('traffic_logs.host', $sortDirection);
    } elseif ($sortColumn === 'integration') {
      $query->orderBy('integrations.name', $sortDirection);
    } else {
      $query->orderBy("offerwall_conversions.$sortColumn", $sortDirection);
    }

    return $query;
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
