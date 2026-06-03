<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreTrafficLogRequest;
use App\Http\Requests\Api\UpdateTrafficLogRequest;
use App\Services\TrafficLog\TrafficLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
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
      $isDev = app()->environment('local');
      $root = $this->unwrapException($e);
      $errors = get_error_stack($e, $isDev);
      $this->notifySlack($e, $data);
      // Headline = mensaje de la causa raiz (no el wrapper generico), para que la
      // linea del log ya sea diagnostica por si sola.
      TailLogger::saveLog($root->getMessage(), 'traffic-log/store', 'error', $this->errorContext($e, $data));
      // Respuesta al cliente: mensaje generico del wrapper (no filtramos detalle SQL).
      return $this->errorResponse(message: $e->getMessage(), status: 500, errors: $errors);
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

  /**
   * Desenvuelve una excepcion hasta su causa raiz siguiendo la cadena de
   * `getPrevious()`. El flujo de traffic log envuelve toda falla en
   * `TrafficLogCreationException('Failed to create traffic log', 0, $e)`, asi que
   * sin desenvolver el mensaje/file/line serian siempre los del wrapper y no
   * dirian nada accionable. Guard de profundidad por si hubiera ciclos.
   *
   * @param \Throwable $e
   * @return \Throwable Causa raiz (la excepcion mas profunda de la cadena)
   */
  private function unwrapException(\Throwable $e): \Throwable
  {
    $root = $e;
    $depth = 0;
    while ($root->getPrevious() !== null && $depth < 10) {
      $root = $root->getPrevious();
      $depth++;
    }
    return $root;
  }

  /**
   * Sanitiza un mensaje de excepcion para Slack quitando el payload sensible.
   * Las `QueryException` de Laravel concatenan el SQL completo con los bindings
   * interpolados (IP, user-agent, query_params, etc.) tras el patron
   * `(Connection: ...)` / `SQL: ...`. Para no filtrar PII al canal nos quedamos
   * solo con la parte diagnostica del driver (SQLSTATE + descripcion). El
   * mensaje completo sigue persistiendose en el log de disco via errorContext.
   *
   * @param string $message Mensaje crudo de la excepcion
   * @return string Mensaje recortado antes del SQL/bindings
   */
  private function sanitizeErrorMessage(string $message): string
  {
    // Cortar antes del primer marcador de SQL de Laravel ("(Connection:" o "(SQL:").
    foreach (['(Connection:', '(SQL:'] as $marker) {
      $pos = stripos($message, $marker);
      if ($pos !== false) {
        $message = rtrim(substr($message, 0, $pos));
        break;
      }
    }
    return Str::limit($message, 800);
  }

  /**
   * Construye y dispara la alerta de Slack de falla critica de traffic log.
   * Enriquecida con la causa raiz desenvuelta (clase, mensaje, file:line) y un
   * subset seguro del request (sin payload completo para no filtrar PII).
   *
   * @param \Throwable $e Excepcion capturada (se desenvuelve a su causa raiz)
   * @param array<string, mixed> $data Datos validados del request
   * @return void
   */
  private function notifySlack(\Throwable $e, array $data): void
  {
    $root = $this->unwrapException($e);

    // Subset seguro del request para debug (sin query_params crudos ni PII).
    $safeKeys = ['current_page', 'user_agent', 'landing_id', 's1', 's2', 's3', 's4', 's10'];
    $requestSubset = array_intersect_key($data, array_flip($safeKeys));

    $slack = new SlackMessageBundler();
    $slack
      ->addTitle('Critical Traffic Log Failure', '🚨')
      ->addSection('The traffic log processing failed due to an unexpected error.')
      ->addKeyValue('Environment', app()->environment(), true)
      ->addKeyValue('Exception', get_class($root), true)
      ->addKeyValue('Root Message', '```' . $this->sanitizeErrorMessage($root->getMessage()) . '```', false, '📄')
      ->addKeyValue('Origin', basename($root->getFile()) . ':' . $root->getLine(), true)
      ->addDivider()
      ->addKeyValue('Ip', $this->request->ip(), true)
      ->addKeyValue('Path', $data['current_page'] ?? 'n/a')
      ->addKeyValue('Landing', $this->request->header('origin') ?? 'n/a')
      ->addKeyValue('User Agent', Str::limit($data['user_agent'] ?? ($this->request->userAgent() ?? 'n/a'), 300))
      ->addKeyValue('Request Data', '```' . Str::limit(json_encode($requestSubset, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), 1500) . '```');

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
   * Obtiene el objeto contexto de errores para logging.
   * Incluye tanto la excepcion externa (wrapper) como la causa raiz
   * desenvuelta, que es la que realmente apunta al punto de falla.
   *
   * @param \Throwable $e Excepcion capturada
   * @param array<string, mixed> $data Datos del request
   * @return array<string, mixed>
   */
  public function errorContext(\Throwable $e, array $data): array
  {
    $root = $this->unwrapException($e);
    return [
      'message' => $e->getMessage(),
      'file' => $e->getFile(),
      'line' => $e->getLine(),
      'code' => $e->getCode(),
      'type' => get_class($e),
      'root_cause' => [
        'message' => $root->getMessage(),
        'class' => get_class($root),
        'file' => $root->getFile(),
        'line' => $root->getLine(),
      ],
      'request_data' => $data,
      'trace' => $root->getTrace(),
    ];
  }
}
