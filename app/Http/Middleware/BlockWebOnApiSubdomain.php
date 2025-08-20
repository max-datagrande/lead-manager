<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Maxidev\Logger\TailLogger as Logger;

class BlockWebOnApiSubdomain
{
  /**
   * Handle an incoming request.
   *
   * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
   */
  public function handle(Request $request, Closure $next): Response
  {
    $host = $request->getHost();
    $path = $request->path();

    // Detectar rutas v1 de múltiples formas
    $isV1Route = str_starts_with($path, 'v1/') ||
      str_contains($request->fullUrl(), '/v1/') ||
      str_contains($request->getRequestUri(), '/v1/');
    // Si es una ruta v1/*, permitir la petición INMEDIATAMENTE
    if ($isV1Route) {
      return $next($request);
    }


    // Verificar si es una petición API basada en headers o contenido
    $isApiRequest = $request->expectsJson() ||
      $request->header('Accept') === 'application/json' ||
      str_contains($request->header('Content-Type', ''), 'application/json');

    // Si es una petición API, permitir la petición
    if ($isApiRequest) {
      return $next($request);
    }

    $apiUrl = env('APP_API_URL');
    $isInternalApi = $apiUrl ? (parse_url($apiUrl, PHP_URL_HOST) === $host) : false;

    $subdomainStartsWithApi = str_starts_with($host, 'api.');
    $isApiOrigin = $subdomainStartsWithApi || $isInternalApi;

    // Solo bloquear si es subdominio api., NO es una ruta v1/* y NO es una petición API
    if ($isApiOrigin) {
      Logger::saveLog('Access attempt blocked in API subdomain', 'middleware/block-web-on-api-subdomain', 'warning', [
        'ip' => $request->ip(),
        'host' => $host,
        'url' => $request->fullUrl(),
        'user_agent' => $request->userAgent(),
        'path' => $path,
        'method' => $request->method(),
        'is_v1_route' => $isV1Route,
        'is_api_request' => $isApiRequest,
        'subdomain_starts_with_api' => $subdomainStartsWithApi,
        'is_internal_api' => $isInternalApi,
      ]);
      return response()->json(['error' => 'Forbidden - Only API routes allowed'], 403);
    }

    return $next($request);
  }
}
