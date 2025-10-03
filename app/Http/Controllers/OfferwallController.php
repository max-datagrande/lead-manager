<?php

namespace App\Http\Controllers;

use App\Models\OfferwallConversion;
use App\Models\Integration;
use App\Models\Company;
use Illuminate\Http\Request;
use Inertia\Inertia;

class OfferwallController extends Controller
{
  /**
   * Display a listing of the resource.
   */
  public function index(Request $request)
  {
    return Inertia::render('offerwall/index', [
      'rows' => [],
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
    $sort = $request->get('sort', 'created_at:desc');
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

    return Inertia::render('offerwall/conversions', [
      'rows' => $conversions,
      'totalPayout' => $totalPayout,
      'state' => $request->only(['sort', 'direction', 'search']),
      'filters' => ['columnFilters' => $columnFilters],
      'integrations' => $integrations,
      'companies' => $companies,
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
  public function show(string $id)
  {
    //
  }

  /**
   * Show the form for editing the specified resource.
   */
  public function edit(string $id)
  {
    //
  }

  /**
   * Update the specified resource in storage.
   */
  public function update(Request $request, string $id)
  {
    //
  }

  /**
   * Remove the specified resource from storage.
   */
  public function destroy(string $id)
  {
    //
  }
}
