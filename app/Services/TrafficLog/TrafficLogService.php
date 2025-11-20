<?php

namespace App\Services\TrafficLog;

use App\Models\TrafficLog;
use App\Services\BotDetectorService;
use App\Services\GeolocationService;
use App\Services\UtmService;
use Illuminate\Http\Request;
use Maxidev\Logger\TailLogger;
use Illuminate\Support\Str;

/**
 * Exception personalizada para errores en creación de traffic logs
 */
class TrafficLogCreationException extends \Exception {}

/**
 * Servicio principal para manejo de traffic logs
 *
 * Coordina todos los servicios especializados para crear registros de tráfico
 * completos con detección de bots, geolocalización, análisis de fuentes y fingerprinting
 */
class TrafficLogService
{
  private ?TrafficLog $currentVisitor = null;

  public function __construct(
    private TrafficSourceAnalyzerService $trafficSourceAnalyzer,
    private DeviceDetectionService $deviceDetectionService,
    private FingerprintGeneratorService $fingerprintGenerator,
    private BotDetectorService $botDetectorService,
    private GeolocationService $geolocationService,
    private UtmService $utmService,
    protected Request $request,
  ) {}

  /**
   * Crea un nuevo traffic log con todos los datos procesados
   *
   * @param array $data Datos validados del request
   * @return TrafficLog
   * @throws TrafficLogCreationException
   */
  public function createTrafficLog(array $data): TrafficLog
  {
    try {

      // Generar fingerprint único
      $userAgent = $data['user_agent'];
      $ip = $this->request->geoService()->getIpAddress();
      $landingOrigin = $this->getOrigin();
      $landingHost = parse_url($landingOrigin, PHP_URL_HOST) ?? "";
      $fingerprint = $this->fingerprintGenerator->generate($userAgent, $ip, $landingHost);
      TailLogger::saveLog('Iniciando creación de traffic log', 'traffic-log/store', 'info', ['fingerprint' => $fingerprint]);

      $existingTraffic = $this->getExistingTraffic($fingerprint);
      if ($existingTraffic) {
        TailLogger::saveLog("Traffic log duplicado actualizado para $fingerprint, nuevo visit_count: {$existingTraffic->visit_count}", 'traffic-log/store', 'info', [
          'fingerprint' => $fingerprint,
          'visit_count' => $existingTraffic->visit_count,
          'id' => $existingTraffic->id
        ]);
        $this->currentVisitor = $existingTraffic;
        return $this->incrementVisitCount($existingTraffic);
      }

      //Creating new visitor
      $newTraffic = new TrafficLog();
      $newTraffic->id = (string) Str::uuid(); //Id unico generado
      $newTraffic->fingerprint = $fingerprint;
      $newTraffic->user_agent = $userAgent;
      $newTraffic->ip_address = $ip;
      $newTraffic->visit_date = date('Y-m-d');
      $newTraffic->visit_count = 1;
      $newTraffic->is_bot = $data['is_bot'] ?? false;
      // $newTraffic->is_bot = $data['is_bot'] ?? $this->botDetectorService->detectBot($userAgent);

      //Device detection
      $deviceInfo = $this->deviceDetectionService->detectDevice($userAgent);
      $newTraffic->device_type = $deviceInfo['deviceType'];
      $newTraffic->browser = $deviceInfo['browser'];
      $newTraffic->os = $deviceInfo['os'];
      //Referer
      $referer = $this->getReferer($data);
      $newTraffic->referrer = $referer; //INFO: Este es el origen del trafico que aterrizo nuestra landing page
      //Host origin
      $newTraffic->host = $landingHost;
      //Page visited
      $newTraffic->path_visited = $data['current_page'];
      //Query Params on page
      $queryParams = $data['query_params'] ?? null;
      $newTraffic->query_params = $queryParams;
      // S1-S4
      $newTraffic->s1 = $data['s1'] ?? $queryParams['s1'] ?? null;
      $newTraffic->s2 = $data['s2'] ?? $queryParams['s2'] ?? null;
      $newTraffic->s3 = $data['s3'] ?? $queryParams['s3'] ?? null;
      $newTraffic->s4 = $data['s4'] ?? $queryParams['s4'] ?? null;

      //Campaign Code
      $newTraffic->campaign_code = $queryParams['cptype'] ?? null;

      // Analizar fuente de tráfico usando UtmService para extracción integral
      $trafficData = $this->utmService->analyzeTrafficData(referrer: $referer, queryParams: $queryParams);

      // Asignar datos UTM y de tráfico de manera limpia
      // Los valores pueden ser null si no están presentes en los parámetros UTM
      $newTraffic->utm_source = $trafficData['utm_source'];
      $newTraffic->utm_medium = $trafficData['utm_medium']; // Canal de marketing (cpc, email, etc.)
      $newTraffic->utm_campaign_id = $trafficData['utm_campaign_id'];
      $newTraffic->utm_campaign_name = $trafficData['utm_campaign_name'];
      $newTraffic->utm_term = $trafficData['utm_term'];
      $newTraffic->utm_content = $trafficData['utm_content'];
      $newTraffic->click_id = $trafficData['click_id'];
      $newTraffic->platform = $trafficData['platform'];
      $newTraffic->channel = $trafficData['channel'];

      // Obtener geolocalización
      $geolocation = $this->geolocationService->getGeolocation();
      $newTraffic->country_code = $geolocation['country'] ?? null;
      $newTraffic->state = $geolocation['region'] ?? null;
      $newTraffic->city = $geolocation['city'] ?? null;
      $newTraffic->postal_code = $geolocation['postal'] ?? null;

      // Crear el registro en la base de datos
      $newTraffic->save();
      TailLogger::saveLog('Traffic log creado exitosamente', 'traffic-log/store', 'info', [
        'id' => $newTraffic->id,
        'fingerprint' => $newTraffic->fingerprint,
        'utm_source' => $newTraffic->utm_source,
        'is_bot' => $newTraffic->is_bot,
      ]);
      $this->currentVisitor = $newTraffic;

      return $newTraffic;
    } catch (\Exception $e) {
      TailLogger::saveLog('Error inesperado en creación de traffic log: ' . $e->getMessage(), 'traffic-log/store', 'error', [
        'data' => $data,
        'error' => $e->getTraceAsString(),
      ]);
      throw new TrafficLogCreationException('Failed to create traffic log', 0, $e);
    }
  }

  private function getOrigin(): string
  {
    $isDev = app()->environment('local');
    $originParam = $isDev ? 'dev-origin' : 'origin';
    return $this->request->header($originParam) ?? '';
  }
  private function getReferer($data): ?string
  {
    $origin = $this->getOrigin();
    $originHost = parse_url($origin, PHP_URL_HOST) ?? "";
    $referer = $data['referer'] ?? null;
    if ($referer && strpos($referer, $originHost) !== false) {
      $referer = null;
    }
    return $referer;
  }
  private function incrementVisitCount(TrafficLog $trafficLog): TrafficLog
  {
    // Incrementar visit_count para tráfico duplicado
    $trafficLog->increment('visit_count');
    return $trafficLog;
  }

  /**
   * Obtiene el tráfico existente si existe duplicado reciente
   *
   * @param string $fingerprint
   * @return TrafficLog|null
   */
  private function getExistingTraffic(string $fingerprint): ?TrafficLog
  {
    try {
      return TrafficLog::where('fingerprint', $fingerprint)
        ->first();
    } catch (\Exception $e) {
      TailLogger::saveLog("Error verificando tráfico duplicado de: $fingerprint" . $e->getMessage(), 'traffic-log/store', 'warning');
      return null;
    }
  }

  /**
   * Obtiene el tráfico actual del usuario
   *
   * @return TrafficLog|null
   */
  public function getCurrentVisitor(): ?TrafficLog
  {
    return $this->currentVisitor;
  }
}
