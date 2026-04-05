<?php

namespace App\Http\Controllers\Api;

use App\Enums\PostbackSource;
use App\Enums\PostbackType;
use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use App\Models\Field;
use App\Models\Lead;
use App\Models\LeadFieldResponse;
use App\Models\Postback;
use App\Models\TrafficLog;
use App\Services\PostbackFireService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InternalPostbackFireController extends Controller
{
  use ApiResponseTrait;

  public function __construct(protected PostbackFireService $fireService) {}

  /**
   * Recibe postback interno con fingerprint.
   * GET /v1/postback/fire/{uuid}/{fingerprint}?field1=value1&field2=value2
   *
   * 1. Valida postback internal + activo
   * 2. Busca lead por fingerprint
   * 3. Guarda/actualiza query params como field responses en el lead
   * 4. Resuelve tokens de traffic log
   * 5. Combina todo y delega al servicio
   */
  public function fire(Request $request, string $uuid, string $fingerprint): JsonResponse
  {
    try {
      $postback = Postback::query()
        ->where('uuid', $uuid)
        ->where('type', PostbackType::INTERNAL)
        ->active()
        ->firstOrFail();

      $lead = Lead::query()->where('fingerprint', $fingerprint)->first();

      if (!$lead) {
        return $this->errorResponse('Lead not found for this fingerprint.', status: 404);
      }

      // Guardar fields que llegan por query params
      $inboundParams = $request->query();

      foreach ($inboundParams as $fieldName => $value) {
        if ($value === null) {
          continue;
        }

        $field = Field::query()->where('name', $fieldName)->first();

        if ($field) {
          LeadFieldResponse::updateOrCreate(
            ['lead_id' => $lead->id, 'field_id' => $field->id, 'fingerprint' => $fingerprint],
            ['value' => $value],
          );
        }
      }

      // Resolver tokens de traffic log
      $trafficLog = TrafficLog::query()->where('fingerprint', $fingerprint)->latest()->first();
      $trafficValues = [];

      if ($trafficLog) {
        foreach ($postback->param_mappings as $tokenName) {
          if (str_starts_with($tokenName, 'traffic.')) {
            $column = str_replace('traffic.', '', $tokenName);
            $trafficValues[$tokenName] = (string) ($trafficLog->{$column} ?? '');
          }
        }
      }

      // Combinar: query params (ya guardados en lead) + traffic values
      $params = array_merge($inboundParams, $trafficValues);

      $execution = $this->fireService->fireInternal(
        uuid: $postback->uuid,
        params: $params,
        source: PostbackSource::EXTERNAL_API,
        sourceReference: $fingerprint,
      );

      return $this->successResponse(
        data: [
          'execution_uuid' => $execution->execution_uuid,
          'status' => $execution->status->value,
        ],
        message: $execution->status->message(),
      );
    } catch (ModelNotFoundException) {
      return $this->errorResponse('Internal postback not found or inactive.', status: 404);
    } catch (\InvalidArgumentException $e) {
      return $this->errorResponse($e->getMessage(), status: 422);
    }
  }
}
