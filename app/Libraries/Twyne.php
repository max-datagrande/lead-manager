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
    'ip',
    'externalid',
    'subid1',
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
  public function getRequest(): array
  {
    return $this->formData;
  }
  private function buildRequest(): void
  {
    $builtData = [];
    $processedFields = [];
    $skippedFields = [];

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

    // --- Validation Step ---
    $requiredApiFields = $this->apiFields;
    $missingFields = [];
    foreach ($requiredApiFields as $requiredField) {
      if (empty($this->formData[$requiredField])) {
        $missingFields[] = $requiredField;
      }
    }

    if (!empty($missingFields)) {
      throw new \App\Exceptions\MissingRequiredFieldsException(
        'Required fields are missing or empty.',
        $missingFields
      );
    }
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
