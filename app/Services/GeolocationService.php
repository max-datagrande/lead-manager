<?php

namespace App\Services;

use Illuminate\Http\Request;
use Maxidev\Logger\TailLogger;
use Illuminate\Support\Facades\Cache;
use App\Libraries\IpApi;
use Illuminate\Support\Facades\Http;

/**
 * Servicio de geolocalización con cache por IP
 *
 * Proporciona datos de geolocalización basados en IP con cache inteligente
 * para optimizar llamadas a APIs externas y mejorar performance
 */
class GeolocationService
{
  protected array $defaultGeolocation = [
    'hostname' => 'ns1.google.com',
    'city' => 'Mountain View',
    'region' => 'California',
    'country' => 'US',
    'country_code' => 'US',
    'loc' => '38.0088,-122.1175',
    'org' => 'AS15169 Google LLC',
    'postal' => '94043',
    'timezone' => 'America/Los_Angeles',
    'readme' => 'https://ipinfo.io/missingauth',
    'anycast' => true,
  ];

  // Cache de la IP para evitar múltiples llamadas en el mismo request
  private ?string $cachedIpAddress = null;

  // TTL del cache en minutos (24 horas)
  private int $cacheMinutes = 1440;

  public function __construct(protected Request $request, protected IpApi $ipApi) {}
  /**
   * Obtiene la IP del request actual con cache interno
   * Como este servicio es singleton, la IP se calcula una sola vez por request
   */
  public function getIpAddress(): string
  {
    // Si ya tenemos la IP cacheada, la devolvemos
    if ($this->cachedIpAddress !== null) {
      return $this->cachedIpAddress;
    }

    // IPs de prueba para diferentes ubicaciones
    $testIps = [
      'new_york' => "216.131.83.235",
      'miami' => "216.131.87.237",
      'chicago' => "216.131.77.230",
      'california' => "216.239.32.10",
      'colombia' => "190.60.47.250"
    ];

    // Cachear la IP para futuras llamadas en el mismo request
    $this->cachedIpAddress = app()->environment('production')
      ? $this->request->ip()
      : $testIps['new_york'];

    return $this->cachedIpAddress;
  }

  /**
   * Obtiene datos de geolocalización con cache por IP
   *
   * @return array Datos de geolocalización
   */
  public function getGeolocation(?string $ip = null): array
  {
    if ($ip === null) {
      $ip = $this->getIpAddress();
    }

    // Generar clave de cache única por IP
    $cacheKey = "geolocation:ip:{$ip}";

    // Intentar obtener del cache primero
    $cachedData = Cache::get($cacheKey);
    if ($cachedData !== null) {
      $this->saveGeolocationServiceLog("Cache hit for IP: {$ip}");
      return $cachedData;
    }

    // Si no está en cache, consultar API externa
    try {
      $response = $this->ipApi->getLocationByIp($ip);

      // Guardar en cache por 24 horas
      Cache::put($cacheKey, $response, now()->addMinutes($this->cacheMinutes));

      $this->saveGeolocationServiceLog("API call successful and cached for IP: {$ip}");
      return $response;
    } catch (\Exception $e) {
      $this->saveGeolocationServiceLog("API call failed for IP: {$ip}. Error: " . $e->getMessage());

      // En caso de error, devolver datos por defecto
      return $this->defaultGeolocation;
    }
  }

  /**
   * Captura los datos de geolocalización y los guarda en el request
   *
   * Este método obtiene los datos de geolocalización y los almacena como
   * atributo del request para que puedan ser accedidos posteriormente
   * por otros componentes sin necesidad de volver a consultar el servicio.
   *
   * @return array Datos de geolocalización capturados
   */
  public function captureGeolocation(): array
  {
    $data = $this->getGeolocation();
    $this->request->attributes->set('geolocation', $data);
    $this->saveGeolocationServiceLog("Geolocation data captured and stored in request attributes");

    return $data;
  }

  /**
   * Obtiene los datos de geolocalización previamente capturados del request
   *
   * @return array|null Datos de geolocalización si fueron capturados previamente, null en caso contrario
   */
  public function getCapturedGeolocation(): ?array
  {
    return $this->request->attributes->get('geolocation');
  }

  /**
   * Limpia el cache de geolocalización para una IP específica
   *
   * @param string|null $ip IP a limpiar del cache. Si es null, usa la IP actual
   * @return bool True si se limpió correctamente
   */
  public function clearGeolocationCache(?string $ip = null): bool
  {
    $targetIp = $ip ?? $this->getIpAddress();
    $cacheKey = "geolocation:ip:{$targetIp}";

    $result = Cache::forget($cacheKey);

    if ($result) {
      $this->saveGeolocationServiceLog("Cache cleared for IP: {$targetIp}");
    }

    return $result;
  }

  /**
   * Guarda log del servicio de geolocalización
   */
  private function saveGeolocationServiceLog(string $message): void
  {
    TailLogger::saveLog($message, "api/geolocation/", 'info');
  }

  /**
   * Fetches city and state from a given zipcode using an external API, with caching.
   *
   * @param string $zipcode
   * @return array|null An array with 'city' and 'state' keys, or null on failure.
   */
  public function getCityAndStateFromZipcode(string $zipcode): ?array
  {
    if (empty($zipcode)) {
      return null;
    }

    $cacheKey = "geolocation:zip:{$zipcode}";

    // Try to get from cache first
    return Cache::remember($cacheKey, now()->addDays(30), function () use ($zipcode) {
      try {
        $response = Http::get("https://api.zippopotam.us/us/{$zipcode}");

        if ($response->failed() || !isset($response->json()['places'][0])) {
          $this->saveGeolocationServiceLog("Zippopotam API call failed for zipcode: {$zipcode}");
          return null;
        }

        $place = $response->json()['places'][0];

        return [
          'city' => $place['place name'],
          'state' => $place['state abbreviation'],
        ];
      } catch (\Exception $e) {
        $this->saveGeolocationServiceLog("Exception in getCityAndStateFromZipcode for zipcode: {$zipcode}. Error: " . $e->getMessage());
        return null;
      }
    });
  }
}
