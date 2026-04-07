<?php

namespace App\Http\Controllers\Logs;

use App\Enums\DispatchStatus;
use App\Enums\PostbackType;
use App\Http\Controllers\Controller;
use App\Models\Field;
use App\Models\LeadDispatch;
use App\Models\PostbackExecution;
use App\Models\Workflow;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class LeadDispatchLogController extends Controller
{
  public function index(): Response
  {
    $dispatches = LeadDispatch::query()
      ->with(['workflow', 'lead', 'winnerIntegration'])
      ->latest()
      ->paginate(50);

    $soldUuids = collect($dispatches->items())->where('status', DispatchStatus::SOLD)->pluck('dispatch_uuid')->filter()->values()->all();

    $dispatchesWithExecutions = $soldUuids
      ? PostbackExecution::query()->whereIn('source_reference', $soldUuids)->distinct()->pluck('source_reference')->all()
      : [];

    $workflowIds = collect($dispatches->items())->pluck('workflow_id')->unique()->values()->all();

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
      'dispatches' => $dispatches,
      'workflows' => Workflow::orderBy('name')->get(['id', 'name']),
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
    ]);

    return Inertia::render('ping-post/dispatches/show', [
      'dispatch' => $dispatch,
      'fields' => Field::all(['id', 'name', 'label']),
    ]);
  }
}
