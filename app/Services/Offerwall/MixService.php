<?php

namespace App\Services\Offerwall;

use App\Models\Lead;
use App\Models\OfferwallMix;
use App\Models\OfferwallMixLog;
use App\Support\SlackMessageBundler;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Maxidev\Logger\TailLogger;
use App\Services\IntegrationService;
use Throwable;

class MixService
{
  private IntegrationService $integrationService;

  public function __construct(IntegrationService $integrationService)
  {
    $this->integrationService = $integrationService;
  }

  public function fetchAndAggregateOffers(OfferwallMix $mix, string $fingerprint): array
  {
    $startTime = microtime(true);
    $lead = Lead::getLeadWithResponses($fingerprint);

    if (!$lead) {
      return [
        'success' => false,
        'message' => 'Lead not found',
        'status_code' => 404,
        'data' => null
      ];
    }

    $integrations = $mix->integrations()->where('is_active', true)->get();
    $leadData = $this->prepareLeadData($lead);
    $mixLog = null;
    try {
      $result = DB::transaction(function () use ($mix, $lead, $integrations, $leadData, $startTime, &$mixLog) {
        $mixLog = OfferwallMixLog::create([
          'offerwall_mix_id' => $mix->id,
          'fingerprint' => $lead->fingerprint,
          'origin' => $lead->host,
          'total_integrations' => $integrations->count(),
        ]);

        if ($integrations->isEmpty()) {
          return [
            'success' => true,
            'message' => 'No active integrations found',
            'status_code' => 200,
            'data' => []
          ];
        }

        $requestsData = [];
        foreach ($integrations as $integration) {
          $prodEnv = $integration->environments->where('environment', 'production')->first();
          if (!$prodEnv) {
            continue;
          }

          $template = $prodEnv->request_body ?? '';
          $mappingConfig = $integration->request_mapping_config ?? [];
          $payload = $this->integrationService->parseParams($leadData, $template, $mappingConfig);
          $method = strtolower($prodEnv->method ?? 'post');
          $headersParsed = $this->integrationService->parseParams($leadData, $prodEnv->request_headers ?? '[]', $mappingConfig);
          $headers = json_decode($headersParsed, true) ?? [];
          $url = $prodEnv->url;
          $requestsData[$integration->id] = compact('integration', 'url', 'payload', 'method', 'headers');
        }

        $responses = Http::pool(function (Pool $pool) use ($requestsData) {
          foreach ($requestsData as $integrationId => $data) {
            $url = $data['url'];
            $headers = $data['headers'];
            $payloadArray = json_decode($data['payload'], true) ?? [];
            $method = $data['method'];
            $request = $pool->as($integrationId)->withHeaders($headers);
            $request->{$method}($url, $payloadArray);
          }
        });

        $aggregatedOffers = [];
        $successfulCount = 0;

        foreach ($integrations as $integration) {
          if (!isset($responses[$integration->id])) {
            continue;
          }
          $response = $responses[$integration->id];
          $requestData = $requestsData[$integration->id];

          $this->logIntegrationCall($mixLog, $integration, $response, $requestData['method'], $requestData['headers'], $requestData['payload']);
          if ($response->successful()) {
            $successfulCount++;
            $offers = $this->integrationService->parseOfferwallResponse($response->json(), $integration);
            $offers = $this->enrichOffersWithToken($offers, $mixLog->id, $integration->id);
            $aggregatedOffers = array_merge($aggregatedOffers, $offers);
          }
        }

        // Ordenar ofertas por CPC
        $aggregatedOffers = $this->sortOffersByCpc($aggregatedOffers);
        $durationMs = (microtime(true) - $startTime) * 1000;
        $durationRounded = (int) round($durationMs);
        $mixLog->update([
          'successful_integrations' => $successfulCount,
          'failed_integrations' => $integrations->count() - $successfulCount,
          'total_offers_aggregated' => count($aggregatedOffers),
          'total_duration_ms' => $durationRounded,
        ]);

        // Validar que el array de ofertas no esté vacío
        if (empty($aggregatedOffers)) {
          return [
            'success' => false,
            'message' => 'No offers were found from any integration',
            'status_code' => 404,
            'data' => [],
            'meta' => [
              'total_offers' => 0,
              'successful_integrations' => $successfulCount,
              'failed_integrations' => $integrations->count() - $successfulCount,
              'duration_ms' => $durationRounded
            ]
          ];
        }

        return [
          'success' => true,
          'message' => 'Offers aggregated successfully',
          'status_code' => 200,
          'data' => $aggregatedOffers,
          'meta' => [
            'total_offers' => count($aggregatedOffers),
            'successful_integrations' => $successfulCount,
            'failed_integrations' => $integrations->count() - $successfulCount,
            'duration_ms' => $durationRounded
          ]
        ];
      });

      return $result;
    } catch (Throwable $e) {
      TailLogger::saveLog('Failed to process offerwall mix', 'offerwall/mix-service', 'error', ['error' => $e->getMessage(), 'fingerprint' => $fingerprint, 'file' => $e->getFile(), 'line' => $e->getLine()]);
      $slack = new SlackMessageBundler();
      $slack->addTitle('Critical Offerwall Mix Failure', '🚨')
        ->addSection('The offerwall mix processing failed due to an unexpected error.')
        ->addDivider()
        ->addKeyValue('Fingerprint', $fingerprint, true, '🆔');

      if ($mixLog) {
        $slack->addKeyValue('Mix Log ID', $mixLog->id, false, '📋');
      }

      $slack->addKeyValue('Error Message', $e->getMessage(), true, '📄')
        ->addButton('View Admin', route('home'), 'primary');

      // Registrar en logs en modo debug, no enviar a Slack
      if (app()->environment('local')) {
        $slack->sendDebugLog('errors');
      } else {
        $slack->sendDirect();
      }

      return [
        'success' => false,
        'message' => 'An unexpected error occurred while processing offers',
        'status_code' => 500,
        'data' => null
      ];
    }
  }

  private function prepareLeadData(Lead $lead): array
  {
    return $lead->leadFieldResponses->pluck('value', 'field.name')->toArray();
  }



  private function logIntegrationCall(OfferwallMixLog $mixLog, $integration, Response $response, string $method, array $headers, string $payload): void
  {
    $duration = $response->transferStats ? $response->transferStats->getTransferTime() * 1000 : 0;
    $mixLog->integrationCallLogs()->create([
      'integration_id' => $integration->id,
      'status' => $response->successful() ? 'success' : 'failed',
      'http_status_code' => $response->status(),
      'duration_ms' => (int) round($duration),
      'request_url' => (string) $response->effectiveUri(),
      'request_method' => strtoupper($method),
      'request_headers' => $headers,
      'request_payload' => json_decode($payload, true) ?? ['raw_body' => $payload],
      'response_headers' => $response->headers(),
      'response_body' => $response->json() ?? $response->body(),
    ]);
  }

  /**
   * Agrega un token único a cada oferta para rastrear su origen.
   */
  private function enrichOffersWithToken(array $offers, int $mixLogId, int $integrationId): array
  {
    foreach ($offers as $index => &$offer) {
      // Formato: mix_log_id|integration_id|index_in_response
      $rawToken = "{$mixLogId}|{$integrationId}|{$index}";
      $offer['offer_token'] = \Illuminate\Support\Facades\Crypt::encryptString($rawToken);
    }
    unset($offer); // Romper referencia

    return $offers;
  }

  /**
   * Ordena las ofertas por CPC de mayor a menor.
   * Los valores nulos o inválidos se mueven al final.
   * Agrega un campo 'pos' indicando el ranking (empezando en 1).
   */
  private function sortOffersByCpc(array $offers): array
  {
    usort($offers, function ($a, $b) {
      $cpcA = isset($a['cpc']) && is_numeric($a['cpc']) ? (float)$a['cpc'] : -1;
      $cpcB = isset($b['cpc']) && is_numeric($b['cpc']) ? (float)$b['cpc'] : -1;

      if ($cpcA == $cpcB) {
        return 0;
      }
      // Orden descendente (mayor a menor)
      return ($cpcA > $cpcB) ? -1 : 1;
    });

    // Asignar posición
    foreach ($offers as $index => &$offer) {
      $offer['pos'] = $index + 1;
    }

    return $offers;
  }
}
