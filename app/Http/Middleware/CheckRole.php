<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
  /**
   * Handle an incoming request.
   *
   * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
   * @param  string  ...$roles
   */
  public function handle(Request $request, Closure $next, ...$roles): Response
  {
    // Check if user is logged in and has one of the required roles
    if (! $request->user() || ! in_array($request->user()->role, $roles)) {
      // This will render resources/views/errors/403.blade.php
      abort(403, 'Unauthorized action.');
    }

    return $next($request);
  }
}
