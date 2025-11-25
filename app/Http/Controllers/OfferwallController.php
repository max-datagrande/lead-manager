<?php

namespace App\Http\Controllers;

use App\Models\OfferwallConversion;
use App\Models\Integration;
use App\Models\Company;
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
    $query = OfferwallConversion::with(['integration', 'company']);

    // Apply global search filter
    if ($search = $request->input('search')) {
      $query->where(function ($q) use ($search) {
        $q->where('click_id', 'like', '%' . $search . '%')
          ->orWhere('utm_source', 'like', '%' . $search . '%')
          ->orWhere('utm_medium', 'like', '%' . $search . '%')
          ->orWhere('fingerprint', 'like', '%' . $search . '%')
          ->orWhereHas('integration', function ($q2) use ($search) {
            $q2->where('name', 'like', '%' . $search . '%');
          })
          ->orWhereHas('company', function ($q2) use ($search) {
            $q2->where('name', 'like', '%' . $search . '%');
          });
      });
    }

    // Apply column filters (e.g., from_date, to_date, integration_id, company_id)
    $columnFilters = json_decode($request->input('filters', '[]'), true);
    foreach ($columnFilters as $filter) {
      if (isset($filter['id']) && isset($filter['value'])) {
        if ($filter['id'] === 'from_date') {
          $query->whereDate('created_at', '>=', $filter['value']);
        } elseif ($filter['id'] === 'to_date') {
          $query->whereDate('created_at', '<=', $filter['value']);
        } elseif ($filter['id'] === 'integration_id') {
          $query->whereIn('integration_id', (array) $filter['value']);
        } elseif ($filter['id'] === 'company_id') {
          $query->whereIn('company_id', (array) $filter['value']);
        }
        // Add more specific column filters here if needed
      }
    }

    // Calculate total payout on the filtered query
    $totalPayout = $query->sum('amount');

    // Apply sorting
    $sort = $request->input('sort', 'created_at:desc');
    [$sortColumn, $sortDirection] = get_sort_data($sort);
    $query->orderBy($sortColumn, $sortDirection);

    $conversions = $query->paginate(15)->withQueryString();

    // Fetch data for faceted filters
    $integrations = Integration::select('id', 'name')->get()->map(function ($integration) {
      return ['value' => (string) $integration->id, 'label' => $integration->name];
    });
    $companies = Company::select('id', 'name')->get()->map(function ($company) {
      return ['value' => (string) $company->id, 'label' => $company->name];
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
        'companies' => $companies,
        'integrations' => $integrations,
      ],
      'totalPayout' => $totalPayout,
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
