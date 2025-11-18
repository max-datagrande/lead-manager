<?php

namespace App\Services;

use App\Models\TrafficLog;

class UtmService
{
  protected $adPlatforms = [
    'gclid' => 'Google Ads',
    'dclid' => 'Google Display & Video',
    'gbraid' => 'Google Ads (iOS)',
    'wbraid' => 'Google Ads (iOS)',
    'gclsrc' => 'Google Ads Source',
    'msclkid' => 'Microsoft Ads (Bing)',
    'fbclid' => 'Meta Ads (Facebook/Instagram)',
    'ttclid' => 'TikTok Ads',
    'li_fat_id' => 'LinkedIn Ads',
    'scid' => 'Snapchat Ads',
    'epik' => 'Pinterest Ads',
    'twclid' => 'Twitter Ads',
    'rdt_cid' => 'Reddit Ads',
    'yclid' => 'Yandex Ads',
    'sccid' => 'Snapchat Ads',
    'ymcid' => 'Yandex Ads',
    'yqrid' => 'Yandex Ads',
    'yzcrid' => 'Yandex Ads',
    'sznclid' => 'Seznam/Sklik',
    'zanpid' => 'Awin',
    'vmcid' => 'Yahoo Ads',
  ];
  /**
   * Extrae el click ID de los parámetros de URL basándose en plataformas publicitarias conocidas
   *
   * @param array $urlParams Parámetros de la URL
   * @return string|null
   */
  public function getClickData($urlParams)
  {
    $clickId = null;
    $platform = null;
    // Buscar el primer parámetro de ads presente
    foreach ($this->adPlatforms as $param => $platform) {
      if (isset($urlParams[$param]) && !empty($urlParams[$param])) {
        $clickId = $urlParams[$param];
        $platform = $this->adPlatforms[$param];
        break;
      }
    }

    return ['click_id' => $clickId, 'platform' => $platform];
  }

  /**
   * Determina la fuente de tráfico basándose en parámetros UTM y referrer
   *
   * @param array $urlParams Parámetros de la URL
   * @param string|null $referrer URL de referencia
   * @return array
   */
  public function getSourceData($urlParams, $referrer = null)
  {
    $source = null;
    $platform = null;
    $channel = 'direct';
    $utmSource = $urlParams['utm_source'] ?? null;

    // Verificar parámetros UTM primero
    if (!empty($utmSource)) {
      $source = $utmSource;
      $channel = 'organic';
    } elseif (!empty($referrer)) {
      $source = $this->extractSourceFromReferrer($referrer);
      $channel = 'organic';
    }

    // Verificar si es tráfico pagado
    $clickData = $this->getClickData($urlParams);
    $clickId = $clickData['click_id'];
    if ($clickId) {
      $platform = $clickData['platform'];
      $channel = 'ads';
    }

    return [
      'source' => $source,
      'platform' => $platform, // Ahora solo contiene la plataforma detectada
      'channel' => $channel,
      'click_id' => $clickId
    ];
  }

  /**
   * Extrae la fuente de tráfico del referrer
   *
   * @param string $referrer
   * @return string
   */
  private function extractSourceFromReferrer($referrer)
  {
    $referrerMap = [
      'facebook.com' => 'facebook',
      'google.com' => 'google',
      'bing.com' => 'bing',
      'aol.com' => 'aol',
      'instagram.com' => 'instagram',
      'linkedin.com' => 'linkedin',
      'chatgpt.com' => 'chatgpt',
    ];

    foreach ($referrerMap as $domain => $source) {
      if (strpos($referrer, $domain) !== false) {
        return $source;
      }
    }

    return 'other (' . $referrer . ')';
  }

  /**
   * Busca registros de tráfico por fingerprint
   *
   * @param string $fingerprint
   * @return \Illuminate\Database\Eloquent\Collection
   */
  public function getTrafficLogsByFingerprint($fingerprint)
  {
    return TrafficLog::where('fingerprint', $fingerprint)
      ->orderBy('created_at', 'desc')
      ->get();
  }

  /**
   * Busca registros de tráfico por click ID
   *
   * @param string $clickId
   * @return \Illuminate\Database\Eloquent\Collection
   */
  public function getTrafficLogsByClickId($clickId)
  {
    return TrafficLog::where('click_id', $clickId)
      ->orderBy('created_at', 'desc')
      ->get();
  }

  /**
   * Busca registros de tráfico por parámetros de campaña
   *
   * @param array $params
   * @return \Illuminate\Database\Eloquent\Builder
   */
  public function getTrafficLogsByCampaign($params)
  {
    $query = TrafficLog::query();

    if (isset($params['campaign_id']) && !empty($params['campaign_id'])) {
      $query->where('campaign_id', $params['campaign_id']);
    }

    if (isset($params['campaign_code']) && !empty($params['campaign_code'])) {
      $query->where('campaign_code', $params['campaign_code']);
    }

    if (isset($params['utm_campaign']) && !empty($params['utm_campaign'])) {
      $query->whereJsonContains('query_params->utm_campaign', $params['utm_campaign']);
    }

    return $query->orderBy('created_at', 'desc');
  }

  /**
   * Obtiene estadísticas de tráfico por fuente
   *
   * @param array $filters
   * @return array
   */
  public function getTrafficStats($filters = [])
  {
    $query = TrafficLog::query();

    // Aplicar filtros de fecha si existen
    if (isset($filters['from_date'])) {
      $query->whereDate('created_at', '>=', $filters['from_date']);
    }

    if (isset($filters['to_date'])) {
      $query->whereDate('created_at', '<=', $filters['to_date']);
    }

    // Estadísticas por fuente de tráfico
    $bySource = $query->selectRaw('utm_source, COUNT(*) as total, COUNT(DISTINCT fingerprint) as unique_visitors')
      ->groupBy('utm_source')
      ->get();

    // Estadísticas por medium
    $byMedium = $query->selectRaw('traffic_medium, COUNT(*) as total, COUNT(DISTINCT fingerprint) as unique_visitors')
      ->whereNotNull('traffic_medium')
      ->where('traffic_medium', '!=', '')
      ->groupBy('traffic_medium')
      ->get();

    // Estadísticas por país
    $byCountry = $query->selectRaw('country_code, COUNT(*) as total, COUNT(DISTINCT fingerprint) as unique_visitors')
      ->whereNotNull('country_code')
      ->groupBy('country_code')
      ->orderByDesc('total')
      ->limit(10)
      ->get();

    return [
      'by_source' => $bySource,
      'by_medium' => $byMedium,
      'by_country' => $byCountry,
      'total_visits' => $query->count(),
      'unique_visitors' => $query->distinct('fingerprint')->count(),
    ];
  }

  /**
   * Procesa y normaliza los parámetros UTM de una URL
   *
   * @param array $urlParams
   * @return array
   */
  public function processUtmParams($urlParams)
  {
    $utmParams = [
      'utm_source' => $urlParams['utm_source'] ?? null,
      'utm_medium' => $urlParams['utm_medium'] ?? null,
      'utm_campaign_id' => $urlParams['campaign_id'] ?? $urlParams['utm_id'] ?? $urlParams['cid'] ?? null,
      'utm_campaign_name' => $urlParams['utm_campaign'] ?? null,
      'utm_term' => $urlParams['utm_term'] ?? null,
      'utm_content' => $urlParams['utm_content'] ?? null,
    ];

    // Limpiar valores vacíos
    return array_filter($utmParams, function ($value) {
      return !empty($value);
    });
  }

  /**
   * Obtiene el último registro de tráfico para un fingerprint específico
   *
   * @param string $fingerprint
   * @return TrafficLog|null
   */
  public function getLatestTrafficLog($fingerprint)
  {
    return TrafficLog::where('fingerprint', $fingerprint)
      ->latest('created_at')
      ->first();
  }

  /**
   * Analiza y extrae toda la información de tráfico de manera integral
   *
   * @param string|null $referrer URL de referencia
   * @param array $queryParams Parámetros de la URL
   * @return array Información completa de tráfico y UTM
   */
  public function analyzeTrafficData($referrer = null, $queryParams = [])
  {
    // Procesar parámetros UTM
    $queryParams = array_change_key_case($queryParams, CASE_LOWER);
    $utmParams = $this->processUtmParams($queryParams);

    // Obtener información de fuente de tráfico
    $sourceData = $this->getSourceData($queryParams, $referrer);

    return [
      // Columnas UTM estándar
      'utm_source' => $utmParams['utm_source'] ?? $sourceData['source'],
      'utm_medium' => $utmParams['utm_medium'] ?? null,
      'utm_campaign_id' => $utmParams['utm_campaign_id'] ?? null,
      'utm_campaign_name' => $utmParams['utm_campaign_name'] ?? null,
      'utm_term' => $utmParams['utm_term'] ?? null,
      'utm_content' => $utmParams['utm_content'] ?? null,

      // Información adicional de tráfico
      'platform' => $sourceData['platform'] ?? null,
      'channel' => $sourceData['channel'] ?? null,
      'click_id' => $sourceData['click_id'] ?? null,
      'utm_params' => $utmParams,

      // Información de análisis
      'analysis' => [
        'has_utm_params' => !empty($utmParams),
        'has_click_id' => !empty($sourceData['click_id']),
        'is_paid_traffic' => !empty($sourceData['click_id']),
        'traffic_type' => $sourceData['channel']
      ]
    ];
  }
}
