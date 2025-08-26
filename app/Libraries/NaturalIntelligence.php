<?php

namespace App\Libraries;

use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\Response;
use Maxidev\Logger\TailLogger;
use Throwable;

final class NaturalIntelligence
{
  private const CACHE_TOKEN_KEY = 'ni_auth_token';
  private const CACHE_TTL = 23 * 60 * 60; // 23h
  private const REPORT_FORMAT = 'json';
  private const REPORT_TYPE = 'Summary';
  private const DATA_TYPE = 'payout';
  private const UNAUTHORIZED_RESPONSE = [
    'STATUS' => 403,
    'MESSAGE' => 'Unauthorized'
  ];

  public readonly string $reportUrl;
  public readonly string $loginUrl;
  public readonly string $username;
  public readonly string $password;

  // Propiedades para almacenar la última respuesta HTTP y payload
  private ?\Illuminate\Http\Client\Response $lastResponse = null;
  private ?array $lastPayload = null;

  private array $relevantFields = [
    'data_type',
    'device',
    'pub_param_1',
    'pub_param_2',
    'external_campaign_id',
    'external_traffic_source',
    'clickouts',
    'leads',
    'payout',
    'sales',
    'visits',
    'date_time',
  ];

  public function __construct()
  {
    $cfg = config('services.natural_intelligence');
    $this->loginUrl = (string) $cfg['login_url'];
    $this->reportUrl = (string) $cfg['report_url'];
    $this->username = (string) $cfg['username'];
    $this->password = (string) $cfg['password'];
  }
  private function requestAuthToken(): Response
  {
    $payload = [
      'username' => $this->username,
      'password' => $this->password,
    ];
    $this->log('Requesting authentication token', 'info', ['payload' => $payload]);
    return Http::timeout(30)
      ->retry(2, 400, throw: false) // Mantener throw en retry para errores de conexión
      ->asJson()
      ->post($this->loginUrl, $payload);
  }

  private function deleteToken(): void
  {
    Cache::forget(self::CACHE_TOKEN_KEY);
  }

  /**
   * Renueva el token de autenticación de forma thread-safe para múltiples jobs concurrentes.
   *
   * Esta función implementa un patrón de lock distribuido para evitar que múltiples jobs
   * hagan login simultáneamente. Solo un proceso puede obtener el lock y hacer la petición
   * HTTP de login, mientras que los demás esperan y reutilizan el token generado.
   *
   * Flujo de ejecución:
   * 1. Intenta obtener un lock distribuido con timeout de 10 segundos
   * 2. Si no puede obtener el lock inmediatamente, espera hasta 10 segundos (block)
   * 3. Una vez obtenido el lock, verifica si otro proceso ya renovó el token
   * 4. Si el token ya existe, termina sin hacer petición HTTP (optimización)
   * 5. Si no existe, hace la petición de login y guarda el token en cache compartido
   * 6. El token se guarda con TTL de 23 horas para ser reutilizado por todos los jobs
   *
   * @throws NaturalIntelligenceException Si falla la autenticación o el token está vacío
   */
  private function refreshToken(): void
  {
    if (Cache::has(self::CACHE_TOKEN_KEY)) {
      return; // Token existe, salir inmediatamente sin bloquear
    }

    // Solo crear lock si NO hay token
    $lock = Cache::lock('ni_auth_token_lock', 10);
    try {
      // Intentar obtener el lock, si no está disponible, esperar hasta 10 segundos
      if (!$lock->get()) {
        $lock->block(10);
      }

      // Double-check: verificar nuevamente por si otro proceso ya lo creó
      if (Cache::has(self::CACHE_TOKEN_KEY)) {
        return; // Otro job ya renovó el token mientras esperábamos
      }

      // Solo llegar aquí si realmente necesitamos hacer login
      $this->log('Solicitando nuevo token de autenticación');

      // Hacer petición HTTP de login con reintentos automáticos
      $resp = $this->requestAuthToken();
      $status = $resp->status();

      // Validación manual con logging personalizado
      if (!$resp->successful()) {
        $body = $resp->body();
        $this->log("Login failed - Status: {$status}, Body: {$body}");
        throw new NaturalIntelligenceException("Login failed with status {$status}: {$body}");
      }

      // Procesar respuesta exitosa
      $this->log('Login response status : ' . $status);
      // Extraer token del cuerpo de la respuesta usando json_decode
      $tokenResponse = trim((string) $resp->body());
      $token = trim($tokenResponse, '"'); // Remover comillas del inicio y final

      if ($token === '') {
        throw new NaturalIntelligenceException('Empty token from login');
      }
      // Guardar token en cache compartido con TTL de 23 horas
      Cache::put(self::CACHE_TOKEN_KEY, $token, self::CACHE_TTL);
    } catch (Throwable $e) {
      throw new NaturalIntelligenceException('Failed to obtain auth token - ' . $e->getMessage(), 0, $e);
    } finally {
      // Liberar el lock siempre, incluso si ocurre una excepción
      optional($lock)->release();
    }
  }

  public function login(): void
  {
    $this->refreshToken();
  }

  /**
   * Obtiene la última respuesta HTTP realizada
   */
  public function getLastResponse(): ?\Illuminate\Http\Client\Response
  {
    return $this->lastResponse;
  }
  /**
   * Obtiene el último payload enviado
   */
  public function getLastPayload(): ?array
  {
    return $this->lastPayload;
  }
  public function buildPayload(?string $fromDate = null, ?string $toDate = null)
  {
    return [
      'FromDate'     => $fromDate ?? now()->subDays(3)->format('Y-m-d'),
      'ToDate'       => $toDate   ?? now()->format('Y-m-d'),
      'ReportFormat' => self::REPORT_FORMAT,
      'ReportType'   => self::REPORT_TYPE,
      'DataType'     => self::DATA_TYPE,
    ];
  }

  public function getReport(?array $payload = null): array
  {
    if (!$payload) {
      $payload = $this->buildPayload();
    }

    // Almacenar el payload para acceso posterior
    $this->lastPayload = $payload;
    $this->log('Solicitando reporte', 'info', ['request_data' => $payload]);
    try {
      $headers = $this->authHeader();
      $this->log('Requesting report', 'info', ['headers' => $headers]);
      $response = Http::timeout(60)
        ->retry(times: 3, sleepMilliseconds: 250, throw: false)
        ->withHeaders($headers)
        ->acceptJson()
        ->post($this->reportUrl, $payload)
        ->throwIf(function ($response) {
          $data = rescue(fn() => $response->json(), [], report: false);
          $message = $data['message'] ?? 'No message provided';
          return $response->status() === self::UNAUTHORIZED_RESPONSE['STATUS'] || $message === self::UNAUTHORIZED_RESPONSE['MESSAGE'];
        });
      // Almacenar la respuesta para acceso posterior
      $statusCode = $response->status();
      //Success case
      if ($response->successful()) {
        return $this->handleReportResponse($response);
      }
      //Error case
      $this->log('HTTP error obtaining report', 'error', [
        'status' => $statusCode,
        'body' => $response->body(),
        'request_data' => $payload,
        'url' => $this->reportUrl,
      ]);
      $data = $response->json() ?? [];
      $message = $data['message'] ?? 'No message provided';
      throw new NaturalIntelligenceException($message, $statusCode);
    } catch (RequestException $e) {
      // Almacenar la respuesta de error si existe
      if ($e->response) {
        $this->lastResponse = $e->response;
      }
      $this->log('Retrying report after authentication-related error', 'warning', [
        'status' => optional($e->response)->status(),
        'url' => $this->reportUrl,
        'message' => $e->getMessage(),
      ]);
      //Token invalid
      $this->deleteToken();
      $this->refreshToken();
      return $this->retryReportOnce($payload);
    } catch (Throwable $e) {
      if ($e instanceof NaturalIntelligenceException) {
        throw $e;
      }
      $this->log('Unexpected error obtaining report', 'error', [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString(),
      ]);
      throw new NaturalIntelligenceException('Unexpected error obtaining report: ' . $e->getMessage(), 0, $e);
    }
  }

  private function retryReportOnce(array $payload): array
  {
    $response = Http::timeout(60)
      ->retry(2, 250, throw: false)
      ->withHeaders($this->authHeader())
      ->acceptJson()
      ->post($this->reportUrl, $payload);

    // Almacenar la respuesta del reintento
    $this->lastResponse = $response;

    if (!$response->successful()) {
      throw new NaturalIntelligenceException('HTTP error on retry: ' . $response->body(), $response->status());
    }

    return $this->handleReportResponse($response);
  }

  private function authHeader(): array
  {
    $token = Cache::get(self::CACHE_TOKEN_KEY);
    if (!$token) {
      $this->refreshToken();
      $token = Cache::get(self::CACHE_TOKEN_KEY);
    }
    return ['Authorization' => (string) $token];
  }

  private function handleReportResponse(Response $response): array
  {

    // Si llegas aquí, ya es 2xx por ->throw()
    $this->lastResponse = $response;
    $json = $response->json();
    if (!is_array($json)) {
      $this->log('Invalid JSON in report response', 'error', [
        'status' => $response->status(),
        'body' => $response->body(),
      ]);
      throw new NaturalIntelligenceException('Invalid JSON in report response');
    }
    $filtered = $this->filterResponse($json);
    $this->log('Report obtained successfully', 'info', [
      'itemsOriginal' => count($json) ?? 0,
      'itemsFiltered' => count($filtered) ?? 0,
    ]);
    return $filtered;
  }

  /**
   * Soporta objetos y listas de objetos.
   * @param array<mixed> $data
   * @return array<mixed>
   */
  public function filterResponse(?array $data): ?array
  {
    // Si es un array de arrays (como las conversiones), filtrar cada elemento
    return collect($data)->map(function ($item) {
      // Si el item es un array asociativo, filtrar sus campos
      if (is_array($item)) {
        return collect($item)->filter(function ($value, $key) {
          return in_array($key, $this->relevantFields);
        })->toArray();
      }
      // Si no es un array, devolver tal como está
      return $item;
    })->toArray();
  }

  private function log(string $message, string $level = 'info', array $context = []): void
  {
    TailLogger::saveLog("NI: {$message}", 'api/ni', $level, $context);
  }
}

class NaturalIntelligenceException extends \RuntimeException {}
