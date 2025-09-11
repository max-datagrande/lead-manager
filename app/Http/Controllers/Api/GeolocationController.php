<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\GeolocationRequest;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Http\Request;
use App\Libraries\IpApi;
use Maxidev\Logger\TailLogger;


class GeolocationController extends Controller
{

  public function __construct(private IpApi $ipApi, protected Request $request) {}

  /**
   * Obtener información de geolocalización por dirección IP
   *
   * @var Request $request
   * @param GeolocationRequest $request
   * @return JsonResponse
   */
  public function getLocationByIp(GeolocationRequest $request): JsonResponse
  {
    try {
      $ipRequested = $request->validated()['ip'];
      // Log de la petición para auditoría
      TailLogger::saveLog('Petición de geolocalización recibida', 'clients/geolocation', 'info', [
        'ip_requested' => $ipRequested,
        'ip_client' => $this->request->ip(),
        'user_agent' => $this->request->userAgent(),
        'origin' => $this->request->header('Origin'),
        'referer' => $this->request->header('Referer')
      ]);

      // Obtener datos de geolocalización usando IpApi
      $locationData = $this->ipApi->getLocationByIp($ipRequested);

      // Estructurar la respuesta
      $response = [
        'success' => true,
        'data' => $locationData,
        'meta' => [
          'timestamp' => now()->toISOString(),
          'request_id' => uniqid('geo_', true)
        ]
      ];

      TailLogger::saveLog('Respuesta de geolocalización enviada', 'clients/geolocation', 'info', [
        'ip_requested' => $ipRequested,
        'success' => true,
        'request_id' => $response['meta']['request_id']
      ]);
      return response()->json($response, Response::HTTP_OK);
    } catch (\Exception $e) {
      // Log del error
      TailLogger::saveLog('Error en endpoint de geolocalización', 'clients/geolocation', 'error', [
        'ip_requested' => $ipRequested,
        'error_message' => $e->getMessage(),
        'error_trace' => $e->getTraceAsString(),
        'client_ip' => $this->request->ip()
      ]);
      // Respuesta de error estructurada
      return response()->json([
        'success' => false,
        'error' => [
          'code' => 'GEOLOCATION_ERROR',
          'message' => 'Error interno del servidor al procesar la geolocalización',
          'details' => config('app.debug') ? $e->getMessage() : 'Contacte al administrador del sistema'
        ],
        'meta' => [
          'timestamp' => now()->toISOString(),
          'api_version' => '1.0',
          'request_id' => uniqid('geo_error_', true)
        ]
      ], Response::HTTP_INTERNAL_SERVER_ERROR);
    }
  }

  /**
   * Endpoint para verificar el estado de la API
   *
   * @return JsonResponse
   */
  public function status(): JsonResponse
  {
    return response()->json([
      'success' => true,
      'message' => 'Geolocation API is operational',
      'data' => [
        'service' => 'Geolocation API',
        'status' => 'active',
        'version' => '1.0'
      ],
      'meta' => [
        'timestamp' => now()->toISOString(),
        'server_time' => now()->format('Y-m-d H:i:s T')
      ]
    ], Response::HTTP_OK);
  }
}
