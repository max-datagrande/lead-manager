<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Maxidev\Logger\TailLogger;

class AuthenticateApiKey
{
  /**
   * Handle an incoming request.
   *
   * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
   */
  public function handle(Request $request, Closure $next): Response
  {
    TailLogger::saveLog('Connection from ' . $request->ip(), 'webhooks/leads/store', 'info', $request->all());
    $apiKey = $request->header('X-API-KEY');
    $validApiKey = config('auth.webhooks.api_key');
    if (!$apiKey || !hash_equals($validApiKey, $apiKey)) {
      return response()->json(['message' => 'Unauthorized'], 401);
    }

    return $next($request);
  }
}
