<?php

namespace App\Services\TrafficLog;

use Illuminate\Support\Collection;
use Maxidev\Logger\TailLogger;

/**
 * Analizador de fuentes de tráfico sin dependencia de sesiones
 *
 * Determina el medium y source del tráfico basado en:
 * 1. Campañas pagas (cptype en query params)
 * 2. Referrer original (buscadores, redes sociales, otros sitios)
 * 3. Tráfico directo (fallback)
 */
class TrafficSourceAnalyzerService
{
  /**
   * Patrones de buscadores conocidos
   */
  private array $searchEngines = [
    'google',
    'bing',
    'yahoo',
    'duckduckgo',
    'baidu',
    'yandex',
    'ask',
    'aol'
  ];

  /**
   * Patrones de redes sociales conocidas
   */
  private array $socialNetworks = [
    'facebook',
    'instagram',
    'twitter',
    'x',
    'linkedin',
    'youtube',
    'tiktok',
    'pinterest',
    'snapchat',
    'whatsapp',
    'telegram',
    'reddit',
    'tumblr',
  ];

  /**
   * Analiza la fuente de tráfico de forma stateless
   *
   * @param string|null $landingReferrer Referrer original de la landing page
   * @param array $queryParams Parámetros de la URL
   * @param string $landingHost Host de la landing page
   * @return array ['traffic_medium' => string, 'traffic_source' => string]
   */
  public function analyze(?string $landingReferrer, array $queryParams, string $landingHost): array
  {
    TailLogger::saveLog('Starting traffic source analysis', 'traffic-log/source', 'info', [
      'landing_referrer' => $landingReferrer,
      'query_params' => $queryParams,
      'landing_host' => $landingHost
    ]);

    // 1. Verificar campañas pagas (cptype)
    if ($this->hasPaidCampaign($queryParams)) {
      TailLogger::saveLog('Paid campaign detected, analyzing cptype', 'traffic-log/source', 'info', [
        'cptype' => $queryParams['cptype'] ?? null
      ]);
      
      try {
        $result = $this->analyzePaidCampaign($queryParams);
        TailLogger::saveLog('Paid campaign analysis completed successfully', 'traffic-log/source', 'info', [
          'result' => $result
        ]);
        return $result;
      } catch (TrafficSourceException $e) {
        TailLogger::saveLog('Traffic source analysis failed: ' . $e->getMessage(), 'traffic-log/source', 'error', $e->getContext());
        // Continuar con análisis de referrer como fallback
        TailLogger::saveLog('Falling back to referrer analysis after paid campaign failure', 'traffic-log/source', 'warning');
      }
    }

    // 2. Analizar referrer original
    if ($landingReferrer) {
      TailLogger::saveLog('Analyzing referrer traffic', 'traffic-log/source', 'info', [
        'referrer' => $landingReferrer
      ]);
      
      $result = $this->analyzeReferrer($landingReferrer, $landingHost);
      TailLogger::saveLog('Referrer analysis completed', 'traffic-log/source', 'info', [
        'result' => $result
      ]);
      return $result;
    }

    // 3. Fallback a direct
    TailLogger::saveLog('No paid campaign or referrer found, defaulting to direct traffic', 'traffic-log/source', 'info');
    return ['traffic_medium' => 'direct', 'traffic_source' => 'direct'];
  }

  /**
   * Verifica si hay una campaña paga en los parámetros
   * 
   * @param array $queryParams Parámetros de consulta de la URL
   * @return bool True si existe cptype en los parámetros
   */
  private function hasPaidCampaign(array $queryParams): bool
  {
    $hasCptype = !empty($queryParams['cptype']);
    TailLogger::saveLog('Checking for paid campaign presence', 'traffic-log/source', 'debug', [
      'has_cptype' => $hasCptype,
      'cptype_value' => $queryParams['cptype'] ?? null
    ]);
    
    return $hasCptype;
  }

  /**
   * Analiza campaña paga basada en cptype
   * 
   * @param array $queryParams Parámetros de consulta que contienen cptype
   * @return array Información de medium y source para campaña paga
   * @throws TrafficSourceException Si no se encuentra la campaña
   */
  private function analyzePaidCampaign(array $queryParams): array
  {
    $cptype = strtoupper($queryParams['cptype'] ?? '');
    
    TailLogger::saveLog('Analyzing paid campaign', 'traffic-log/source', 'debug', [
      'cptype' => $cptype,
      'original_cptype' => $queryParams['cptype'] ?? null
    ]);
    
    // Buscar en configuración de campañas
    $campaigns = config('campaigns', []);
    TailLogger::saveLog('Loading campaign configuration', 'traffic-log/source', 'debug', [
      'total_campaigns' => count($campaigns)
    ]);
    
    $campaign = collect($campaigns)->firstWhere('cptype', $cptype);
    
    if (is_null($campaign)) {
      TailLogger::saveLog('Campaign not found in configuration', 'traffic-log/source', 'error', [
        'cptype' => $cptype,
        'available_campaigns' => collect($campaigns)->pluck('cptype')->toArray()
      ]);
      
      throw new TrafficSourceException(
        message: 'Campaign not found', 
        context: [
          'cptype' => $cptype,
          'available_campaigns' => collect($campaigns)->pluck('cptype')->toArray()
        ]
      );
    }
    
    $result = [
      'traffic_medium' => 'ads',
      'traffic_source' => $campaign['vendor'] ?? strtolower($cptype),
    ];
    
    TailLogger::saveLog('Paid campaign analysis successful', 'traffic-log/source', 'info', [
      'campaign_found' => $campaign,
      'result' => $result
    ]);
    
    return $result;
  }

  /**
   * Analiza el referrer original para determinar la fuente
   * 
   * @param string $landingReferrer URL del referrer
   * @param string $landingHost Host de la página de destino
   * @return array Información de medium y source basada en referrer
   */
  private function analyzeReferrer(string $landingReferrer, string $landingHost): array
  {
    TailLogger::saveLog('Starting referrer analysis', 'traffic-log/source', 'debug', [
      'referrer' => $landingReferrer,
      'landing_host' => $landingHost
    ]);
    
    $refHost = $this->extractHost($landingReferrer);
    TailLogger::saveLog('Extracted referrer host', 'traffic-log/source', 'debug', [
      'extracted_host' => $refHost
    ]);
    
    // Omitir tráfico interno
    if ($this->isInternalTraffic($refHost, $landingHost)) {
      TailLogger::saveLog('Internal traffic detected, treating as direct', 'traffic-log/source', 'info', [
        'ref_host' => $refHost,
        'landing_host' => $landingHost
      ]);
      return ['traffic_medium' => 'direct', 'traffic_source' => 'direct'];
    }

    // Buscadores
    if ($searchEngine = $this->getSearchEngine($refHost)) {
      TailLogger::saveLog('Search engine traffic detected', 'traffic-log/source', 'info', [
        'search_engine' => $searchEngine,
        'ref_host' => $refHost
      ]);
      return ['traffic_medium' => 'organic', 'traffic_source' => $searchEngine];
    }

    // Redes sociales
    if ($socialPlatform = $this->getSocialNetwork($refHost)) {
      TailLogger::saveLog('Social network traffic detected', 'traffic-log/source', 'info', [
        'social_platform' => $socialPlatform,
        'ref_host' => $refHost
      ]);
      return ['traffic_medium' => 'social', 'traffic_source' => $socialPlatform];
    }

    // Otros referrals
    TailLogger::saveLog('External referral traffic detected', 'traffic-log/source', 'info', [
      'ref_host' => $refHost
    ]);
    return ['traffic_medium' => 'referral', 'traffic_source' => $refHost];
  }

  /**
   * Extrae el host de una URL
   * 
   * @param string $url URL completa
   * @return string Host extraído y normalizado (sin www)
   */
  private function extractHost(string $url): string
  {
    TailLogger::saveLog('Extracting host from URL', 'traffic-log/source', 'debug', [
      'url' => $url
    ]);
    
    $host = parse_url($url, PHP_URL_HOST);

    if (!$host) {
      TailLogger::saveLog('Failed to extract host from URL', 'traffic-log/source', 'warning', [
        'url' => $url
      ]);
      return '';
    }

    // Remover www. si existe
    $normalizedHost = preg_replace('/^www\./', '', strtolower($host));
    
    TailLogger::saveLog('Host extracted and normalized', 'traffic-log/source', 'debug', [
      'original_host' => $host,
      'normalized_host' => $normalizedHost
    ]);
    
    return $normalizedHost;
  }

  /**
   * Verifica si el tráfico es interno (mismo dominio)
   * 
   * @param string $refHost Host del referrer
   * @param string $landingHost Host de la página de destino
   * @return bool True si es tráfico interno
   */
  private function isInternalTraffic(string $refHost, string $landingHost): bool
  {
    if (empty($refHost)) {
      TailLogger::saveLog('Empty referrer host, considering as internal', 'traffic-log/source', 'debug');
      return true;
    }
    
    $currentHost = preg_replace('/^www\./', '', strtolower($landingHost));
    $isInternal = $refHost === $currentHost;
    
    TailLogger::saveLog('Internal traffic check completed', 'traffic-log/source', 'debug', [
      'ref_host' => $refHost,
      'current_host' => $currentHost,
      'is_internal' => $isInternal
    ]);
    
    return $isInternal;
  }

  /**
   * Determina si el host corresponde a un buscador
   * 
   * @param string $refHost Host a verificar
   * @return string|null Nombre del buscador si coincide, null si no
   */
  private function getSearchEngine(string $refHost): ?string
  {
    TailLogger::saveLog('Checking if host matches search engine', 'traffic-log/source', 'debug', [
      'ref_host' => $refHost,
      'search_engines' => $this->searchEngines
    ]);
    
    foreach ($this->searchEngines as $index => $engine) {
      if ($this->hostMatches($refHost, $engine)) {
        TailLogger::saveLog('Search engine match found', 'traffic-log/source', 'debug', [
          'matched_engine' => $engine,
          'ref_host' => $refHost
        ]);
        return $engine;
      }
    }
    
    TailLogger::saveLog('No search engine match found', 'traffic-log/source', 'debug', [
      'ref_host' => $refHost
    ]);
    
    return null;
  }

  /**
   * Determina si el host corresponde a una red social
   * 
   * @param string $host Host a verificar
   * @return string|null Nombre de la red social si coincide, null si no
   */
  private function getSocialNetwork(string $host): ?string
  {
    TailLogger::saveLog('Checking if host matches social network', 'traffic-log/source', 'debug', [
      'host' => $host,
      'social_networks' => $this->socialNetworks
    ]);
    
    foreach ($this->socialNetworks as $index => $network) {
      if ($this->hostMatches($host, $network)) {
        TailLogger::saveLog('Social network match found', 'traffic-log/source', 'debug', [
          'matched_network' => $network,
          'host' => $host
        ]);
        return $network;
      }
    }
    
    TailLogger::saveLog('No social network match found', 'traffic-log/source', 'debug', [
      'host' => $host
    ]);
    
    return null;
  }

  /**
   * Verifica si un host coincide con un patrón
   * 
   * @param string $host Host a verificar
   * @param string $pattern Patrón a buscar
   * @return bool True si hay coincidencia
   */
  private function hostMatches(string $host, string $pattern): bool
  {
    // Coincidencia por posición
    $matches = strpos($host, $pattern) !== false;
    
    TailLogger::saveLog('Host pattern matching', 'traffic-log/source', 'debug', [
      'host' => $host,
      'pattern' => $pattern,
      'matches' => $matches
    ]);
    
    return $matches;
  }
}

/**
 * Excepción personalizada para errores en análisis de fuente de tráfico
 * 
 * Permite pasar contexto adicional (como arrays con información de debug)
 * para facilitar el troubleshooting de problemas en el análisis
 */
class TrafficSourceException extends \Exception
{
  /**
   * Contexto adicional para debugging (array con información relevante)
   * 
   * @var array
   */
  protected $context;

  /**
   * Constructor extendido que acepta contexto adicional
   * 
   * @param string $message Mensaje de error
   * @param int $code Código de error
   * @param array $context Información adicional para debugging
   * @param \Throwable|null $previous Excepción anterior en la cadena
   */
  public function __construct($message = '', $code = 0, $context = [], ?\Throwable $previous = null)
  {
    $this->context = $context;
    parent::__construct($message, $code, $previous);
  }

  /**
   * Obtiene el contexto adicional de la excepción
   * 
   * @return array Información de contexto para debugging
   */
  public function getContext(): array
  {
    return $this->context;
  }
}
