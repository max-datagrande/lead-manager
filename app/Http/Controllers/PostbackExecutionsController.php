<?php

namespace App\Http\Controllers;

use App\Enums\ExecutionStatus;
use App\Enums\FireMode;
use App\Models\Postback;
use App\Models\PostbackExecution;
use App\Traits\DatatableTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PostbackExecutionsController extends Controller
{
  use DatatableTrait;

  public function index(Request $request): Response
  {
    $query = PostbackExecution::query()->with('postback:id,name,fire_mode');

    // Apply fire_mode relation filter manually (not supported natively by DatatableTrait)
    $filters = json_decode($request->input('filters', '[]'), true) ?? [];
    $fireModeFilter = collect($filters)->firstWhere('id', 'fire_mode');

    if ($fireModeFilter && !empty($fireModeFilter['value'])) {
      $values = (array) $fireModeFilter['value'];
      $query->whereHas('postback', fn($q) => $q->whereIn('fire_mode', $values));
    }

    $table = $this->processDatatableQuery(
      query: $query,
      request: $request,
      searchableColumns: ['execution_uuid', 'outbound_url', 'ip_address'],
      filterConfig: [
        'status' => ['type' => 'lower'],
        'postback_id' => ['type' => 'exact'],
        'from_date' => ['type' => 'from_date', 'column' => 'created_at'],
        'to_date' => ['type' => 'to_date', 'column' => 'created_at'],
      ],
      allowedSort: ['id', 'status', 'attempts', 'dispatched_at', 'completed_at', 'created_at'],
      defaultSort: 'created_at:desc',
    );

    return Inertia::render('postbacks/executions', [
      'rows' => $table['rows'],
      'meta' => $table['meta'],
      'state' => $table['state'],
      'data' => [
        'statusOptions' => ExecutionStatus::toArray(),
        'fireModeOptions' => FireMode::toArray(),
        'postbacks' => Postback::query()->active()->select('id', 'name')->orderBy('name')->get(),
      ],
    ]);
  }

  public function dispatchLogs(PostbackExecution $execution): JsonResponse
  {
    return response()->json([
      'success' => true,
      'data' => $execution->dispatchLogs()->orderBy('attempt_number')->get(),
    ]);
  }
}
