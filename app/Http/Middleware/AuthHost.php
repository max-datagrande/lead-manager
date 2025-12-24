<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Services\HostValidationService;
use Symfony\Component\HttpFoundation\Response;
use Maxidev\Logger\TailLogger;

class AuthHost
{
  protected HostValidationService $hostValidationService;

  public function __construct(HostValidationService $hostValidationService)
  {
    $this->hostValidationService = $hostValidationService;
  }

  /**
   * Handle an incoming request.
   *
   * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
   */
  public function handle(Request $request, Closure $next): Response
  {
    $host = request()->host();
    $isAdmin = $host ? str_contains($host, 'admin') : false;
    if ($isAdmin) {
      return $next($request);
    }
    $origin = $request->header('Origin');
    $userAgent = $request->header('User-Agent');
    $isPostman = str_contains($userAgent, 'PostmanRuntime');
    $postmanConfig = config('auth.postman');
    // Si es Postman, validar token personalizado
    if ($isPostman && $postmanConfig['enabled']) {
      $token = $request->header('x-postman-auth-token');
      // Aquí podrías validar el token con una lista blanca o contra la base de datos
      if (!$token || $token !== $postmanConfig['secret']) {
        return response()->json(['message' => 'Unauthorized: Invalid Postman token'], 401);
      }

      // Si el token es válido, permitir la petición
      return $next($request);
    }
    // Priorizar el header Origin. Si no está, la petición no es válida para este filtro.
    if (!$origin) {
      $message = 'Forbidden - Origin header missing.';
      $context = ['ip' => $request->ip(), 'url' => $request->fullUrl()];
      TailLogger::saveLog($message, 'middleware/auth-host', 'warning', $context);
      return response()->json(['message' => 'Forbidden: Origin header missing'], 403);
    }

    $targetHost = parse_url($origin, PHP_URL_HOST);

    if (!$targetHost) {
      $message = 'Forbidden - Could not parse host from Origin header.';
      TailLogger::saveLog($message, 'middleware/auth-host', 'warning', ['origin' => $origin, 'ip' => $request->ip(), 'url' => $request->fullUrl()]);
      return response()->json(['message' => 'Forbidden: Invalid Origin header'], 403);
    }

    $parts = explode('.', $targetHost);
    $rootDomain = count($parts) > 2
        ? implode('.', array_slice($parts, -2))
        : $targetHost;
    // Validar el host usando ambos métodos

    if (!$this->hostValidationService->validateFromJson($rootDomain)) {
      $message = 'AuthHost: Forbidden - Host not allowed.';
      TailLogger::saveLog($message, 'middleware/auth-host', 'warning', [
        'target_host' => $targetHost,
        'origin' => $origin,
        'ip' => $request->ip(),
        'url' => $request->fullUrl()
      ]);
      return response()->json(['message' => 'Forbidden'], 403);
    }

    return $next($request);
  }
}
