<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\LeadService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Maxidev\Logger\TailLogger;
use App\Http\Traits\ApiResponseTrait;

/**
 * @description Controller to handle lead data submissions via API.
 */
class LeadController extends Controller
{
  use ApiResponseTrait;

  protected LeadService $leadService;

  public function __construct(LeadService $leadService)
  {
    $this->leadService = $leadService;
  }

  public function getLeadDetails($fingerprint)
  {
    $lead = \App\Models\Lead::where('fingerprint', $fingerprint)->first();

    if (!$lead) {
      return response()->json(['message' => 'Lead not found'], 404);
    }

    $lead->load(['leadFieldResponses.field']);

    // Format the response
    $fields = $lead->leadFieldResponses->map(function ($response) {
      return [
        'id' => $response->field->id ?? null,
        'name' => $response->field->name ?? 'Unknown',
        'label' => $response->field->label ?? $response->field->name ?? 'Unknown',
        'value' => $response->value,
      ];
    });

    return response()->json([
      'lead' => $lead,
      'fields' => $fields,
    ]);
  }
  /**
   * Store a newly created lead and its field responses in storage.
   *
   * @param  \Illuminate\Http\Request  $request
   * @return \Illuminate\Http\JsonResponse
   */
  public function store(Request $request)
  {
    // Validar que el fingerprint esté presente
    $validatedData = $request->validate([
      'fingerprint' => 'required|string|max:255',
      'fields' => 'nullable|array',
    ]);

    $fingerprint = $validatedData['fingerprint'];
    $fields = $validatedData['fields'] ?? [];

    // Logging de la request
    TailLogger::saveLog('Received request to create lead', 'leads/store', 'info', compact('fingerprint', 'fields'));

    try {
      // Validar traffic log y detectar bots
      $visitorLog = $this->leadService->validateTrafficLog($fingerprint);
      // Crear o encontrar el lead
      $lead = $this->leadService->createOrFindLead($visitorLog);
      $isLeadCreated = $lead->wasRecentlyCreated;
      if (empty($fields)) {
        $this->leadService->logLeadSuccess($lead);
        return $this->successResponse(
          data: [
            'fingerprint' => $fingerprint,
            'is_new' => $isLeadCreated,
          ],
          message: $isLeadCreated ? 'Lead registered successfully.' : 'Lead processed successfully.',
          status: $isLeadCreated ? 201 : 200,
        );
      }
      // Procesar campos
      $fieldResults = $this->leadService->processLeadFields($lead, $fields);

      // Log de éxito
      $this->leadService->logLeadSuccess($lead);

      // Preparar respuesta
      $responseData = [
        'fingerprint' => $fingerprint,
        'is_new' => $isLeadCreated,
      ];

      if ($fieldResults['created_count'] > 0 || $fieldResults['updated_count'] > 0) {
        $responseData['fields_summary'] = [
          'created_fields' => $fieldResults['created_fields'],
          'updated_fields' => $fieldResults['updated_fields'],
        ];
      }

      return $this->successResponse(
        data: $responseData,
        message: $isLeadCreated ? 'Lead registered successfully.' : 'Lead processed successfully.',
        status: $isLeadCreated ? 201 : 200,
      );
    } catch (ValidationException $e) {
      // Manejar errores de validación específicos
      $errors = $e->errors();
      $serviceErrors = $errors['services'] ?? null;
      $message = $e->getMessage() ?? 'Validation failed';
      $statusCode = $message === 'Fingerprint not found' ? 404 : 400;
      return $this->errorResponse($message, $serviceErrors ?? $errors, $statusCode);
    } catch (\Exception $e) {
      $message = 'An unexpected error occurred while processing the lead';
      TailLogger::saveLog($message . ': ' . $e->getMessage(), 'leads/store', 'error', [
        'fingerprint' => $fingerprint,
        'fields' => $fields,
        'exception' => $e->getMessage(),
        'trace' => $e->getTraceAsString(),
      ]);
      return $this->errorResponse($message, $e->getTrace(), 500);
    }
  }

  /**
   * Update lead field responses for an existing lead.
   *
   * @param  \Illuminate\Http\Request  $request
   * @return \Illuminate\Http\JsonResponse
   */
  public function update(Request $request)
  {
    // Validar que el fingerprint y fields estén presentes
    $validatedData = $request->validate([
      'fingerprint' => 'required|string|max:255',
      'fields' => 'required|array|min:1', // fields es obligatorio para update
    ]);

    $fingerprint = $validatedData['fingerprint'];
    $fields = $validatedData['fields'];

    // Logging de la request
    TailLogger::saveLog('Received request to update lead fields', 'leads/update', 'info', compact('fingerprint', 'fields'));

    try {
      // Validar que el traffic log exista y no sea bot
      $visitorLog = $this->leadService->validateTrafficLog($fingerprint);

      // Verificar que el lead exista (NO crear si no existe)
      $lead = $this->leadService->findLead($visitorLog);

      // Procesar campos
      $fieldResults = $this->leadService->processLeadFields($lead, $fields);

      // Log de éxito
      $this->leadService->logLeadUpdateSuccess($lead);

      // Preparar respuesta
      $responseData = [
        'fingerprint' => $fingerprint,
      ];
      if ($fieldResults['created_count'] > 0 || $fieldResults['updated_count'] > 0) {
        $responseData['fields_summary'] = [
          'created_fields' => $fieldResults['created_fields'],
          'updated_fields' => $fieldResults['updated_fields'],
        ];
      }
      return $this->successResponse(data: $responseData, message: 'Lead fields updated successfully.', status: 200);
    } catch (ValidationException $e) {
      // Manejar errores de validación específicos
      $errors = $e->errors();
      $serviceErrors = $errors['services'] ?? null;
      $message = $e->getMessage() ?? 'Validation failed';
      $statusCode = $message === 'Fingerprint not found' ? 404 : 400;
      return $this->errorResponse($message, $serviceErrors ?? $errors, $statusCode);
    } catch (\Exception $e) {
      $message = 'An unexpected error occurred while updating lead fields';
      TailLogger::saveLog($message . ': ' . $e->getMessage(), 'leads/update', 'error', [
        'fingerprint' => $fingerprint,
        'fields' => $fields,
        'exception' => $e->getMessage(),
        'trace' => $e->getTraceAsString(),
      ]);
      return $this->errorResponse($message, $e->getTrace(), 500);
    }
  }
  public function submit(Request $request)
  {
    // Validar que el fingerprint y fields estén presentes
    $validatedData = $request->validate([
      'fingerprint' => 'required|string|max:255',
    ]);

    $fingerprint = $validatedData['fingerprint'];
    $fields = $request->except(['fingerprint']);

    // Logging de la request
    TailLogger::saveLog('Received request to update lead fields', 'leads/update', 'info', compact('fingerprint', 'fields'));

    try {
      // Validar que el traffic log exista y no sea bot
      $visitorLog = $this->leadService->validateTrafficLog($fingerprint);

      // Verificar que el lead exista (NO crear si no existe)
      $lead = $this->leadService->findLead($visitorLog);
      // Procesar campos
      $fieldResults = $this->leadService->processLeadFields($lead, $fields);

      // Log de éxito
      $this->leadService->logLeadUpdateSuccess($lead);

      // Preparar respuesta
      $responseData = [
        'fingerprint' => $fingerprint,
      ];
      if ($fieldResults['created_count'] > 0 || $fieldResults['updated_count'] > 0) {
        $responseData['fields_summary'] = [
          'created_fields' => $fieldResults['created_fields'],
          'updated_fields' => $fieldResults['updated_fields'],
        ];
      }
      return $this->successResponse(data: $responseData, message: 'Lead fields updated successfully.', status: 200);
    } catch (ValidationException $e) {
      // Manejar errores de validación específicos
      $errors = $e->errors();
      $serviceErrors = $errors['services'] ?? null;
      $message = $e->getMessage() ?? 'Validation failed';
      $statusCode = $message === 'Fingerprint not found' ? 404 : 400;
      return $this->errorResponse($message, $serviceErrors ?? $errors, $statusCode);
    } catch (\Exception $e) {
      $message = 'An unexpected error occurred while updating lead fields';
      TailLogger::saveLog($message . ': ' . $e->getMessage(), 'leads/update', 'error', [
        'fingerprint' => $fingerprint,
        'fields' => $fields,
        'exception' => $e->getMessage(),
        'trace' => $e->getTraceAsString(),
      ]);
      return $this->errorResponse($message, $e->getTrace(), 500);
    }
  }
}
