<?php

namespace App\Http\Controllers\Api\Offerwall;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use App\Models\OfferwallMix;
use App\Services\LeadService;
use App\Services\Offerwall\MixService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Maxidev\Logger\TailLogger;

class MixController extends Controller
{
  use ApiResponseTrait;

  protected $mixService;
  protected $leadService;

  public function __construct(MixService $mixService, LeadService $leadService)
  {
    $this->mixService = $mixService;
    $this->leadService = $leadService;
  }

  public function trigger(Request $request, OfferwallMix $offerwallMix): JsonResponse
  {
    $validated = $request->validate([
      'fingerprint' => 'required|string',
      'placement' => 'nullable|string|max:255',
      'fields' => 'nullable|array',
      'create_on_miss' => 'nullable|boolean',
    ]);

    $fingerprint = $validated['fingerprint'];
    $fields = $validated['fields'] ?? null;
    $placement = $validated['placement'] ?? null;
    $createOnMiss = $validated['create_on_miss'] ?? false;

    // Optional: Update lead fields if provided
    if (!empty($fields)) {
      TailLogger::saveLog('Received request to update lead fields within offerwall mix trigger', 'offerwall/mix/trigger', 'info', compact('fingerprint', 'fields', 'createOnMiss'));
      try {
        $visitorLog = $this->leadService->validateTrafficLog($fingerprint);

        if ($createOnMiss) {
          $lead = $this->leadService->createOrFindLead($visitorLog);
        } else {
          $lead = $this->leadService->findLead($visitorLog);
        }

        $this->leadService->processLeadFields($lead, $fields);

        if ($lead->wasRecentlyCreated) {
          $this->leadService->logLeadSuccess($lead);
        } else {
          $this->leadService->logLeadUpdateSuccess($lead);
        }
      } catch (ValidationException $e) {
        $errors = $e->errors();
        $serviceErrors = $errors['services'] ?? null;
        $message = $e->getMessage() ?? 'Validation failed';
        // As per user request, fail the entire operation if fingerprint is not found (and create flag is false)
        $statusCode = $message === 'Fingerprint not found' ? 404 : 400;
        return $this->errorResponse($message, $serviceErrors ?? $errors, $statusCode);
      } catch (\Exception $e) {
        $message = 'An unexpected error occurred while updating lead fields';
        TailLogger::saveLog($message . ': ' . $e->getMessage(), 'offerwall/mix/trigger', 'error', [
          'fingerprint' => $fingerprint,
          'fields' => $fields,
          'exception' => $e->getMessage(),
        ]);
        return $this->errorResponse($message, null, 500);
      }
    }

    $result = $this->mixService->fetchAndAggregateOffers(
      $offerwallMix,
      $fingerprint,
      $placement,
    );

    // Extraer información de la respuesta del servicio
    $success = $result['success'] ?? false;
    $message = $result['message'] ?? 'Unknown response';
    $data = $result['data'] ?? null;
    $statusCode = $result['status_code'] ?? 500;
    $meta = $result['meta'] ?? null;

    if ($success) {
      return $this->successResponse($data, $message, $statusCode, $meta);
    } else {
      return $this->errorResponse($message, null, $statusCode);
    }
  }
}
