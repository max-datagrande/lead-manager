<?php

namespace App\Http\Controllers\Logs;

use App\Enums\DispatchStatus;
use App\Enums\PostbackType;
use App\Enums\WorkflowStrategy;
use App\Http\Controllers\Controller;
use App\Models\Field;
use App\Models\LeadDispatch;
use App\Models\PingResult;
use App\Models\PostbackExecution;
use App\Models\PostResult;
use App\Models\Workflow;
use App\Jobs\PingPost\RetryDispatchJob;
use App\Traits\DatatableTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class LeadDispatchLogController extends Controller
{
  use DatatableTrait;

  public function index(Request $request): Response
  {
    $query = LeadDispatch::query()->with(['workflow:id,name', 'lead', 'winnerIntegration:id,name']);

    $table = $this->processDatatableQuery(
      query: $query,
      request: $request,
      searchableColumns: ['fingerprint', 'dispatch_uuid', 'utm_source'],
      filterConfig: [
        'status' => ['type' => 'exact'],
        'strategy_used' => ['type' => 'exact'],
        'workflow_id' => ['type' => 'exact'],
        'utm_source' => ['type' => 'like'],
        'from_date' => ['type' => 'from_date', 'column' => 'created_at'],
        'to_date' => ['type' => 'to_date', 'column' => 'created_at'],
      ],
      allowedSort: ['id', 'status', 'final_price', 'created_at', 'utm_source'],
      defaultSort: 'created_at:desc',
    );

    $items = collect($table['rows']->items());

    $soldUuids = $items->where('status', DispatchStatus::SOLD)->pluck('dispatch_uuid')->filter()->values()->all();

    $dispatchesWithExecutions = $soldUuids
      ? PostbackExecution::query()->whereIn('source_reference', $soldUuids)->distinct()->pluck('source_reference')->all()
      : [];

    $workflowIds = $items->pluck('workflow_id')->unique()->values()->all();

    $workflowPostbacks = DB::table('postback_workflow')
      ->join('postbacks', 'postbacks.id', '=', 'postback_workflow.postback_id')
      ->where('postbacks.type', PostbackType::INTERNAL->value)
      ->where('postbacks.is_active', true)
      ->whereIn('postback_workflow.workflow_id', $workflowIds)
      ->select('postback_workflow.workflow_id', 'postbacks.id', 'postbacks.uuid', 'postbacks.name', 'postbacks.base_url')
      ->get()
      ->groupBy('workflow_id')
      ->map(fn($items) => $items->values())
      ->all();

    return Inertia::render('ping-post/dispatches/index', [
      'rows' => $table['rows'],
      'meta' => $table['meta'],
      'state' => $table['state'],
      'data' => [
        'statusOptions' => DispatchStatus::toArray(),
        'strategyOptions' => WorkflowStrategy::toArray(),
        'workflows' => Workflow::orderBy('name')->get(['id', 'name']),
      ],
      'dispatches_with_executions' => $dispatchesWithExecutions,
      'workflow_postbacks' => $workflowPostbacks,
    ]);
  }

  public function show(LeadDispatch $dispatch): Response
  {
    $dispatch->load([
      'workflow',
      'lead',
      'winnerIntegration.company',
      'pingResults.integration.company',
      'postResults.integration.company',
      'postResults.pingResult',
      'buyerEvents.integration',
    ]);

    $rootId = $dispatch->parent_dispatch_id ?? $dispatch->id;
    $allAttempts = LeadDispatch::query()
      ->where(function ($q) use ($rootId) {
        $q->where('id', $rootId)->orWhere('parent_dispatch_id', $rootId);
      })
      ->orderBy('attempt')
      ->get(['id', 'attempt', 'status', 'started_at', 'completed_at']);

    return Inertia::render('ping-post/dispatches/show', [
      'dispatch' => $dispatch,
      'fields' => Field::all(['id', 'name', 'label']),
      'allAttempts' => $allAttempts,
    ]);
  }

  public function timeline(LeadDispatch $dispatch): Response
  {
    $dispatch->load(['workflow', 'winnerIntegration']);

    $timelineLogs = $dispatch->timelineLogs()->orderBy('logged_at')->get();

    $rootId = $dispatch->parent_dispatch_id ?? $dispatch->id;
    $allAttempts = LeadDispatch::query()
      ->where(function ($q) use ($rootId) {
        $q->where('id', $rootId)->orWhere('parent_dispatch_id', $rootId);
      })
      ->orderBy('attempt')
      ->get(['id', 'attempt', 'status', 'started_at', 'completed_at']);

    return Inertia::render('ping-post/dispatches/timeline', [
      'dispatch' => $dispatch,
      'timelineLogs' => $timelineLogs,
      'allAttempts' => $allAttempts,
    ]);
  }

  public function retry(LeadDispatch $dispatch): RedirectResponse
  {
    if (!$dispatch->status->isTerminal()) {
      return back()->with('error', 'Only terminal dispatches can be retried.');
    }

    RetryDispatchJob::dispatch($dispatch->id);

    return back()->with('success', 'Retry dispatch queued. A new attempt will appear shortly.');
  }

  public function resultDetail(string $type, int $id): JsonResponse
  {
    $model = match ($type) {
      'ping' => PingResult::with('integration')->findOrFail($id),
      'post' => PostResult::with('integration')->findOrFail($id),
      default => abort(404),
    };

    return response()->json($model);
  }
}
