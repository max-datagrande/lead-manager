<?php

namespace App\Http\Controllers\Api\PingPost;

use App\Http\Controllers\Controller;
use App\Http\Requests\PingPost\DispatchLeadRequest;
use App\Http\Traits\ApiResponseTrait;
use App\Jobs\PingPost\DispatchLeadJob;
use App\Models\Lead;
use App\Models\Workflow;
use App\Services\PingPost\DispatchOrchestrator;
use Illuminate\Http\JsonResponse;

class DispatchController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        private readonly DispatchOrchestrator $orchestrator,
    ) {}

    public function dispatch(DispatchLeadRequest $request, Workflow $workflow): JsonResponse
    {
        $fingerprint = $request->input('fingerprint');
        $leadId = $request->input('lead_id');

        $lead = $leadId
            ? Lead::findOrFail($leadId)
            : Lead::where('fingerprint', $fingerprint)->firstOrFail();

        if ($workflow->execution_mode === 'async') {
            DispatchLeadJob::dispatch($workflow->id, $lead->id, $lead->fingerprint ?? $fingerprint);

            return $this->successResponse(
                ['queued' => true, 'workflow_id' => $workflow->id],
                'Lead queued for dispatch.',
                202,
            );
        }

        $dispatch = $this->orchestrator->dispatch($workflow, $lead, $lead->fingerprint ?? $fingerprint);

        return $this->successResponse(
            $dispatch->only(['dispatch_uuid', 'status', 'strategy_used', 'final_price', 'total_duration_ms']),
            'Lead dispatched.',
        );
    }
}
