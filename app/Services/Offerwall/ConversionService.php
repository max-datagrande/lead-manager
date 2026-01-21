<?php

namespace App\Services\Offerwall;

use App\Models\OfferwallConversion;
use App\Services\IntegrationService;
use App\Support\SlackMessageBundler;
use Maxidev\Logger\TailLogger as Logger; // Import the custom logger
use Illuminate\Support\Facades\Crypt;
use Exception;

/**
 * Service class for handling offerwall conversion logic.
 */
class ConversionService
{
  public function __construct(
    protected IntegrationService $integrationService
  ) {}

  /**
   * Create a new offerwall conversion record.
   *
   * @param array $data The data for creating the conversion.
   * @return OfferwallConversion
   * @throws Exception
   */
  public function createConversion(array $data): OfferwallConversion
  {
    $offerToken = $data['offer_token'] ?? null;
    $offerData = [];
    $mixLogId = null;
    $trackedFields = [];
    $integrationId = null;

    if (!$offerToken) {
      throw new Exception('Offer token is missing.');
    }

    try {
      $decrypted = Crypt::decryptString($offerToken);
      $parts = explode('|', $decrypted);
    } catch (\Exception $e) {
      Logger::saveLog('Failed to decrypt offer_token.', 'offerwall/conversion-service', 'error', ['offer_token' => $offerToken, 'error' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()]);
      $this->sendSlackAlert('Invalid Offer Token', 'Failed to decrypt offer token.', $data, ['Offer Token' => $offerToken]);
      throw new Exception('Invalid or malformed offer token.');
    }

    if (count($parts) === 3) {
      [$mixLogId, $parsedIntegrationId, $offerIndex] = $parts;
      $mixLogId = (int) $mixLogId;
      $parsedIntegrationId = (int) $parsedIntegrationId;
      $offerIndex = (int) $offerIndex;

      $callLog = \App\Models\IntegrationCallLog::where('loggable_type', \App\Models\OfferwallMixLog::class)
        ->where('loggable_id', $mixLogId)
        ->where('integration_id', $parsedIntegrationId)
        ->first();

      if (!$callLog) {
        Logger::saveLog('IntegrationCallLog not found for given token.', 'offerwall/conversion-service', 'error', ['mixLogId' => $mixLogId, 'parsedIntegrationId' => $parsedIntegrationId, 'file' => __FILE__, 'line' => __LINE__]);
        $this->sendSlackAlert('Call Log Not Found', 'Could not find IntegrationCallLog for a valid token.', $data, [
          'Mix Log ID' => $mixLogId,
          'Parsed Integration ID' => $parsedIntegrationId
        ]);
        throw new Exception('Could not resolve conversion source.');
      }

      $integration = \App\Models\Integration::find($parsedIntegrationId);
      if (!$integration) {
          Logger::saveLog('Integration not found for a valid call log.', 'offerwall/conversion-service', 'error', ['parsedIntegrationId' => $parsedIntegrationId, 'callLogId' => $callLog->id, 'file' => __FILE__, 'line' => __LINE__]);
          $this->sendSlackAlert('Orphaned Call Log Data', 'Integration not found for a valid call log.', $data, [
              'Parsed Integration ID' => $parsedIntegrationId,
              'Call Log ID' => $callLog->id
          ]);
          throw new Exception('Could not resolve integration source.');
      }

      // If we got here, we have a valid integration
      $integrationId = $integration->id;

      // Extract tracked fields from the log's context arrays
      $trackedFields = [
        'state' => $callLog->original_field_values['state'] ?? null,
        'cptype' => $callLog->original_field_values['cptype'] ?? null,
        'placement_id' => $callLog->mapped_field_values['cptype'] ?? null,
      ];

      $offerCompanyName = null;
      // Extract original offer data
      if (!empty($callLog->response_body)) {
        $parserConfig = $integration->response_parser_config;
        $pathOfOffers = $parserConfig['offer_list_path'] ?? '';
        $offers = data_get($callLog->response_body, $pathOfOffers);

        if (isset($offers[$offerIndex])) {
          $offerData = $offers[$offerIndex];
          // Get company name from the offer data using the mapping
          $companyMappingPath = $parserConfig['mapping']['company'] ?? null;
          if ($companyMappingPath) {
            $offerCompanyName = data_get($offerData, $companyMappingPath);
          }
        }
      }
    } else {
        throw new Exception('Invalid offer token structure.');
    }

    if (is_null($integrationId)) {
        Logger::saveLog('Integration ID is null just before creating conversion.', 'offerwall/conversion-service', 'critical', ['data' => $data, 'trackedFields' => $trackedFields, 'file' => __FILE__, 'line' => __LINE__]);
        $this->sendSlackAlert('CRITICAL: Null Integration ID', 'Attempted to create a conversion with a null integration ID.', $data);
        throw new Exception('Failed to determine integration ID for conversion.');
    }

    // Prepare data for creation
    $createData = [
      'integration_id' => $integrationId,
      'amount' => $data['amount'] ?? 0,
      'fingerprint' => $data['fingerprint'],
      'click_id' => $data['click_id'] ?? null,
      'utm_source' => $data['utm_source'] ?? null,
      'utm_medium' => $data['utm_medium'] ?? null,
      'offerwall_mix_log_id' => $mixLogId,
      'offer_data' => $offerData ?: null,
      'pathname' => $data['pathname'] ?? null,
      'tracked_fields' => $trackedFields ?: null,
      'offer_company_name' => $offerCompanyName,
    ];

    try {
      $conversion = OfferwallConversion::create($createData);
      Logger::saveLog('Offerwall conversion created successfully.', 'offerwall/conversion-service', 'info', ['id' => $conversion->id, 'file' => __FILE__, 'line' => __LINE__]);
      return $conversion;
    } catch (Exception $e) {
            Logger::saveLog('Failed to create offerwall conversion in database.', 'offerwall/conversion-service', 'error', ['error' => $e->getMessage(), 'createData' => $createData, 'file' => $e->getFile(), 'line' => $e->getLine()]);
      $this->sendSlackAlert('DB Error on Conversion', 'Failed to save the conversion to the database.', ['error' => $e->getMessage()]);
      throw $e;
    }
  }

  /**
   * Helper to send a formatted Slack alert.
   */
  private function sendSlackAlert(string $title, string $message, array $contextData, array $extraFields = []): void
  {
      $slack = new SlackMessageBundler();
      $slack->addTitle($title, '🚨')
            ->addSection($message)
            ->addDivider();

      if (isset($contextData['fingerprint'])) {
        $slack->addKeyValue('Fingerprint', $contextData['fingerprint']);
      }
      if (isset($contextData['amount'])) {
        $slack->addKeyValue('Amount', (string)$contextData['amount']);
      }

      foreach($extraFields as $key => $value) {
        $slack->addKeyValue($key, (string)$value);
      }

      // Use sendDirect for immediate notification from the server
      $slack->sendDirect('errors');
  }
}

