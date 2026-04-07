<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\ResetsSequences;
use App\Models\User;
use App\Models\Vertical;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Auth;

class VerticalController extends Controller
{
  use ResetsSequences;
  /**
   * Export all verticals for synchronization.
   * This action is only available in the production environment.
   */
  public function export()
  {
    if (!App::isProduction()) {
      return response()->json(['error' => 'This action is only available in the production environment.'], 403);
    }

    return Vertical::all();
  }

  /**
   * Import all verticals from production.
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

    $endpoint = route('api.verticals.export');
    $productionEndpoint = str_replace($localUrl, $productionUrl, $endpoint);

    try {
      $response = Http::get($productionEndpoint);

      if ($response->failed()) {
        return response()->json(
          [
            'error' => 'Failed to fetch verticals from production.',
            'details' => $response->body(),
          ],
          $response->status(),
        );
      }

      $data = $response->json();
      $verticalsToInsert = is_array($data) ? array_values($data) : [];

      if (empty($verticalsToInsert)) {
        return response()->json(['message' => 'No verticals to import from production.']);
      }
      $userId = User::where('role', 'admin')->first()->id;

      $processedVerticals = array_map(function ($vertical) use ($userId) {
        $vertical['user_id'] = $userId;
        $vertical['updated_user_id'] = null;
        $vertical['created_at'] = $vertical['created_at'] ?? now();
        $vertical['updated_at'] = $vertical['updated_at'] ?? now();
        return $vertical;
      }, $verticalsToInsert);

      Vertical::truncate();
      Vertical::insert($processedVerticals);

      $this->resetSequence('verticals');

      return response()->json(['message' => 'Verticals synchronized successfully from production.']);
    } catch (\Throwable $th) {
      return response()->json(['error' => 'An unexpected error occurred: ' . $th->getMessage()], 500);
    }
  }
}
