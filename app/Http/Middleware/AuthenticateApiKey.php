<?php

namespace App\Http\Middleware;

use App\Services\WebhookApiKeyService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Maxidev\Logger\TailLogger;

class AuthenticateApiKey
{
    protected $webhookService;

    public function __construct(WebhookApiKeyService $webhookService)
    {
        $this->webhookService = $webhookService;
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        TailLogger::saveLog('Connection from ' . $request->ip(), 'webhooks/leads/store', 'info', ['request' => $request->all(), 'headers' => $request->headers->all()]);

        $source = $request->route('source');

        if (!$source) {
            return response()->json(['message' => 'Webhook source not specified.'], 400);
        }

        $validationResult = $this->webhookService->validate($source, $request);

        // If the service returned a Response object, it means it handled the request entirely.
        if ($validationResult instanceof Response) {
            return $validationResult;
        }

        // If it returned false, validation failed.
        if ($validationResult === false) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        // Otherwise, validation passed. Proceed to the controller.
        return $next($request);
    }
}
