<?php

namespace App\Services\Offerwall;

use App\Models\Field;
use App\Models\Integration;
use App\Models\Lead;
use App\Models\LeadFieldResponse;
use App\Models\OfferwallMix;
use App\Models\OfferwallMixLog;
use App\Models\TrafficLog;
use App\Models\User;
use App\Services\IntegrationService;
use App\Services\PayloadProcessorService;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Maxidev\Logger\TailLogger;
use Throwable;

class TesterService
{
  private const SYSTEM_MIX_NAME = 'System: Offerwall Tester';

  public function __construct(private IntegrationService $integrationService) {}

  /**
   * Get the dynamic fields for an integration based on its tokenMappings.
   *
   * @return array{fields: array, cptypeField: array|null, stateField: array|null}
   */
  public function getIntegrationFields(Integration $integration): array
  {
    $integration->loadMissing('tokenMappings.field');

    $fieldData = [];
    $cptypeField = null;
    $stateField = null;

    foreach ($integration->tokenMappings as $mapping) {
      $field = $mapping->field;
      if (!$field) {
        continue;
      }

      $entry = [
        'token' => $field->name,
        'label' => $field->label ?? ucfirst(str_replace('_', ' ', $field->name)),
        'possible_values' => $field->possible_values ?? [],
        'default_value' => $mapping->default_value ?? '',
      ];

      if (strtolower($field->name) === 'cptype') {
        $cptypeField = $entry;
      } elseif (strtolower($field->name) === 'state') {
        $stateField = $entry;
      } else {
        $fieldData[] = $entry;
      }
    }

    return [
      'fields' => $fieldData,
      'cptypeField' => $cptypeField,
      'stateField' => $stateField,
    ];
  }

  /**
   * Prepare test context: creates synthetic data and returns IDs for sequential per-cptype execution.
   *
   * @param  array<string, string>  $fieldValues  Token name => value
   * @return array{mix_log_id: int, lead_id: int, integration_id: int}
   */
  public function prepareTest(int $integrationId, array $fieldValues, User $user): array
  {
    $request = request();
    $isDevMode = app()->environment('local');
    $integration = Integration::with(['environments'])->findOrFail($integrationId);
    $prodEnv = $integration->environments->where('environment', 'production')->first();
    $ipAddress = $isDevMode ? 'localhost' : $request->ip() ?? '127.0.0.1';
    $host = $isDevMode ? 'localhost' : $request->getHost();
    if (!$prodEnv) {
      throw new \RuntimeException('No production environment configured for this integration');
    }

    return DB::transaction(function () use ($integration, $fieldValues, $user, $ipAddress, $host) {
      $fingerprint = $this->generateTestFingerprint($user);
      $stateValue = $fieldValues['state'] ?? null;

      // Reuse or create TrafficLog (persistent per user)
      $trafficLog = TrafficLog::where('fingerprint', $fingerprint)->first();

      if ($trafficLog) {
        $trafficLog->update([
          'state' => $stateValue,
          'ip_address' => $ipAddress,
          'host' => $host,
          'visit_count' => $trafficLog->visit_count + 1,
        ]);
      } else {
        $trafficLog = $this->createTestTrafficLog($fingerprint, $stateValue, $ipAddress, $host);
      }

      // Reuse or create Lead (persistent per user)
      $lead = Lead::where('fingerprint', $fingerprint)->first();

      if ($lead) {
        $lead->update([
          'ip_address' => $ipAddress,
          'website' => $host,
        ]);
      } else {
        $lead = $this->createTestLead($trafficLog, $ipAddress, $host);
      }

      // Update field responses (updateOrCreate handles both new and existing)
      $this->createTestLeadFieldResponses($lead, $fieldValues, $fingerprint);

      $systemMix = $this->getOrCreateSystemMix($user);

      $mixLog = OfferwallMixLog::create([
        'offerwall_mix_id' => $systemMix->id,
        'fingerprint' => $fingerprint,
        'origin' => 'offerwall-tester',
        'placement' => null,
        'total_integrations' => 0,
        'successful_integrations' => 0,
        'failed_integrations' => 0,
        'total_offers_aggregated' => 0,
        'total_duration_ms' => 0,
      ]);

      return [
        'mix_log_id' => $mixLog->id,
        'lead_id' => $lead->id,
        'integration_id' => $integration->id,
      ];
    });
  }

  /**
   * Execute a single cptype test call and update mix log counters incrementally.
   *
   * @return array{success: bool, message: string, data: array{cptype: string, section: array}}
   */
  public function executeSingleCptype(int $integrationId, int $mixLogId, int $leadId, ?string $cptype): array
  {
    $integration = Integration::with(['environments.fieldHashes', 'tokenMappings.field'])->findOrFail($integrationId);
    $prodEnv = $integration->environments->where('environment', 'production')->first();

    if (!$prodEnv) {
      return [
        'success' => false,
        'message' => 'No production environment configured',
        'data' => [
          'cptype' => $cptype ?? 'default',
          'section' => [
            'offers' => [],
            'callLog' => null,
            'error' => 'No production environment configured',
          ],
        ],
      ];
    }

    $mixLog = OfferwallMixLog::findOrFail($mixLogId);
    $lead = Lead::with('leadFieldResponses.field')->findOrFail($leadId);
    $leadData = $lead->leadFieldResponses->pluck('value', 'field.name')->toArray();

    if ($cptype !== null) {
      $leadData['cptype'] = $cptype;

      // Persist cptype to LeadFieldResponse so the last tested value is traceable
      $cptypeField = Field::where('name', 'cptype')->first();
      if ($cptypeField) {
        LeadFieldResponse::updateOrCreate(
          ['lead_id' => $leadId, 'field_id' => $cptypeField->id],
          ['value' => $cptype, 'fingerprint' => $lead->fingerprint],
        );
      }
    }

    $result = $this->executeSingleCall($integration, $prodEnv, $leadData, $mixLog);

    // Update mix log counters incrementally
    $mixLog->increment('total_integrations');

    if ($result['success']) {
      $mixLog->increment('successful_integrations');
    } else {
      $mixLog->increment('failed_integrations');
    }

    $offerCount = count($result['sectionData']['offers']);
    if ($offerCount > 0) {
      $mixLog->increment('total_offers_aggregated', $offerCount);
    }

    return [
      'success' => true,
      'message' => 'OK',
      'data' => [
        'cptype' => $cptype ?? 'default',
        'section' => $result['sectionData'],
      ],
    ];
  }

  /**
   * Execute a single HTTP call to the integration and parse the response.
   */
  private function executeSingleCall(Integration $integration, $prodEnv, array $leadData, OfferwallMixLog $mixLog): array
  {
    $processor = new PayloadProcessorService();
    $replacements = $processor->buildReplacements($integration, $prodEnv, $leadData);

    $payloadArray = json_decode($processor->applyReplacements($prodEnv->request_body ?? '{}', $replacements), true) ?? [];

    $payloadArray = $processor->applyTwigTransformer($integration, $payloadArray);

    $headers = json_decode($processor->applyReplacements($prodEnv->request_headers ?? '[]', $replacements), true) ?? [];
    $url = $processor->applyReplacements($prodEnv->url ?? '', $replacements);

    $method = strtolower($prodEnv->method ?? 'post');

    // Execute HTTP call
    $callStartTime = microtime(true);
    $response = null;
    $exception = null;

    try {
      $response = Http::withHeaders($headers)->{$method}($url, $payloadArray);
    } catch (Throwable $e) {
      $exception = $e;
    }

    $callDurationMs = (int) round((microtime(true) - $callStartTime) * 1000);

    // Log the call
    $callLog = $this->logIntegrationCall(
      $mixLog,
      $integration,
      $response ?? $exception,
      $method,
      $url,
      $headers,
      $payloadArray,
      null,
      null,
      $callDurationMs,
    );

    // Parse response
    $offers = [];
    $isSuccess = $response instanceof Response && $response->successful();

    if ($isSuccess) {
      $offers = $this->integrationService->parseOfferwallResponse($response->json() ?? [], $integration);
      usort($offers, fn($a, $b) => ((float) ($b['cpc'] ?? 0)) <=> ((float) ($a['cpc'] ?? 0)));
      foreach ($offers as $i => &$offer) {
        $offer['pos'] = $i + 1;
      }
      unset($offer);
    }

    // Build call log data for frontend
    $callLogData = [
      'id' => $callLog->id,
      'status' => $isSuccess ? 'success' : 'failed',
      'http_status_code' => $response instanceof Response ? $response->status() : 0,
      'duration_ms' => $callDurationMs,
      'request_url' => $url,
      'request_method' => strtoupper($method),
      'request_headers' => $headers,
      'request_payload' => $payloadArray,
      'response_headers' => $response instanceof Response ? $response->headers() : [],
      'response_body' => $response instanceof Response ? $response->json() ?? $response->body() : $exception?->getMessage() ?? 'Unknown error',
      'original_field_values' => [],
      'mapped_field_values' => [],
    ];

    return [
      'success' => $isSuccess,
      'sectionData' => [
        'offers' => $offers,
        'callLog' => $callLogData,
        'error' => $isSuccess ? null : $exception?->getMessage() ?? 'HTTP ' . ($response?->status() ?? 'error'),
      ],
    ];
  }

  /**
   * Log an integration call to the database (mirrors MixService::logIntegrationCall).
   */
  private function logIntegrationCall(
    OfferwallMixLog $mixLog,
    Integration $integration,
    Response|Throwable|null $response,
    string $method,
    string $url,
    array $headers,
    array $payload,
    ?array $originalValues,
    ?array $mappedValues,
    int $durationMs,
  ): \App\Models\IntegrationCallLog {
    $isResponse = $response instanceof Response;

    $status = 'failed';
    $statusCode = 0;
    $responseBody = 'Unknown Error';
    $responseHeaders = [];

    if ($isResponse) {
      $status = $response->successful() ? 'success' : 'failed';
      $statusCode = $response->status();
      $responseBody = $response->json() ?? $response->body();
      $responseHeaders = $response->headers();
    } elseif ($response instanceof Throwable) {
      $responseBody = [
        'error' => get_class($response),
        'message' => $response->getMessage(),
      ];
    }

    return $mixLog->integrationCallLogs()->create([
      'integration_id' => $integration->id,
      'status' => $status,
      'http_status_code' => $statusCode,
      'duration_ms' => $durationMs,
      'request_url' => $url,
      'request_method' => strtoupper($method),
      'request_headers' => $headers,
      'request_payload' => $payload,
      'response_headers' => $responseHeaders,
      'response_body' => $responseBody,
      'original_field_values' => $originalValues,
      'mapped_field_values' => $mappedValues,
    ]);
  }

  /**
   * Get or create the system OfferwallMix used for test logging.
   */
  private function getOrCreateSystemMix(User $user): OfferwallMix
  {
    return OfferwallMix::firstOrCreate(
      ['name' => self::SYSTEM_MIX_NAME],
      [
        'description' => 'Auto-created system mix for offerwall integration testing. Do not delete.',
        'is_active' => false,
        'user_id' => $user->id,
      ],
    );
  }

  /**
   * Generate a deterministic fingerprint per user for test persistence.
   */
  private function generateTestFingerprint(User $user): string
  {
    return hash('sha256', 'offerwall_test_' . $user->id);
  }

  /**
   * Create a synthetic TrafficLog for the test.
   */
  private function createTestTrafficLog(string $fingerprint, ?string $state, string $ipAddress, string $host): TrafficLog
  {
    return TrafficLog::create([
      'id' => Str::uuid()->toString(),
      'fingerprint' => $fingerprint,
      'visit_date' => now()->toDateString(),
      'visit_count' => 1,
      'ip_address' => $ipAddress,
      'user_agent' => 'OfferwallTester/1.0',
      'device_type' => 'desktop',
      'browser' => 'OfferwallTester',
      'os' => 'System',
      'host' => $host,
      'path_visited' => '/offerwall/tester',
      'state' => $state,
      'country_code' => 'US',
      'is_bot' => false,
    ]);
  }

  /**
   * Create a Lead from the synthetic TrafficLog.
   */
  private function createTestLead(TrafficLog $trafficLog, string $ipAddress, string $host): Lead
  {
    return Lead::create([
      'fingerprint' => $trafficLog->fingerprint,
      'website' => $host,
      'ip_address' => $ipAddress,
    ]);
  }

  /**
   * Create LeadFieldResponses for each submitted field value.
   */
  private function createTestLeadFieldResponses(Lead $lead, array $fieldValues, string $fingerprint): void
  {
    foreach ($fieldValues as $fieldName => $value) {
      if ($value === null || $value === '') {
        continue;
      }

      $field = Field::where('name', $fieldName)->first();
      if (!$field) {
        continue;
      }

      LeadFieldResponse::updateOrCreate(
        [
          'lead_id' => $lead->id,
          'field_id' => $field->id,
        ],
        [
          'value' => $value,
          'fingerprint' => $fingerprint,
        ],
      );
    }
  }
}
