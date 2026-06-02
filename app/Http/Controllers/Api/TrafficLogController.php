<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreTrafficLogRequest;
use App\Http\Requests\Api\UpdateTrafficLogRequest;
use App\Services\TrafficLog\TrafficLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Maxidev\Logger\TailLogger;
use App\Http\Traits\ApiResponseTrait;
use App\Support\SlackMessageBundler;

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
      $geolocation = $this->request->geoService()->getGeolocation();
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
    } catch (\Exception $e) {
      $message = $e->getMessage();
      $isDev = app()->environment('local');
      $errors = get_error_stack($e, $isDev);
      $statusCode = str_contains($message, 'Duplicate') ? 409 : 500;
      $this->notifySlack($message, $data);
      TailLogger::saveLog($message, 'traffic-log/store', 'error', $this->errorContext($e, $data));
      return $this->errorResponse(message: $message, status: $statusCode, errors: $errors);
    }
  }
  /**
   * Actualiza columnas de tracking de una visita ya registrada, matcheada por
   * fingerprint. Idempotente. Respuesta apta para el SDK `Catalyst.updateVisit`:
   * `data` lleva `fingerprint`, `updated_at` y el ECO de cada columna
   * actualizable que vino en el request (s10, s1, etc.) con su valor persistido.
   *
   * @param UpdateTrafficLogRequest $request
   * @return JsonResponse
   */
  public function update(UpdateTrafficLogRequest $request): JsonResponse
  {
    $data = $request->validated();
    $fingerprint = $data['fingerprint'];

    try {
      $trafficLog = $this->trafficLogService->updateVisit($fingerprint, $data);

      if (!$trafficLog) {
        return $this->errorResponse(
          message: 'No active visit found for the provided fingerprint',
          errors: ['code' => 'NO_ACTIVE_VISIT'],
          status: 404,
        );
      }

      // Eco generico: por cada columna actualizable presente en el request,
      // devolvemos su valor persistido. No hardcodear `s10`: el contrato cliente
      // es plano y libre, asi que reflejamos lo recibido (s10, s1, ...).
      $echo = [];
      foreach (TrafficLogService::UPDATABLE_COLUMNS as $column) {
        if (array_key_exists($column, $data)) {
          $echo[$column] = $trafficLog->{$column};
        }
      }

      return $this->successResponse(
        data: [
          'fingerprint' => $trafficLog->fingerprint,
          'updated_at' => optional($trafficLog->updated_at)->toIso8601String(),
          ...$echo,
        ],
        message: 'Visit updated successfully',
      );
    } catch (\Exception $e) {
      $message = $e->getMessage();
      $isDev = app()->environment('local');
      $errors = ['code' => 'UNKNOWN', 'details' => get_error_stack($e, $isDev)];
      TailLogger::saveLog($message, 'traffic-log/update', 'error', $this->errorContext($e, $data));
      return $this->errorResponse(message: $message, errors: $errors, status: 500);
    }
  }

  private function notifySlack(string $message, array $data): void
  {
    $slack = new SlackMessageBundler();
    $slack
      ->addTitle('Critical Traffic Log Failure', '🚨')
      ->addSection('The traffic log processing failed due to an unexpected error.')
      ->addKeyValue('Ip', $this->request->ip(), true)
      ->addKeyValue('Path', $data['current_page'])
      ->addKeyValue('Landing', $this->request->header('origin'))
      ->addDivider();

    $slack->addKeyValue('Error Message', $message, true, '📄');
    // Registrar en logs en modo debug, no enviar a Slack
    app()->environment('local') ? $slack->sendDebugLog('error') : $slack->sendDirect('error');
  }

  public function successLog(): void
  {
    $currentVisitor = $this->trafficLogService->getCurrentVisitor();
    TailLogger::saveLog('Traffic log successfully created from controller', 'traffic-log/store', 'info', [
      'id' => $currentVisitor->id,
      'fingerprint' => $currentVisitor->fingerprint,
      'utm_source' => $currentVisitor->utm_source,
    ]);
  }

  /**
   * Obtiene el objeto contexto de errores
   */
  public function errorContext($e, $data): array
  {
    return [
      'message' => $e->getMessage(),
      'file' => $e->getFile(),
      'line' => $e->getLine(),
      'code' => $e->getCode(),
      'type' => get_class($e),
      'request_data' => $data,
      'trace' => $e->getTrace(),
    ];
  }
}
