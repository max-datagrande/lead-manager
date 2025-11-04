<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\File;
use Maxidev\Logger\TailLogger;

class DomainWhitelistMiddleware
{
  const WHITELIST_FILE = 'app/data/whitelist_domains.json';
  /**
   * Handle an incoming request.
   *
   * @param  \Illuminate\Http\Request  $request
   * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
   * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
   */
  public function handle(Request $request, Closure $next)
  {
    // Obtener el dominio de origen
    $origin = $request->header('Origin') ?? $request->header('Referer');
    TailLogger::saveLog('Request to API', 'middleware/geolocation', 'info', [
      'origin' => $origin,
      'ip' => $request->ip(),
      'user_agent' => $request->userAgent()
    ]);
    // Si no hay origen, usar el host de la petición
    if (!$origin) {
      $origin = $request->getSchemeAndHttpHost();
    }

    // Extraer el dominio del origen
    $domain = parse_url($origin, PHP_URL_HOST);

    // Verificar si el dominio está en la whitelist
    if (!$this->isDomainAllowed($domain)) {
      TailLogger::saveLog('Access denied', 'middleware/geolocation', 'warning', [
        'domain' => $domain,
        'origin' => $origin,
        'ip' => $request->ip(),
        'user_agent' => $request->userAgent()
      ]);
      return response()->json([
        'error' => 'Access denied',
        'message' => 'Domain not authorized to access this API',
        'code' => 'DOMAIN_NOT_ALLOWED'
      ], Response::HTTP_FORBIDDEN);
    }

    return $next($request);
  }

  /**
   * Verificar si un dominio está permitido
   *
   * @param string $domain
   * @return bool
   */
  private function isDomainAllowed(string $domain): bool
  {
    try {
      $jsonPath = storage_path(self::WHITELIST_FILE);
      // Cargar la whitelist desde el archivo JSON
      if (!File::exists($jsonPath)) {
        TailLogger::saveLog('Whitelist file not found', 'middleware/geolocation', 'error', [
          'path' => $jsonPath,
        ]);
        return false;
      }

      $whitelistContent = File::get($jsonPath);
      $whitelist = json_decode($whitelistContent, true);

      if (!$whitelist || !isset($whitelist['allowed_domains'])) {
        TailLogger::saveLog('Invalid format in whitelist file', 'middleware/geolocation', 'error', [
          'path' => self::WHITELIST_FILE,
        ]);
        return false;
      }

      // Verificar coincidencia exacta
      if (in_array($domain, $whitelist['allowed_domains'])) {
        return true;
      }
      return false;
    } catch (\Throwable $e) {
      TailLogger::saveLog('Error while verifying whitelist', 'middleware/geolocation', 'error', [
        'error' => $e->getMessage(),
        'domain' => $domain
      ]);
      return false;
    }
  }
}
