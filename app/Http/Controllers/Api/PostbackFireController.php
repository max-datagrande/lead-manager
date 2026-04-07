<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use App\Models\PostbackExecution;
use App\Services\PostbackFireService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PostbackFireController extends Controller
{
  use ApiResponseTrait;

  public function __construct(protected PostbackFireService $fireService) {}

  /**
   * Recibe postback inbound de un partner externo.
   * GET /v1/postback/fire/{uuid}
   */
  public function fire(Request $request, string $uuid): JsonResponse
  {
    try {
      $execution = $this->fireService->handleInbound(
        uuid: $uuid,
        inboundParams: $request->query(),
        ipAddress: $request->ip(),
        userAgent: $request->userAgent(),
      );
      return $this->successResponse(
        data: [
          'execution_uuid' => $execution->execution_uuid,
          'status' => $execution->status->value,
        ],
        message: $execution->status->message(),
      );
    } catch (ModelNotFoundException) {
      return $this->errorResponse('Postback not found or inactive.', status: 404);
    } catch (\InvalidArgumentException $e) {
      return $this->errorResponse($e->getMessage(), status: 422);
    }
  }

  /**
   * Consulta el estado de una ejecución.
   * GET /v1/postback/execution/{executionUuid}
   */
  public function executionStatus(string $executionUuid): JsonResponse
  {
    $execution = PostbackExecution::query()->where('execution_uuid', $executionUuid)->first();

    if (!$execution) {
      return $this->errorResponse('Execution not found.', status: 404);
    }

    return $this->successResponse([
      'execution_uuid' => $execution->execution_uuid,
      'status' => $execution->status->value,
      'attempts' => $execution->attempts,
      'dispatched_at' => $execution->dispatched_at?->toISOString(),
      'completed_at' => $execution->completed_at?->toISOString(),
    ]);
  }
}
