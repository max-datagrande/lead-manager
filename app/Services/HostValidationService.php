<?php

namespace App\Services;

use App\Models\Host;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class HostValidationService
{
  private const ALLOWED_HOSTS_PATH = 'app/data/allowed_hosts.json';

  /**
   * Valida un host contra la base de datos
   *
   * @param string $host El host a validar
   * @return bool
   */
  public function validateFromDatabase(string $host): bool
  {
    return Host::where('domain', $host)
      ->where('is_active', true)
      ->exists();
  }

  /**
   * Valida un host contra el archivo JSON de hosts permitidos
   * Soporta validación de subdominios si el dominio base está en la lista
   *
   * @param string $host El host a validar
   * @return bool
   */
  public function validateFromJson(string $host): bool
  {
    $jsonPath = storage_path(self::ALLOWED_HOSTS_PATH);
    if (!File::exists($jsonPath)) {
      return false;
    }
    try {
      $allowedHosts = json_decode(File::get($jsonPath), true);
      if (!is_array($allowedHosts)) {
        return false;
      }

      // Validación directa del host completo
      if (in_array($host, $allowedHosts)) {
        return true;
      }

      // Validación de subdominios
      foreach ($allowedHosts as $allowedHost) {
        // Si el host permitido comienza con un punto, lo tratamos como un wildcard para subdominios
        if (Str::startsWith($allowedHost, '.')) {
          if (Str::endsWith($host, $allowedHost)) {
            return true;
          }
        }
        // Si no, verificamos si el host es un subdominio del dominio permitido
        else {
          $pattern = '.' . preg_quote($allowedHost, '/');
          if (Str::endsWith($host, $pattern)) {
            return true;
          }
        }
      }
      return false;
    } catch (\Exception $e) {
      return false;
    }
  }
}
