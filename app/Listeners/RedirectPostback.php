<?php

namespace App\Listeners;

use App\Events\PostbackProcessed;
use App\Models\PostbackApiRequests;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Http;
use Maxidev\Logger\TailLogger;
use Carbon\Carbon;
use App\Models\Postback;

class RedirectPostback implements ShouldQueue
{
  use InteractsWithQueue;

  /**
   * Handle the event.
   */
  public function handle(PostbackProcessed $event): void
  {
    $postback = $event->postback;
    $clickId = $postback->click_id;
    $payout = $postback->payout;
    $offer_id = $postback->offer_id;

    TailLogger::saveLog("Initiating processed postback redirection", 'listeners/redirect-postback', 'info', [
      'postback_id' => $postback->id,
      'click_id' => $clickId,
      'payout' => $payout,
      'vendor' => $postback->vendor,
      'offer_id' => $offer_id
    ]);
    $offers = collect(config('offers.maxconv'));
    $offer = $offers->where('offer_id', $offer_id)->first() ?? null;

    if (!$offer) {
      TailLogger::saveLog("Offer not found", 'listeners/redirect-postback', 'warning', [
        'postback_id' => $postback->id,
        'vendor' => $postback->vendor,
        'offer_id' => $offer_id
      ]);
      return;
    }
    /*
    */
    $postbackUrl = $offer['postback_url'];
    if (!$postbackUrl) {
      TailLogger::saveLog("No se encontró URL de redirección para el vendor", 'listeners/redirect-postback', 'warning', [
        'postback_id' => $postback->id,
        'vendor' => $postback->vendor
      ]);
      return;
    }
    // Preparar los datos para el postback
    $postbackData = [
      'clid' => $clickId,
      'payout' => $payout,
      'txid' => $postback->txid,
      'currency' => $postback->currency,
      'event' => $postback->event,
    ];
    try {
      $startTime = microtime(true);
      $response = Http::timeout(30)->get($postbackUrl, $postbackData);
      $responseTime = (int) ((microtime(true) - $startTime) * 1000);

      // Registrar la petición en PostbackApiRequests
      PostbackApiRequests::create([
        'postback_id' => $postback->id,
        'request_id' => uniqid('req_'),
        'service' => 'max_conv',
        'endpoint' => $postbackUrl,
        'method' => 'GET',
        'request_data' => $postbackData,
        'response_data' => $response->body(),
        'status_code' => $response->status(),
        'response_time_ms' => $responseTime,
        'related_type' => 'postback',
      ]);

      if ($response->successful()) {
        TailLogger::saveLog("Postback redirigido exitosamente", 'listeners/redirect-postback', 'success', [
          'postback_id' => $postback->id,
          'redirect_url' => $postbackUrl,
          'status_code' => $response->status(),
          'response_time_ms' => $responseTime
        ]);
      } else {
        TailLogger::saveLog("Error en la redirección del postback", 'listeners/redirect-postback', 'error', [
          'postback_id' => $postback->id,
          'redirect_url' => $postbackUrl,
          'status_code' => $response->status(),
          'response_body' => $response->body(),
          'response_time_ms' => $responseTime
        ]);
      }
    } catch (\Throwable $e) {
      TailLogger::saveLog("Excepción al redirigir postback", 'listeners/redirect-postback', 'error', [
        'postback_id' => $postback->id,
        'exception' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
      ]);

      // Registrar el error en PostbackApiRequests
      PostbackApiRequests::create([
        'postback_id' => $postback->id,
        'service' => 'postback_redirect',
        'endpoint' => $redirectUrl ?? 'unknown',
        'request_data' => $postbackData ?? [],
        'response_data' => [
          'error' => $e->getMessage(),
          'trace' => $e->getTraceAsString()
        ],
        'status_code' => 0,
        'response_time_ms' => 0,
        'related_type' => 'postback_redirect_error',
        'related_id' => $postback->id
      ]);
    }
  }
}
