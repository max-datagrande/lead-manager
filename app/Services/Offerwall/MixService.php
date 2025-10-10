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
    $lead = Lead::getLeadResponses($fingerprint);
    if (!$lead) {
      return ['error' => 'Lead not found'];
    }

    $integrations = $mix->integrations()->where('is_active', true)->get();
    $leadData = $this->prepareLeadData($lead);
    $mixLog = null;

    try {
      return DB::transaction(function () use ($mix, $lead, $integrations, $leadData, $startTime, &$mixLog) {
        $mixLog = OfferwallMixLog::create([
          'offerwall_mix_id' => $mix->id,
          'fingerprint' => $lead->fingerprint,
          'origin' => $lead->host,
          'total_integrations' => $integrations->count(),
        ]);

        if ($integrations->isEmpty()) {
          return [];
        }

        $responses = Http::pool(function (Pool $pool) use ($integrations, $leadData) {
          foreach ($integrations as $integration) {
            $payload = $this->buildPayload($leadData, $integration->request_body['template'] ?? [], $integration->request_mapping_config ?? []);
            $pool->as($integration->id)->withHeaders($integration->environments->first()->request_headers ?? [])->post($integration->environments->first()->url, $payload);
          }
        });

        $aggregatedOffers = [];
        $successfulCount = 0;

        foreach ($integrations as $integration) {
          $response = $responses[$integration->id];
          $this->logIntegrationCall($mixLog, $integration, $response);

          if ($response->successful()) {
            $successfulCount++;
            $offers = $this->parseOffers($response, $integration);
            $aggregatedOffers = array_merge($aggregatedOffers, $offers);
          }
        }

        $mixLog->update([
          'successful_integrations' => $successfulCount,
          'failed_integrations' => $integrations->count() - $successfulCount,
          'total_offers_aggregated' => count($aggregatedOffers),
          'total_duration_ms' => (microtime(true) - $startTime) * 1000,
        ]);

        return $aggregatedOffers;
      });
    } catch (Throwable $e) {
      TailLogger::saveLog('Failed to process offerwall mix', 'offerwall/mix-service', 'error', ['error' => $e->getMessage()]);

      $slack = new SlackMessageBundler();
      $slack->addTitle('Critical Offerwall Mix Failure', '🚨')
        ->addSection('The offerwall mix processing failed due to an unexpected error.')
        ->addDivider()
        ->addKeyValue('Fingerprint', $fingerprint, true, '🆔');

      if ($mixLog) {
        $slack->addKeyValue('Mix Log ID', $mixLog->id, false, '📋');
      }

      $slack->addKeyValue('Error Message', $e->getMessage(), true, '📄')
        ->addButton('View Admin', route('home'), 'primary')
        ->sendDirect();

      return ['error' => 'An unexpected error occurred'];
    }
  }

  private function prepareLeadData(Lead $lead): array
  {
    return $lead->leadFieldResponses->pluck('value', 'fields.name')->toArray();
  }

  private function buildPayload(array $leadData, array $template, array $mappingConfig): array
  {
    $payload = [];
    foreach ($template as $key => $token) {
      if (is_array($token)) {
        $payload[$key] = $this->buildPayload($leadData, $token, $mappingConfig);
      } else {
        $tokenName = str_replace(['{', '}'], '', $token);
        $config = $mappingConfig[$tokenName] ?? [];
        $value = $leadData[$tokenName] ?? $config['defaultValue'] ?? null;

        if (isset($config['value_mapping']) && isset($config['value_mapping'][$value])) {
          $value = $config['value_mapping'][$value];
        }

        $payload[$key] = $value;
      }
    }
    return $payload;
  }

  private function logIntegrationCall(OfferwallMixLog $mixLog, $integration, Response $response): void
  {
    $mixLog->integrationCallLogs()->create([
      'integration_id' => $integration->id,
      'status' => $response->successful() ? 'success' : 'failed',
      'http_status_code' => $response->status(),
      'duration_ms' => $response->transferStats ? $response->transferStats->getTransferTime() * 1000 : 0,
      'request_url' => $response->effectiveUri(),
      'request_method' => 'POST', // Assuming POST
      // 'request_headers' => // Need to get this from the request
      // 'request_payload' => // Need to get this from the request
      'response_headers' => $response->headers(),
      'response_body' => $response->json() ?? $response->body(),
    ]);
  }

  private function parseOffers(Response $response, $integration): array
  {
    $parserConfig = $integration->response_parser_config;
    $offers = data_get($response->json(), $parserConfig['offer_list_path'] ?? '');

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
