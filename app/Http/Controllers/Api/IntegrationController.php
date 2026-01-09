<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Integration;
use App\Models\IntegrationEnvironment;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;

class IntegrationController extends Controller
{
  /**
   * Export all fields for synchronization.
   * This action is only available in the production environment.
   */
  public function export()
  {
    if (!App::isProduction()) {
      return response()->json(['error' => 'This action is only available in the production environment.'], 403);
    }

    return [
      'integrations' => Integration::all(),
      'environments' => IntegrationEnvironment::all(),
    ];
  }

  /**
   * Import all fields from production.
   * This action is only available in the local environment.
   */
  public function import()
  {
    if (!App::isLocal()) {
      return response()->json(['error' => 'This action is only available in the local environment.'], 403);
    }

    $app = config('app');
    $localUrl = $app['url'];
    $productionUrl = $app['production_url'];

    if (!$productionUrl) {
      return response()->json(['error' => 'Production URL is not configured in .env file (APP_API_PRODUCTION_URL).'], 500);
    }
    $endpoint = route('api.integrations.export');
    // Replace local host for production
    $productionEndpoint = str_replace($localUrl, $productionUrl, $endpoint);

    try {
      $response = Http::get($productionEndpoint);
      if ($response->failed()) {
        return response()->json([
          'error' => 'Failed to fetch integrations from production.',
          'details' => $response->body()
        ], $response->status());
      }

      $data = $response->json();
      // Ensure we have a plain, numerically-indexed array for the 'insert' method.
      $integrationsToInsert = is_array($data['integrations']) ? array_values($data['integrations']) : [];
      $environmentsToInsert = is_array($data['environments']) ? array_values($data['environments']) : [];

      if (empty($integrationsToInsert)) {
        return response()->json(['message' => 'No integrations to import from production.']);
      }
      Integration::truncate();
      $migrations = [];
      $errors = 0;
      foreach ($integrationsToInsert as $integration) {
        try {
          DB::beginTransaction();
          //Insert integration
          $newIntegration = new Integration();
          $newIntegration->fill($integration);
          if (isset($integration['id'])) {
            $newIntegration->id = $integration['id'];
          }
          $newIntegration->save();
          $environments = collect($environmentsToInsert)
            ->filter(fn($env) => $env['integration_id'] == $newIntegration->id);
          //Insert environments
          foreach ($environments as $environment) {
            $data = [
              ...$environment,
              'integration_id' => $newIntegration->id,
            ];
            IntegrationEnvironment::create($data);
          }
          DB::commit();
          $migrations[] = [
            'integration' => $newIntegration->id,
            'status' => 'success',
          ];
        } catch (\Throwable $th) {
          $migrations[] = [
            'integration' => $integration['id'] ?? null,
            'status' => 'error',
            'message' => $th->getMessage(),
          ];
          DB::rollBack();
          $errors++;
        }
      }
      $statusMessage = $errors > 0
        ? 'Integrations synchronized successfully from production with errors.'
        : 'Integrations synchronized successfully from production.';

      $response = [
        'message' => $statusMessage,
        'migrations' => $migrations,
        'errors' => $errors,
      ];
      return response()->json($response);
    } catch (\Throwable $th) {
      return response()->json(['error' => 'An unexpected error occurred: ' . $th->getMessage(), 'file' => $th->getFile(), 'line' => $th->getLine()], 500);
    }
  }
}
