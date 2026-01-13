<?php

namespace App\Services\Offerwall;

use App\Models\OfferwallConversion;
use App\Services\IntegrationService;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
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
   */
  public function createConversion(array $data): OfferwallConversion
  {
    try {
      $offerToken = $data['offer_token'] ?? null;
      $offerData = [];
      $mixLogId = null;
      $trackedFields = [];
      $integrationId = null;

      if ($offerToken) {
        try {
          $decrypted = Crypt::decryptString($offerToken);
          // Format: mix_log_id|integration_id|index
          $parts = explode('|', $decrypted);

          if (count($parts) === 3) {
            [$mixLogId, $parsedIntegrationId, $offerIndex] = $parts;
            $mixLogId = (int) $mixLogId;
            $parsedIntegrationId = (int) $parsedIntegrationId;
            $offerIndex = (int) $offerIndex;

            // Optimized query: Find the call log for this mix and integration
            $callLog = \App\Models\IntegrationCallLog::where('loggable_type', \App\Models\OfferwallMixLog::class)
              ->where('loggable_id', $mixLogId)
              ->where('integration_id', $parsedIntegrationId)
              ->first();

            if ($callLog) {
              // Extract tracked fields from the log's context arrays
              $trackedFields = [
                'cptype' => $callLog->original_field_values['cptype'] ?? null,
                'placement_id' => $callLog->mapped_field_values['cptype'] ?? null,
              ];

              if(!empty($callLog->response_body)) {
                $integration = \App\Models\Integration::find($parsedIntegrationId);
                if ($integration) {
                  // Trust the token for integration and company
                  $integrationId = $integration->id;
                  $parserConfig = $integration->response_parser_config;
                  $pathOfOffers = $parserConfig['offer_list_path'] ?? '';
                  $offers = data_get($callLog->response_body, $pathOfOffers);
                  /*
                  Deprecated because we need the raw original response, not parsed.
                  // Re-parse to get the clean list exactly as it was presented
                  $parsedOffers = $this->integrationService->parseOfferwallResponse($callLog->response_body, $integration);

                  // Sort by CPC to replicate the exact order/index if sorting was applied BEFORE token generation.
                  // BUT WAIT: In MixService, we generate tokens BEFORE sorting or AFTER?
                  // MixService: parse -> enrich (tokens generated here with index 0,1,2...) -> merge -> sort.
                  // The token contains the index relative to THAT INTEGRATION'S response list (before merge/sort).
                  // So $parsedOffers[index] is correct regardless of global sorting. */

                  if (isset($offers[$offerIndex])) {
                    $offerData = $offers[$offerIndex];
                  }
                }
              }
            }
          }
        } catch (\Exception $e) {
          Log::warning("Failed to decrypt or process offer_token: " . $e->getMessage());
        }
      }

      // Prepare data for creation
      $createData = [
        'integration_id' => $integrationId,
        'amount' => $data['amount'] ?? 0, // Assuming amount comes from postback or request
        'fingerprint' => $data['fingerprint'],
        'click_id' => $data['click_id'] ?? null,
        'utm_source' => $data['utm_source'] ?? null,
        'utm_medium' => $data['utm_medium'] ?? null,
        'offerwall_mix_log_id' => $mixLogId,
        'offer_data' => $offerData ?: null,
        'pathname' => $data['pathname'] ?? null,
        'tracked_fields' => $trackedFields ?: null,
      ];

      $conversion = OfferwallConversion::create($createData);

      Log::info('Offerwall conversion created successfully.', ['id' => $conversion->id]);

      return $conversion;
    } catch (Exception $e) {
      Log::error('Failed to create offerwall conversion.', [
        'error' => $e->getMessage(),
        'data' => $data
      ]);

      // Re-throw the exception to be handled by the controller or a global exception handler.
      throw $e;
    }
  }
}
