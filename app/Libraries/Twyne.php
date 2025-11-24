<?php

namespace App\Libraries;

use Illuminate\Support\Facades\Http;
use Maxidev\Logger\TailLogger;

class Twyne
{
  protected string $baseUrl = 'https://metricworx.api.twyne.io';
  protected int $pid = 19;
  protected int $sid = 19;
  protected int $cid = 77;
  protected array $formData = [];
  protected array $apiFields = [
    'first',
    'last',
    'email',
    'address1',
    'city',
    'state',
    'zip',
    'dob',
    'phone',
    'cq1',
    'cq2',
    'cq3',
    'trustedform',
    'ip',
    'subid1'
  ];

  private array $payload;
  private ?array $mapping;
  private string $loggerFolder = 'webhooks/leads/twyne';

  /* Example
   TailLogger::saveLog('Webhook received for source: ' . $source, 'webhooks/leads/store', 'info', ['payload' => $payload]);
  */

  public function __construct(array $payload, ?array $mapping = null)
  {
    $this->payload = $payload;
    $this->mapping = $mapping;

    TailLogger::saveLog('Twyne library initialized', $this->loggerFolder, 'info', [
      'payload_keys' => array_keys($payload),
      'mapping_exists' => $mapping !== null,
      'mapping_keys' => $mapping ? array_keys($mapping) : []
    ]);

    $this->buildRequest();
  }

  private function buildRequest(): void
  {
    $builtData = [];
    $processedFields = [];
    $skippedFields = [];

    TailLogger::saveLog('Starting to build Twyne request', $this->loggerFolder, 'info', [
      'api_fields_count' => count($this->apiFields),
      'payload_keys' => array_keys($this->payload)
    ]);

    foreach ($this->apiFields as $field) {
      $map = $this->mapping[$field] ?? ['source' => $field]; // Default behavior: source key is same as field name

      $sourceKey = $map['source'];
      $value = data_get($this->payload, $sourceKey, $map['default'] ?? null);

      if (isset($map['transform']) && is_callable($map['transform'])) {
        $originalValue = $value;
        $value = $map['transform']($value);
        TailLogger::saveLog("Field '{$field}' transformed", $this->loggerFolder, 'debug', [
          'source_key' => $sourceKey,
          'original_value' => $originalValue,
          'transformed_value' => $value
        ]);
      }

      if ($value !== null) {
        $builtData[$field] = $value;
        $processedFields[] = $field;
      } else {
        $skippedFields[] = ['field' => $field, 'source_key' => $sourceKey];
      }
    }

    // Add static and configured fields
    $this->formData = array_merge($builtData, [
      'istest' => app()->environment('production') ? 'false' : 'true',
      'pid' => $this->pid,
      'sid' => $this->sid,
      'cid' => $this->cid,
    ]);

    TailLogger::saveLog('Twyne request built successfully', $this->loggerFolder, 'info', [
      'processed_fields' => $processedFields,
      'skipped_fields' => $skippedFields,
      'final_form_data_keys' => array_keys($this->formData),
      'environment' => app()->environment()
    ]);
  }

  public function submit()
  {
    TailLogger::saveLog('Submitting Twyne lead', $this->loggerFolder, 'info', [
      'form_data_keys' => array_keys($this->formData),
      'environment' => app()->environment(),
      'base_url' => $this->baseUrl
    ]);

    try {
      $response = Http::asForm()->withHeaders([
        'Accept' => 'application/json',
      ])->post($this->baseUrl . '/lead/submit', $this->formData);

      $responseData = $response->json();

      if ($response->successful()) {
        TailLogger::saveLog('Twyne lead submitted successfully', $this->loggerFolder, 'info', [
          'status_code' => $response->status(),
          'response_data' => $responseData
        ]);
      } else {
        TailLogger::saveLog('Twyne lead submission failed', $this->loggerFolder, 'warning', [
          'status_code' => $response->status(),
          'response_data' => $responseData,
          'form_data' => $this->formData
        ]);
      }

      return $response;

    } catch (\Exception $e) {
      TailLogger::saveLog('Twyne lead submission exception', $this->loggerFolder, 'error', [
        'error_message' => $e->getMessage(),
        'error_code' => $e->getCode(),
        'form_data' => $this->formData,
        'trace' => $e->getTraceAsString()
      ]);

      throw $e;
    }
  }
}
