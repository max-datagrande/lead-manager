<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Integration;
use App\Models\IntegrationEnvironment;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Http;

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
      dd($data);
      // Ensure we have a plain, numerically-indexed array for the 'insert' method.
      $integrationsToInsert = is_array($data['integrations']) ? array_values($data['integrations']) : [];
      $environmentsToInsert = is_array($data['environments']) ? array_values($data['environments']) : [];

      if (empty($integrationsToInsert)) {
        return response()->json(['message' => 'No integrations to import from production.']);
      }

      // Using PostgreSQL syntax from project context
      //Truncate table
      Integration::truncate();

      return response()->json(['message' => 'Integrations synchronized successfully from production.']);
    } catch (\Throwable $th) {
      return response()->json(['error' => 'An unexpected error occurred: ' . $th->getMessage()], 500);
    }
  }
}
