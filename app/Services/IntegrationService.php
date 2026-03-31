<?php

namespace App\Services;

use App\Models\Integration;
use App\Models\IntegrationEnvironment;
use App\Models\OfferwallResponseConfig;
use App\Models\PingResponseConfig;
use App\Models\PostResponseConfig;
use Illuminate\Support\Arr;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Http;
use Maxidev\Logger\TailLogger;
use Exception;

class IntegrationService
{
  /**
   * Get all integrations.
   */
  public function getIntegrations(array $filters = [])
  {
    // Logic to get integrations will be implemented here
    return Integration::query();
  }

  /**
   * Create a new integration.
   */
  public function createIntegration(array $data)
  {
    $validator = Validator::make($data, [
      'name' => 'required|string|max:255',
      'type' => 'required|in:ping-post,post-only,offerwall',
      'is_active' => 'required|boolean',
      'company_id' => 'required|exists:companies,id',
      'request_mapping_config' => 'nullable|array',
      'environments' => 'required|array',
      'environments.*.env_type' => 'required|in:ping,post,offerwall',
      'environments.*.environment' => 'required|in:development,production',
      'environments.*.url' => 'required|string',
      'environments.*.response_config' => 'nullable|array',
      'payload_transformer' => 'nullable|string',
      'use_custom_transformer' => 'nullable|boolean',
    ]);

    if ($validator->fails()) {
      throw new IntegrationServiceException(
        $validator->errors()->first(),
        ['validation_errors' => $validator->errors()->toArray()]
      );
    }

    DB::transaction(function () use ($data) {
      $integration = Integration::create([
        'name' => $data['name'],
        'type' => $data['type'],
        'is_active' => $data['is_active'],
        'company_id' => $data['company_id'],
        'request_mapping_config' => $data['request_mapping_config'] ?? null,
        'payload_transformer' => $data['payload_transformer'] ?? null,
        'use_custom_transformer' => $data['use_custom_transformer'] ?? false,
      ]);

      foreach ($data['environments'] as $envData) {
        $env = $integration->environments()->create([
          'env_type' => $envData['env_type'],
          'environment' => $envData['environment'],
          'url' => $envData['url'],
          'method' => $envData['method'],
          'request_headers' => $this->convertHeadersToJson($envData['request_headers'] ?? []),
          'request_body' => $envData['request_body'] ?? null,
          'content_type' => $envData['content_type'] ?? 'application/json',
          'authentication_type' => $envData['authentication_type'] ?? 'none',
        ]);
        $config = $envData['response_config'] ?? null;

        $this->saveResponseConfig($env, $config);
      }

      return $integration;
    });
  }

  /**
   * Find an integration by its ID.
   */
  public function findIntegrationById(int $id)
  {
    // Logic to find an integration will be implemented here
    return Integration::findOrFail($id);
  }

  /**
   * Update an integration.
   */
  public function updateIntegration(Integration $integration, array $data)
  {
    $validator = Validator::make($data, [
      'name' => 'required|string|max:255',
      'type' => 'required|in:ping-post,post-only,offerwall',
      'is_active' => 'required|boolean',
      'company_id' => 'required|exists:companies,id',
      'request_mapping_config' => 'nullable|array',
      'environments' => 'required|array',
      'environments.*.env_type' => 'required|in:ping,post,offerwall',
      'environments.*.environment' => 'required|in:development,production',
      'environments.*.url' => 'required|string',
      'environments.*.response_config' => 'nullable|array',
      'payload_transformer' => 'nullable|string',
      'use_custom_transformer' => 'nullable|boolean',
    ]);

    if ($validator->fails()) {
      throw new IntegrationServiceException(
        $validator->errors()->first(),
        ['validation_errors' => $validator->errors()->toArray(), 'integration_id' => $integration->id]
      );
    }

    DB::transaction(function () use ($integration, $data) {
      $integration->update([
        'name' => $data['name'],
        'type' => $data['type'],
        'is_active' => $data['is_active'],
        'company_id' => $data['company_id'],
        'request_mapping_config' => $data['request_mapping_config'] ?? null,
        'payload_transformer' => $data['payload_transformer'] ?? null,
        'use_custom_transformer' => $data['use_custom_transformer'] ?? false,
      ]);

      foreach ($data['environments'] as $envData) {
        $env = $integration->environments()->updateOrCreate(
          ['environment' => $envData['environment'], 'env_type' => $envData['env_type']],
          [
            'url' => $envData['url'],
            'method' => $envData['method'],
            'request_headers' => $this->convertHeadersToJson($envData['request_headers'] ?? []),
            'request_body' => $envData['request_body'] ?? null,
            'content_type' => $envData['content_type'] ?? 'application/json',
            'authentication_type' => $envData['authentication_type'] ?? 'none',
          ]
        );
        $config = $envData['response_config'] ?? null;

        $this->saveResponseConfig($env, $config);
      }

      return $integration;
    });
  }

  /**
   * Delete an integration.
   */
  public function deleteIntegration(Integration $integration)
  {
    try {
      $integration->delete();
    } catch (\Throwable $th) {
      throw new IntegrationServiceException(
        'Failed to delete integration: ' . $th->getMessage(),
        ['integration_id' => $integration->id]
      );
    }
  }

  /**
   * Duplicate an integration.
   */
  public function duplicateIntegration(Integration $integration, ?string $newName = null)
  {
    $integration->load('environments.offerwallResponseConfig', 'environments.pingResponseConfig', 'environments.postResponseConfig');

    $data = [
      'name' => $newName ?? ($integration->name . ' (Copy)'),
      'type' => $integration->type,
      'is_active' => false, // Set to inactive by default for safety
      'company_id' => $integration->company_id,
      'request_mapping_config' => $integration->request_mapping_config,
      'payload_transformer' => $integration->payload_transformer,
      'use_custom_transformer' => $integration->use_custom_transformer,
      'environments' => [],
    ];

    foreach ($integration->environments as $env) {
      $headers = json_decode($env->request_headers, true) ?? [];
      $formattedHeaders = [];
      foreach ($headers as $key => $value) {
        $formattedHeaders[] = ['key' => $key, 'value' => $value];
      }

      $responseConfig = match ($env->env_type) {
        IntegrationEnvironment::ENV_TYPE_OFFERWALL => $env->offerwallResponseConfig ? [
          'offer_list_path' => $env->offerwallResponseConfig->offer_list_path,
          'mapping' => $env->offerwallResponseConfig->mapping,
          'fallbacks' => $env->offerwallResponseConfig->fallbacks,
        ] : null,
        IntegrationEnvironment::ENV_TYPE_PING => $env->pingResponseConfig ? [
          'bid_price_path' => $env->pingResponseConfig->bid_price_path,
          'accepted_path' => $env->pingResponseConfig->accepted_path,
          'accepted_value' => $env->pingResponseConfig->accepted_value,
          'lead_id_path' => $env->pingResponseConfig->lead_id_path,
        ] : null,
        IntegrationEnvironment::ENV_TYPE_POST => $env->postResponseConfig ? [
          'accepted_path' => $env->postResponseConfig->accepted_path,
          'accepted_value' => $env->postResponseConfig->accepted_value,
          'rejected_path' => $env->postResponseConfig->rejected_path,
        ] : null,
        default => null,
      };

      $data['environments'][] = [
        'env_type' => $env->env_type,
        'environment' => $env->environment,
        'url' => $env->url,
        'method' => $env->method,
        'request_headers' => $formattedHeaders,
        'request_body' => $env->request_body,
        'response_config' => $responseConfig,
        'content_type' => $env->content_type,
        'authentication_type' => $env->authentication_type,
      ];
    }

    return $this->createIntegration($data);
  }

  /**
   * Test an integration environment connection.
   */
  public function testIntegrationEnvironment(IntegrationEnvironment $environment)
  {
    $startTime = microtime(true);
    try {
      $headers = json_decode($environment->request_headers, true) ?? [];
      $body = json_decode($environment->request_body, true) ?? [];
      $response = Http::withHeaders($headers)
        ->{$environment->method}($environment->url, $body);
      $duration = round((microtime(true) - $startTime) * 1000);
      $body = $response->body();
      $result = json_decode($body, true) ?? $body;

      return [
        'status' => $response->status(),
        'duration' => $duration,
        'headers' => $response->headers(),
        'body' => $result,
      ];
    } catch (ConnectionException $e) {
      throw new IntegrationServiceException(
        'Connection failed: ' . $e->getMessage(),
        [
          'environment_id' => $environment->id,
          'integration_id' => $environment->integration_id,
          'url' => $environment->url,
          'method' => $environment->method,
        ]
      );
    } catch (\Throwable $th) {
      throw new IntegrationServiceException(
        'An unexpected error occurred: ' . $th->getMessage(),
        [
          'environment_id' => $environment->id,
          'integration_id' => $environment->integration_id,
          'url' => $environment->url,
          'method' => $environment->method,
          'file' => $th->getFile(),
          'line' => $th->getLine(),
        ]
      );
    }
  }

  /**
   * Convert an array of key-value pairs to a JSON string.
   *
   * @param array $headers
   * @return string
   */
  private function convertHeadersToJson(array $headers): string
  {
    $mappedHeaders = [];
    foreach ($headers as $header) {
      if (isset($header['key']) && isset($header['value'])) {
        $mappedHeaders[$header['key']] = $header['value'];
      }
    }

    return json_encode($mappedHeaders);
  }

  public function parseParams(array $leadData, string $template, array $mappingConfig): string
  {
    if (empty($template)) {
      return '';
    }

    $replacementsResult = \App\Services\PayloadProcessorService::generateReplacements($leadData, $mappingConfig);
    $finalReplacements = $replacementsResult['finalReplacements'];

    if (empty($finalReplacements)) {
      return $template;
    }

    $processor = new \App\Services\PayloadProcessorService;
    // $oldData =  str_replace(array_keys($replacements), array_values($replacements), $template);// (DEPRECATED)
    $processedTemplate = $processor->process($template, $finalReplacements);

    return $processedTemplate;
  }

  /**
   * Parsea la respuesta de una integración de Offerwall para extraer las ofertas normalizadas.
   *
   * @param  array  $jsonResponse  Respuesta decodificada (array) de la API externa
   * @param  Integration  $integration  Modelo de integración con su configuración de parseo
   * @return array Lista de ofertas normalizadas
   */
  public function parseOfferwallResponse(array $jsonResponse, Integration $integration, ?IntegrationEnvironment $environment = null): array
  {
    TailLogger::saveLog(
      "Parsing offerwall response for Integration ID: {$integration->id}",
      'integrations/parsing',
      'info',
      ['integration_id' => $integration->id]
    );

    $env = $environment ?? $integration->environments
      ->where('env_type', 'offerwall')
      ->where('environment', 'production')
      ->first();
    $parserConfig = $env?->response_config;
    $pathOfOffers = $parserConfig?->offer_list_path ?? '';
    $offers = data_get($jsonResponse, $pathOfOffers);
    TailLogger::saveLog(
      "Offers found at path: {$pathOfOffers}",
      'integrations/parsing',
      'info',
      [
        'raw_response' => $jsonResponse,
        'integration_id' => $integration->id,
        'path_of_offers' => $pathOfOffers,
        'number_of_offers' => $offers ? count($offers) : 0,
      ]
    );
    if (!is_array($offers)) {
      TailLogger::saveLog(
        "Offers not found or not an array at path: {$pathOfOffers}",
        'integrations/parsing',
        'warning',
        [
          'integration_id' => $integration->id,
          'path_of_offers' => $pathOfOffers,
          'number_of_offers' => $offers ? count($offers) : 0,
        ]
      );

      return [];
    }

    $mapping = $parserConfig?->mapping ?? [];
    $mappedOffers = [];
    foreach ($offers as $offer) {
      $mappedOffer = [];
      foreach ($mapping as $key => $valuePath) {
        $mappedOffer[$key] = !empty($valuePath) ? data_get($offer, $valuePath) : null;
      }
      // Add integration id
      $mappedOffer['integration_id'] = $integration->id;
      $mappedOffers[] = $mappedOffer;
    }

    TailLogger::saveLog(
      'Parsed ' . count($mappedOffers) . ' offers.',
      'integrations/parsing',
      'info',
      [
        'integration_id' => $integration->id,
        'offers_count' => count($mappedOffers),
      ]
    );

    return $mappedOffers;
  }

  /**
   * Apply fallback values for title and description on parsed offerwall offers.
   *
   * When an API returns a mapped key with null or empty string value,
   * the configured fallback replaces it so offers always have displayable content.
   *
   * @param  array<int, array<string, mixed>>  $mappedOffers  Offers already processed by parseOfferwallResponse
   * @param  Integration  $integration  Integration model containing the fallback config
   * @return array<int, array<string, mixed>>
   */
  public function applyOfferFallbacks(array $mappedOffers, Integration $integration, ?IntegrationEnvironment $environment = null): array
  {
    $env = $environment ?? $integration->environments
      ->where('env_type', 'offerwall')
      ->where('environment', 'production')
      ->first();
    $fallbacks = $env?->response_config?->fallbacks ?? [];

    if (empty($fallbacks)) {
      return $mappedOffers;
    }

    $fallbackKeys = ['title', 'description'];

    foreach ($mappedOffers as &$offer) {
      foreach ($fallbackKeys as $key) {
        if (isset($fallbacks[$key]) && $fallbacks[$key] !== '' && empty($offer[$key])) {
          $offer[$key] = $fallbacks[$key];
        }
      }
    }
    unset($offer);

    return $mappedOffers;
  }

  /**
   * Save or update the typed response config for an environment.
   *
   * Routes to the correct table based on `env_type` and uses
   * `updateOrCreate` to handle both create and update flows.
   */
  private function saveResponseConfig(IntegrationEnvironment $env, ?array $configData): void
  {
    if (empty($configData)) {
      return;
    }

    match ($env->env_type) {
      IntegrationEnvironment::ENV_TYPE_OFFERWALL => OfferwallResponseConfig::updateOrCreate(
        ['integration_environment_id' => $env->id],
        [
          'offer_list_path' => $configData['offer_list_path'] ?? null,
          'mapping' => $configData['mapping'] ?? null,
          'fallbacks' => $configData['fallbacks'] ?? null,
        ]
      ),
      IntegrationEnvironment::ENV_TYPE_PING => PingResponseConfig::updateOrCreate(
        ['integration_environment_id' => $env->id],
        [
          'bid_price_path' => $configData['bid_price_path'] ?? null,
          'accepted_path' => $configData['accepted_path'] ?? null,
          'accepted_value' => $configData['accepted_value'] ?? null,
          'lead_id_path' => $configData['lead_id_path'] ?? null,
        ]
      ),
      IntegrationEnvironment::ENV_TYPE_POST => PostResponseConfig::updateOrCreate(
        ['integration_environment_id' => $env->id],
        [
          'accepted_path' => $configData['accepted_path'] ?? null,
          'accepted_value' => $configData['accepted_value'] ?? null,
          'rejected_path' => $configData['rejected_path'] ?? null,
        ]
      ),
      default => null,
    };
  }
}

/**
 * Custom Exception for IntegrationService with automatic logging.
 */
class IntegrationServiceException extends Exception
{
  protected $context = [];

  public function __construct(string $message, array $context = [], int $code = 0, ?\Throwable $previous = null)
  {
    parent::__construct($message, $code, $previous);
    TailLogger::saveLog(
      $message,
      'integrations/services',
      'error',
      array_merge(['error' => $message], $context)
    );
  }

  public function getContext(): array
  {
    return $this->context;
  }
}
