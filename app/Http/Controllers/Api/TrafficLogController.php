<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreTrafficLogRequest;
use App\Services\TrafficLog\TrafficLogService;
use App\Services\TrafficLog\TrafficLogCreationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Maxidev\Logger\TailLogger;
use App\Http\Traits\ApiResponseTrait;

/**
 * Controlador para manejo de traffic logs
 *
 * Refactorizado para usar arquitectura de servicios especializados
 * siguiendo principios SOLID y separación de responsabilidades.
 *
 * Utiliza Request macro para acceso directo a GeolocationService
 * mediante $request->geoService() para mejor DX.
 */
class TrafficLogController extends Controller
{
  use ApiResponseTrait;

  public function __construct(private TrafficLogService $trafficLogService, protected Request $request) {}

  /**
   * Almacena un nuevo registro de tráfico
   *
   * Utiliza el Request macro para acceder al GeolocationService
   * de forma directa y elegante: $request->geoService()
   *
   * @param StoreTrafficLogRequest $request Request con macro geoService() disponible
   * @return JsonResponse
   */
  public function store(StoreTrafficLogRequest $request): JsonResponse
  {
    //Logging de la request
    $data = $request->validated();
    $userAgent = $data['user_agent'];
    $ip = $this->request->geoService()->getIpAddress();
    TailLogger::saveLog('Received request to create traffic log', 'traffic-log/store', 'info', compact('ip', 'userAgent'));
    try {
      // Crear el traffic log usando el servicio especializado
      $trafficLog = $this->trafficLogService->createTrafficLog($data);
      $fingerprint = $trafficLog->fingerprint;
      $geolocation = collect($this->request->geoService()->getGeolocation())
        ->only(['city', 'region', 'country', 'postal', 'timezone', 'currency', 'ip'])
        ->toArray();
      //Loggin
      $this->successLog($trafficLog);
      // Usar el trait para respuesta exitosa
      return $this->successResponse(
        data: [
          'device_type' => $trafficLog->device_type,
          'is_bot' => $trafficLog->is_bot,
        ],
        message: 'Traffic log created successfully',
        status: 201,
        meta: compact('fingerprint', 'geolocation'),
      );
    } catch (TrafficLogCreationException $e) {
      // Manejar errores específicos del servicio de traffic log (duplicados, etc.)
      $message = 'Traffic log creation failed: ' . $e->getMessage();
      $statusCode = str_contains($e->getMessage(), 'Duplicate') ? 409 : 422;
      TailLogger::saveLog($message, 'traffic-log/store', 'error', $this->errorContext($e, $data));
      return $this->errorResponse($message, $e->getTrace(), $statusCode);
    } catch (\Exception $e) {
      // Manejar cualquier otro error inesperado
      $message = 'An unexpected error occurred while processing the traffic log';
      TailLogger::saveLog($message . ': ' . $e->getMessage(), 'traffic-log/store', 'error', $this->errorContext($e, $data));
      return $this->errorResponse($message, $e->getTrace(), 500);
    }
  }

  public function successLog(): void
  {
    $currentVisitor = $this->trafficLogService->getCurrentVisitor();
    TailLogger::saveLog('Traffic log successfully created from controller', 'traffic-log/store', 'info', [
      'id' => $currentVisitor->id,
      'fingerprint' => $currentVisitor->fingerprint,
      'traffic_source' => $currentVisitor->traffic_source,
    ]);
  }

  /**
   * Obtiene el objeto contexto de errores
   */
  public function errorContext($e, $data): array
  {
    return ['request_data' => $data, 'trace' => $e->getTrace()];
  }
}
