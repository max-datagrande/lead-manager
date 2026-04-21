<?php

namespace App\Http\Controllers\Api\LeadQuality;

use App\Http\Controllers\Controller;
use App\Http\Requests\LeadQuality\SendChallengeRequest;
use App\Http\Requests\LeadQuality\VerifyChallengeRequest;
use App\Http\Traits\ApiResponseTrait;
use App\Models\Lead;
use App\Models\Workflow;
use App\Services\LeadQuality\ChallengeIssuerService;
use App\Services\LeadQuality\ChallengeVerifierService;
use App\Traits\MergesLeadFieldsTrait;
use Illuminate\Http\JsonResponse;

class ChallengeController extends Controller
{
  use ApiResponseTrait;
  use MergesLeadFieldsTrait;

  public function __construct(private readonly ChallengeIssuerService $issuer, private readonly ChallengeVerifierService $verifier) {}

  public function send(SendChallengeRequest $request): JsonResponse
  {
    $data = $request->validated();

    $workflow = Workflow::findOrFail($data['workflow_id']);
    $lead = Lead::findOrFail($data['lead_id']);

    // Merge any fields the landing wants to persist right before the challenge.
    // Propagates ValidationException upwards so the request aborts atomically
    // — partial writes would leave the landing guessing what did or didn't stick.
    $this->mergeLeadFields($lead, $data['fields'] ?? null);

    $context = array_filter([
      'to' => $data['to'] ?? null,
      'channel' => $data['channel'] ?? null,
      'locale' => $data['locale'] ?? null,
    ]);

    $result = $this->issuer->issue($workflow, $lead, $data['fingerprint'], $context);

    if ($result['challenges'] === [] && $result['errors'] === []) {
      return $this->successResponse(data: $result, message: 'No validation rules apply to this workflow; dispatch can proceed directly.');
    }

    if ($result['challenges'] === [] && $result['errors'] !== []) {
      return $this->errorResponse(message: 'All applicable challenges failed to send.', status: 502, errors: ['challenges' => $result['errors']]);
    }

    return $this->successResponse(data: $result, message: 'Challenge(s) sent.');
  }

  public function verify(VerifyChallengeRequest $request): JsonResponse
  {
    $data = $request->validated();

    $result = $this->verifier->verify($data['challenge_token'], (string) $data['code'], [
      'to' => $data['to'] ?? null,
    ]);

    if ($result['verified']) {
      return $this->successResponse(data: $result, message: 'Challenge verified.');
    }

    $status = match ($result['status']) {
      'retry' => 422,
      'expired', 'failed' => 410,
      'invalid_token', 'not_found' => 404,
      default => 422,
    };

    return $this->errorResponse(message: $result['reason'] ?? 'Challenge not verified.', status: $status, errors: $result);
  }
}
