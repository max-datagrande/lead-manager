<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Buyer;
use App\Models\BuyerCapRule;
use App\Models\BuyerConfig;
use App\Models\BuyerEligibilityRule;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class BuyerSyncController extends Controller
{
  /**
   * Export all buyers with their configs, eligibility rules and cap rules.
   * Only available in production.
   */
  public function export()
  {
    if (!App::isProduction()) {
      return response()->json(['error' => 'This action is only available in the production environment.'], 403);
    }

    return [
      'buyers' => Buyer::all(),
      'buyer_configs' => BuyerConfig::all(),
      'buyer_eligibility_rules' => BuyerEligibilityRule::all(),
      'buyer_cap_rules' => BuyerCapRule::all(),
    ];
  }

  /**
   * Import all buyers from production.
   * Only available in local.
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

    $endpoint = route('api.buyers.export');
    $productionEndpoint = str_replace($localUrl, $productionUrl, $endpoint);

    try {
      $response = Http::get($productionEndpoint);
      if ($response->failed()) {
        return response()->json([
          'error' => 'Failed to fetch buyers from production.',
          'details' => $response->body(),
        ], $response->status());
      }

      $data = $response->json();
      $buyersData = array_values($data['buyers'] ?? []);
      $configsData = array_values($data['buyer_configs'] ?? []);
      $eligibilityData = array_values($data['buyer_eligibility_rules'] ?? []);
      $capsData = array_values($data['buyer_cap_rules'] ?? []);

      if (empty($buyersData)) {
        return response()->json(['message' => 'No buyers to import from production.']);
      }

      DB::beginTransaction();
      try {
        BuyerCapRule::truncate();
        BuyerEligibilityRule::truncate();
        BuyerConfig::truncate();
        Buyer::truncate();

        foreach ($buyersData as $buyer) {
          $model = new Buyer();
          $model->fill($buyer);
          if (isset($buyer['id'])) {
            $model->id = $buyer['id'];
          }
          $model->save();
        }

        foreach ($configsData as $config) {
          $model = new BuyerConfig();
          $model->fill($config);
          if (isset($config['id'])) {
            $model->id = $config['id'];
          }
          $model->save();
        }

        foreach ($eligibilityData as $rule) {
          BuyerEligibilityRule::create($rule);
        }

        foreach ($capsData as $cap) {
          BuyerCapRule::create($cap);
        }

        DB::commit();

        return response()->json([
          'message' => 'Buyers synchronized successfully from production.',
          'counts' => [
            'buyers' => count($buyersData),
            'configs' => count($configsData),
            'eligibility_rules' => count($eligibilityData),
            'cap_rules' => count($capsData),
          ],
        ]);
      } catch (\Throwable $th) {
        DB::rollBack();
        return response()->json(['error' => 'Import failed: ' . $th->getMessage()], 500);
      }
    } catch (\Throwable $th) {
      return response()->json(['error' => 'An unexpected error occurred: ' . $th->getMessage()], 500);
    }
  }
}
