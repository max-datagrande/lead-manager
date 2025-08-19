<?php

/**
 * Detecta el tipo de dispositivo basado en el user agent
 */
if (!function_exists('get_device_type')) {
  function get_device_type(string $userAgent): string
  {
    if (preg_match('/Mobile|Android|iPhone|iPad/', $userAgent)) {
      return 'Mobile';
    }
    if (preg_match('/Tablet|iPad/', $userAgent)) {
      return 'Tablet';
    }
    return 'Desktop';
  }
}

/**
 * Extrae el navegador del user agent
 */

if (!function_exists('get_browser')) {
  function get_browser(string $userAgent): string
  {
    if (preg_match('/Chrome\/([0-9.]+)/', $userAgent, $matches)) {
      return 'Chrome';
    }
    if (preg_match('/Firefox\/([0-9.]+)/', $userAgent, $matches)) {
      return 'Firefox';
    }
    if (preg_match('/Safari\/([0-9.]+)/', $userAgent, $matches)) {
      return 'Safari';
    }
    if (preg_match('/Edge\/([0-9.]+)/', $userAgent, $matches)) {
      return 'Edge';
    }
    return 'Unknown';
  }
}

/**
 * Extrae el sistema operativo del user agent
 */
if (!function_exists('get_os')) {
  function get_os(string $userAgent): string
  {
    if (preg_match('/Windows NT ([0-9.]+)/', $userAgent, $matches)) {
      return 'Windows';
    }
    if (preg_match('/Mac OS X ([0-9._]+)/', $userAgent, $matches)) {
      return 'macOS';
    }
    if (preg_match('/Linux/', $userAgent)) {
      return 'Linux';
    }
    if (preg_match('/Android ([0-9.]+)/', $userAgent, $matches)) {
      return 'Android';
    }
    if (preg_match('/iPhone OS ([0-9._]+)/', $userAgent, $matches)) {
      return 'iOS';
    }
    return 'Unknown';
  }
}
