<?php

namespace App\Services;

use App\Models\Integration;
use App\Models\IntegrationEnvironment;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Http;
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
      'environments' => 'required|array|size:2',
      'environments.development' => 'required|array',
      'environments.development.url' => 'required|url',
      'environments.production.url' => 'required|url',
    ]);

    if ($validator->fails()) {
      throw new IntegrationServiceException($validator->errors()->first());
    }

    DB::transaction(function () use ($data) {
      $integration = Integration::create([
        'name' => $data['name'],
        'type' => $data['type'],
        'is_active' => $data['is_active'],
        'company_id' => $data['company_id'],
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
      'environments' => 'required|array|size:2',
      'environments.development' => 'required|array',
      'environments.development.url' => 'required|url',
      'environments.production.url' => 'required|url',
    ]);

    if ($validator->fails()) {
      throw new IntegrationServiceException($validator->errors()->first());
    }

    DB::transaction(function () use ($integration, $data) {
      $integration->update([
        'name' => $data['name'],
        'type' => $data['type'],
        'is_active' => $data['is_active'],
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
    // Logic to delete an integration will be implemented here
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

      return [
        'status' => $response->status(),
        'duration' => $duration,
        'headers' => $response->headers(),
        'body' => $response->body(),
      ];
    } catch (ConnectionException $e) {
      throw new IntegrationServiceException('Connection failed: ' . $e->getMessage());
    } catch (\Throwable $th) {
      throw new IntegrationServiceException('An unexpected error occurred: ' . $th->getMessage());
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
}

/**
 * Custom Exception for IntegrationService.
 */
class IntegrationServiceException extends Exception {}
