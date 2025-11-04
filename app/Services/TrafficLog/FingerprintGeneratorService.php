<?php

namespace App\Services\TrafficLog;

use Carbon\Carbon;

/**
 * Generador de huellas digitales para traffic logs
 *
 * Crea identificadores únicos basados en user agent, IP y host de origen
 * para detectar tráfico duplicado y crear fingerprints consistentes
 */
class FingerprintGeneratorService
{
  /**
   * Genera una huella digital única para el traffic log
   *
   * @param string $userAgent
   * @param string $ipAddress
   * @param string $originHost
   * @return string
   */
  public function generate(string $userAgent, string $ipAddress, string $originHost): string
  {
    $isPostman = str_contains(request()->header('user_agent'), 'PostmanRuntime');
    if (empty($originHost) && !$isPostman) {
      throw new \Exception('Origin host is empty or not valid');
    } else if ($isPostman) {
      $originHost = 'postman';
    }
    // Normalizar datos para consistencia
    $normalizedHost = $this->normalizeHost($originHost);
    //Current now
    $now = now()->format('Y-m-d');
    // Crear string base para el hash
    $baseString = implode('|', [$userAgent, $ipAddress, $normalizedHost, $now]);
    // Generar hash SHA-256
    return hash('sha256', $baseString);
  }

  /**
   * Genera un fingerprint simple basado solo en user agent e IP
   *
   * Útil para detección de bots o análisis de usuarios únicos
   */
  public function generateSimple(string $userAgent, string $ipAddress): string
  {
    $baseString = implode('|', [$this->normalizeUserAgent($userAgent), $this->normalizeIpAddress($ipAddress)]);

    return hash('md5', $baseString);
  }

  /**
   * Genera un fingerprint temporal para ventanas de tiempo específicas
   *
   * @param string $userAgent
   * @param string $ipAddress
   * @param int $windowMinutes Ventana de tiempo en minutos
   * @return string
   */
  public function generateTemporal(string $userAgent, string $ipAddress, int $windowMinutes = 5): string
  {
    $timeWindow = $this->getTimeWindow($windowMinutes);

    $baseString = implode('|', [$this->normalizeUserAgent($userAgent), $this->normalizeIpAddress($ipAddress), $timeWindow]);

    return hash('sha256', $baseString);
  }

  /**
   * Normaliza el user agent para consistencia
   */
  private function normalizeUserAgent(string $userAgent): string
  {
    // Remover espacios extra y convertir a minúsculas
    $normalized = strtolower(trim($userAgent));

    // Remover versiones específicas que cambian frecuentemente
    $patterns = [
      '/chrome\/[\d\.]+/' => 'chrome/xxx',
      '/firefox\/[\d\.]+/' => 'firefox/xxx',
      '/safari\/[\d\.]+/' => 'safari/xxx',
      '/edge\/[\d\.]+/' => 'edge/xxx',
      '/version\/[\d\.]+/' => 'version/xxx',
    ];

    foreach ($patterns as $pattern => $replacement) {
      $normalized = preg_replace($pattern, $replacement, $normalized);
    }

    return $normalized;
  }

  /**
   * Normaliza la dirección IP
   */
  private function normalizeIpAddress(string $ipAddress): string
  {
    // Para IPv4, mantener solo los primeros 3 octetos para privacidad
    if (filter_var($ipAddress, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
      $parts = explode('.', $ipAddress);
      if (count($parts) === 4) {
        return implode('.', array_slice($parts, 0, 3)) . '.xxx';
      }
    }

    // Para IPv6, mantener solo el prefijo
    if (filter_var($ipAddress, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
      $parts = explode(':', $ipAddress);
      if (count($parts) >= 4) {
        return implode(':', array_slice($parts, 0, 4)) . '::xxx';
      }
    }

    // Fallback para IPs no válidas
    return 'unknown';
  }

  /**
   * Normaliza el host de origen
   */
  private function normalizeHost(string $host): string
  {
    try {
      // Remover protocolo si existe
      $host = preg_replace('/^https?:\/\//', '', $host);

      // Remover www. si existe
      $host = preg_replace('/^www\./', '', $host);

      // Remover path, query parameters y fragments
      $host = strtok($host, '/');
      $host = strtok($host, '?');
      $host = strtok($host, '#');

      // Convertir a minúsculas y limpiar espacios
      return strtolower(trim($host));
    } catch (\Throwable $th) {
      throw new \InvalidArgumentException('Invalid host format: ' . $host, 0, $th);
    }
  }

  /**
   * Obtiene una ventana de tiempo para agrupar requests
   *
   * @param int $windowMinutes Tamaño de la ventana en minutos
   * @return string
   */
  private function getTimeWindow(int $windowMinutes = 5): string
  {
    $now = Carbon::now();

    // Redondear hacia abajo al múltiplo más cercano de windowMinutes
    $roundedMinutes = floor($now->minute / $windowMinutes) * $windowMinutes;

    return $now->format('Y-m-d-H') . '-' . str_pad($roundedMinutes, 2, '0', STR_PAD_LEFT);
  }

  /**
   * Valida si un fingerprint tiene el formato correcto
   */
  public function isValidFingerprint(string $fingerprint): bool
  {
    // SHA-256 debe tener 64 caracteres hexadecimales
    return preg_match('/^[a-f0-9]{64}$/', $fingerprint) === 1;
  }

  /**
   * Extrae información del fingerprint si es posible
   *
   * Nota: Esto es solo para debugging, los fingerprints son one-way hashes
   */
  public function getFingerprintInfo(string $fingerprint): array
  {
    return [
      'length' => strlen($fingerprint),
      'algorithm' => strlen($fingerprint) === 64 ? 'SHA-256' : (strlen($fingerprint) === 32 ? 'MD5' : 'Unknown'),
      'is_valid' => $this->isValidFingerprint($fingerprint),
      'created_at' => Carbon::now()->toISOString(),
    ];
  }
}
