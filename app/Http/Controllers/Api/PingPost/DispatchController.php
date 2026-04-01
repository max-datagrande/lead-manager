<?php

namespace App\Http\Controllers\Api\PingPost;

use App\Http\Controllers\Controller;
use App\Http\Requests\PingPost\DispatchLeadRequest;
use App\Http\Traits\ApiResponseTrait;
use App\Jobs\PingPost\DispatchLeadJob;
use App\Models\Lead;
use App\Models\Workflow;
use App\Services\LeadService;
use App\Services\PingPost\DispatchOrchestrator;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class DispatchController extends Controller
{
  use ApiResponseTrait;

  public function __construct(private readonly DispatchOrchestrator $orchestrator, private readonly LeadService $leadService) {}

  public function dispatch(DispatchLeadRequest $request, Workflow $workflow): JsonResponse
  {
    $fingerprint = $request->input('fingerprint');
    $leadId = $request->input('lead_id');
    $fields = $request->input('fields');
    $createOnMiss = $request->boolean('create_on_miss', false);

    // Build-in-one: create/find lead and save fields before dispatching
    if ($fingerprint && ($fields || $createOnMiss)) {
      try {
        $lead = $this->buildLead($fingerprint, $fields, $createOnMiss);
      } catch (ValidationException $e) {
        $errors = $e->errors();
        $message = $e->getMessage() ?? 'Validation failed';
        $statusCode = $message === 'Fingerprint not found' ? 404 : 400;

        return $this->errorResponse($message, $errors['services'] ?? $errors, $statusCode);
      }
    } else {
      $lead = $leadId ? Lead::findOrFail($leadId) : Lead::where('fingerprint', $fingerprint)->firstOrFail();
    }

    if ($workflow->execution_mode === 'async') {
      DispatchLeadJob::dispatch($workflow->id, $lead->id, $lead->fingerprint ?? $fingerprint);

      return $this->successResponse(['queued' => true, 'workflow_id' => $workflow->id], 'Lead queued for dispatch.', 202);
    }

    $dispatch = $this->orchestrator->dispatch($workflow, $lead, $lead->fingerprint ?? $fingerprint);

    return $this->successResponse(
      $dispatch->only(['dispatch_uuid', 'status', 'strategy_used', 'final_price', 'total_duration_ms']),
      'Lead dispatched.',
    );
  }

  /**
   * Creates or finds a lead by fingerprint and processes its fields.
   */
  private function buildLead(string $fingerprint, ?array $fields, bool $createOnMiss): Lead
  {
    $visitorLog = $this->leadService->validateTrafficLog($fingerprint);

    $lead = $createOnMiss ? $this->leadService->createOrFindLead($visitorLog) : $this->leadService->findLead($visitorLog);

    if (!empty($fields)) {
      $this->leadService->processLeadFields($lead, $fields);
    }

    if ($lead->wasRecentlyCreated) {
      $this->leadService->logLeadSuccess($lead);
    } else {
      $this->leadService->logLeadUpdateSuccess($lead);
    }

    return $lead;
  }
}
