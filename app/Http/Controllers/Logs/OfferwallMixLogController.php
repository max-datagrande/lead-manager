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

    $logs = OfferwallMixLog::query()
      ->with('offerwallMix:id,name')
      ->orderBy($col, $dir)
      ->paginate($request->get('limit', 15));

    return Inertia::render('logs/mixes/index', [
      'rows' => $logs,
      'state' => [
        'sort' => $sort,
        'filters' => []
      ],
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
