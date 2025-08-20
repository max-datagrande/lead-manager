<?php

namespace App\Libraries;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Maxidev\Logger\TailLogger;

class IpApi
{
  private string $baseUrl;
  private string $apiToken;
  private static ?string $ip = null;

  public function __construct()
  {
    $this->baseUrl = config('services.ipapi.base_url', 'https://ipapi.co');
    $this->apiToken = config('services.ipapi.token');
    if (empty($this->apiToken)) {
      throw new \Exception('IpApi token is not configured in services config');
    }
  }

  /**
   * Get geolocation data by IP address
   *
   * @param string|null $ip IP address to lookup (if null, uses current request IP)
   * @return array Parsed geolocation data
   */
  public function getLocationByIp(?string $ip = null): array
  {
    try {
      // If no IP provided, get current request IP
      self::$ip = $ip;
      // Build the API URL
      $url = $this->buildApiUrl($ip);

      // Make the HTTP request
      $response = Http::timeout(10)->get($url, ['key' => $this->apiToken]);
      if (!$response->successful()) {
        TailLogger::saveLog('IpApi request failed', "api/geolocation/", 'warning', [
          'status' => $response->status(),
          'ip' => $ip,
          'url' => $url
        ]);
        return $this->getDefaultLocationData($ip);
      }
      $data = $response->json();
      // Check if API returned an error
      if (!empty($data['error'])) {
        TailLogger::saveLog('IpApi returned error', "api/geolocation/", 'warning', [
          'status' => $response->status(),
          'ip' => $ip,
          'url' => $url,
          'error' => $data['reason'],
          'message' => $data['message'] ?? 'Unknown error',
          'details' => $data,
        ]);
        return $this->getDefaultLocationData($ip);
      }
      return self::parseResponse($data);
    } catch (\Exception $e) {
      TailLogger::saveLog('IpApi exception', "api/geolocation/", 'error', [
        'message' => $e->getMessage(),
        'ip' => $ip ?? 'unknown'
      ]);
      return $this->getDefaultLocationData($ip);
    }
  }

  /**
   * Build the API URL with token
   *
   * @param string $ip
   * @return string
   */
  private function buildApiUrl(string $ip): string
  {
    $endpoint = "/{$ip}/json/";
    return $this->baseUrl . $endpoint;
  }

  /**
   * Get default location data when API fails
   *
   * @param string|null $ip
   * @return array
   */
  private function getDefaultLocationData(?string $ip): array
  {
    return [
      'ip' => $ip,
      'city' => 'Mountain View',
      'region' => 'California',
      'region_code' => 'CA',
      'country' => 'US',
      'postal' => '33323',
      'timezone' => 'America/Los_Angeles',
      'latitude' => 37.4419,
      'longitude' => -122.1430,
      'isp' => null,
      'asn' => null,
      'threat' => null,
      'currency' => 'USD',
      'currency_name' => 'US Dollar'
    ];
  }

  /**
   * Parse API response to standardized format
   *
   * @param array $data
   * @return array
   */
  private static function parseResponse(array $data): array
  {
    $hasPostalCode = !empty($data['postal']);
    if (!$hasPostalCode) {
      // Return default location data
      $data['postal'] = '33323';
      $data['city'] = 'Mountain View';
      $data['region'] = 'California';
      $data['country'] = 'US';
    }

    return [
      'ip' => $data['ip'] ?? self::$ip,
      'city' => $data['city'] ?? null,
      'region' => $data['region'] ?? null,
      'region_code' => $data['region_code'] ?? null,
      'country' => $data['country_code'] ?? null,
      'postal' => $data['postal'] ?? "",
      'timezone' => $data['timezone'] ?? null,
      'latitude' => $data['latitude'] ?? null,
      'longitude' => $data['longitude'] ?? null,
      'isp' => $data['org'] ?? null,
      'asn' => $data['asn'] ?? null,
      'threat' => $data['threat'] ?? null,
      'currency' => $data['currency'] ?? null,
      'currency_name' => $data['currency_name'] ?? null
    ];
  }
}
