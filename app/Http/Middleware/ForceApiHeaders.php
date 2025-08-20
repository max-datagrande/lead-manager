<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ForceApiHeaders
{
    /**
     * Handle an incoming request.
     *
     * Fuerza headers de API para rutas v1/* para evitar redirecciones de sesión
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $path = $request->path();

        // Detectar rutas v1 de múltiples formas
        $isV1Route = str_starts_with($path, 'v1/') ||
            str_contains($request->fullUrl(), '/v1/') ||
            str_contains($request->getRequestUri(), '/v1/');

        // Si es una ruta v1/*, forzar headers de API antes de middlewares de sesión
        if ($isV1Route) {
            $request->headers->set('Accept', 'application/json');
            $request->headers->set('X-Requested-With', 'XMLHttpRequest');
        }

        return $next($request);
    }
}
