<?php

namespace App\Services;

use App\Models\Integration;
use App\Models\IntegrationEnvironment;
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
      'response_parser_config' => 'required_if:type,offerwall|array',
      'request_mapping_config' => 'nullable|array',
      'environments' => 'required|array|size:2',
      'environments.development.url' => 'required|url',
      'environments.production.url' => 'required|url',
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
        'response_parser_config' => $data['response_parser_config'] ?? null,
        'request_mapping_config' => $data['request_mapping_config'] ?? null,
      ]);

      foreach ($data['environments'] as $envName => $envData) {
        $integration->environments()->create([
          'environment' => $envName,
          'url' => $envData['url'],
          'method' => $envData['method'],
          'request_headers' => $this->convertHeadersToJson($envData['request_headers']),
          'request_body' => $envData['request_body'],
          'content_type' => $envData['content_type'] ?? 'application/json',
          'authentication_type' => $envData['authentication_type'] ?? 'none',
        ]);
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
      'response_parser_config' => 'required_if:type,offerwall|array',
      'request_mapping_config' => 'nullable|array',
      'environments' => 'required|array|size:2',
      'environments.development.url' => 'required|url',
      'environments.production.url' => 'required|url',
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
        'response_parser_config' => $data['response_parser_config'] ?? null,
        'request_mapping_config' => $data['request_mapping_config'] ?? null,
      ]);

      foreach ($data['environments'] as $envName => $envData) {
        $integration->environments()->updateOrCreate(
          ['environment' => $envName], // Condiciones para buscar
          [ // Datos para actualizar o crear
            'url' => $envData['url'],
            'method' => $envData['method'],
            'request_headers' => $this->convertHeadersToJson($envData['request_headers']),
            'request_body' => $envData['request_body'],
            'content_type' => $envData['content_type'] ?? 'application/json',
            'authentication_type' => $envData['authentication_type'] ?? 'none',
          ]
        );
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
          'method' => $environment->method
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
          'line' => $th->getLine()
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

    $replacements = [];
    foreach ($mappingConfig as $tokenName => $config) {
      $value = $leadData[$tokenName] ?? $config['defaultValue'] ?? '';

      if (isset($config['value_mapping']) && array_key_exists($value, $config['value_mapping'])) {
        $value = $config['value_mapping'][$value];
      }

      if (is_array($value) || is_object($value)) {
        $value = json_encode($value);
      }

      $replacements[$tokenName] = (string) $value;
    }

    if (empty($replacements)) {
      return $template;
    }
    $processor = new \App\Services\PayloadProcessorService();
    //$oldData =  str_replace(array_keys($replacements), array_values($replacements), $template);// (DEPRECATED)

    $processedTemplate = $processor->process($template, $replacements);
    return $processedTemplate;
  }

  /**
   * Parsea la respuesta de una integración de Offerwall para extraer las ofertas normalizadas.
   *
   * @param array $jsonResponse Respuesta decodificada (array) de la API externa
   * @param Integration $integration Modelo de integración con su configuración de parseo
   * @return array Lista de ofertas normalizadas
   */
  public function parseOfferwallResponse(array $jsonResponse, Integration $integration): array
  {
    $parserConfig = $integration->response_parser_config;
    $pathOfOffers = $parserConfig['offer_list_path'] ?? '';
    $offers = data_get($jsonResponse, $pathOfOffers);

    if (!is_array($offers)) {
      return [];
    }

    $mappedOffers = [];
    foreach ($offers as $offer) {
      $mappedOffer = [];
      foreach ($parserConfig['mapping'] as $key => $valuePath) {
        $mappedOffer[$key] = !empty($valuePath) ? data_get($offer, $valuePath) : null;
      }
      //Add integration id
      $mappedOffer['integration_id'] = $integration->id;
      $mappedOffers[] = $mappedOffer;
    }

    return $mappedOffers;
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
