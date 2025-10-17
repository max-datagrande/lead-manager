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
use Throwable;

class MixService
{
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
          $payload = $this->buildPayload($leadData, $template, $mappingConfig);
          $method = strtolower($prodEnv->method ?? 'post');
          $headers = json_decode($prodEnv->request_headers ?? '[]', true);
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
            $offers = $this->parseOffers($response, $integration);
            $aggregatedOffers = array_merge($aggregatedOffers, $offers);
          }
        }
        
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

  private function buildPayload(array $leadData, string $template, array $mappingConfig): string
  {
    if (empty($template)) {
      return '';
    }

    $replacements = [];
    foreach ($mappingConfig as $tokenName => $config) {
      $value = $leadData[$tokenName] ?? $config['defaultValue'] ?? '';

      if (isset($config['value_mapping']) && array_key_exists($value, $config['value_mapping'])) {
        $value = $config['value_mapping'][$value];
      }

      if (is_array($value) || is_object($value)) {
        $value = json_encode($value);
      }

      $replacements['{' . $tokenName . '}'] = (string) $value;
    }

    if (empty($replacements)) {
      return $template;
    }

    return str_replace(array_keys($replacements), array_values($replacements), $template);
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

  private function parseOffers(Response $response, $integration): array
  {
    $parserConfig = $integration->response_parser_config;
    $jsonResponse = $response->json();
    $offers = data_get($jsonResponse, $parserConfig['offer_list_path'] ?? '');
    
    if (!is_array($offers)) {
      return [];
    }

    $mappedOffers = [];
    foreach ($offers as $offer) {
      $mappedOffer = [];
      foreach ($parserConfig['mapping'] as $key => $valuePath) {
        $mappedOffer[$key] = data_get($offer, $valuePath);
      }
      $mappedOffers[] = $mappedOffer;
    }

    return $mappedOffers;
  }
}
