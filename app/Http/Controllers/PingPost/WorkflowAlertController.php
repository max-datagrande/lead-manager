<?php

namespace App\Http\Controllers\PingPost;

use App\Http\Controllers\Controller;
use App\Http\Requests\PingPost\StoreWorkflowAlertRequest;
use App\Models\AlertChannel;
use App\Models\Workflow;
use App\Models\WorkflowAlert;
use Illuminate\Http\JsonResponse;

class WorkflowAlertController extends Controller
{
  public function store(StoreWorkflowAlertRequest $request, Workflow $workflow): JsonResponse
  {
    $alertChannelId = $request->validated('alert_channel_id');

    $exists = WorkflowAlert::query()->where('workflow_id', $workflow->id)->where('alert_channel_id', $alertChannelId)->exists();

    if ($exists) {
      return response()->json(['success' => false, 'message' => 'Alert channel already associated.'], 422);
    }

    $workflowAlert = WorkflowAlert::query()->create([
      'workflow_id' => $workflow->id,
      'alert_channel_id' => $alertChannelId,
      'is_active' => true,
    ]);

    $workflowAlert->load('alertChannel');

    return response()->json([
      'success' => true,
      'message' => 'Alert channel associated.',
      'data' => [
        'id' => $workflowAlert->id,
        'workflow_id' => $workflowAlert->workflow_id,
        'alert_channel_id' => $workflowAlert->alert_channel_id,
        'is_active' => $workflowAlert->is_active,
        'alert_channel' => [
          'id' => $workflowAlert->alertChannel->id,
          'name' => $workflowAlert->alertChannel->name,
          'type' => $workflowAlert->alertChannel->type,
        ],
      ],
    ]);
  }

  public function destroy(Workflow $workflow, AlertChannel $alertChannel): JsonResponse
  {
    WorkflowAlert::query()->where('workflow_id', $workflow->id)->where('alert_channel_id', $alertChannel->id)->delete();

    return response()->json(['success' => true, 'message' => 'Alert channel detached.']);
  }
}
