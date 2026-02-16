<?php

namespace App\Http\Controllers\Api;

use Illuminate\Support\Facades\Http;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Maxidev\Logger\TailLogger;

class ProxyController extends Controller
{
  protected $webhooks;

  public function __construct()
  {
    $this->webhooks = config('slack-alerts.webhook_urls');
  }

  public function forward(Request $request)
  {
    $key = $request->input('key');
    $payload = $request->input('payload');

    if (empty($key) || empty($payload)) {
      return response()->json([
        'error' => 'Missing key or payload content'
      ], 400);
    }

    // 2. Buscamos el Webhook correspondiente en nuestro diccionario (config)
    $webhookUrl = $this->webhooks[$key] ?? null;

    if (!$webhookUrl) {
      return response()->json([
        'error' => "Invalid or unconfigured proxy key: {$key}"
      ], 422);
    }

    try {
      // 3. Reenviamos el payload tal cual al Webhook de Slack
      $response = Http::post($webhookUrl, $payload);

      if ($response->failed()) {
        throw new \Exception("Slack API returned error: " . $response->body());
      }
      $jsonResponse = $response->json() ?? $response->body() ?? 'No JSON response';
      return response()->json(['status' => 'forwarded', 'raw' => $jsonResponse], 200);
    } catch (\Exception $e) {
      TailLogger::saveLog(
        'Proxy Error',
        'api/proxy',
        'error',
        [
          'key' => $key,
          'error' => $e->getMessage(),
          'file' => $e->getFile(),
          'line' => $e->getLine(),
        ]
      );
      return response()->json(['error' => 'Failed to forward to Slack', 'raw' => $e->getMessage()], 500);
    }
  }
}
