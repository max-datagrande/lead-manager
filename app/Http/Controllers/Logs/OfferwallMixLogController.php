<?php

namespace App\Http\Controllers\Logs;

use App\Models\OfferwallMixLog;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;

class OfferwallMixLogController extends Controller
{
  public function index(Request $request)
  {
    $sort = $request->get('sort', 'created_at:desc');
    [$col, $dir] = get_sort_data($sort);
    $columnFilters = json_decode($request->input('filters', '[]'), true);
    $query = OfferwallMixLog::query();

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
    $perPage = $request->input('per_page', 15);

    $logs = $query->with('offerwallMix:id,name')
      ->orderBy($col, $dir)
      ->paginate($perPage)->withQueryString();

    $state =  [
      'filters' => $columnFilters,
      'sort' => $sort,
      'search' => $search,
    ];

    return Inertia::render('logs/mixes/index', [
      'rows' => $logs,
      'state' =>  $state,
      'meta' => [
        'total' => $logs->total(),
        'per_page' => $logs->perPage(),
        'current_page' => $logs->currentPage(),
        'last_page' => $logs->lastPage(),
      ],
      'data' => []
    ]);
  }

  public function show(OfferwallMixLog $offerwallMixLog)
  {
    $offerwallMixLog->load([
      'offerwallMix:id,name',
      'integrationCallLogs' => function ($query) {
        $query->with('integration:id,name')->orderBy('created_at', 'asc');
      }
    ]);
    return Inertia::render('logs/mixes/show', [
      'log' => $offerwallMixLog,
    ]);
  }
}
