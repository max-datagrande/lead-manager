<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Auth;

class CompanyController extends Controller
{
  /**
   * Export all companies for synchronization.
   * This action is only available in the production environment.
   */
  public function export()
  {
    if (!App::isProduction()) {
      return response()->json(['error' => 'This action is only available in the production environment.'], 403);
    }

    return Company::all();
  }

  /**
   * Import all companies from production.
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

    $endpoint = route('api.companies.export');
    $productionEndpoint = str_replace($localUrl, $productionUrl, $endpoint);

    try {
      $response = Http::get($productionEndpoint);

      if ($response->failed()) {
        return response()->json([
          'error' => 'Failed to fetch companies from production.',
          'details' => $response->body()
        ], $response->status());
      }

      $data = $response->json();
      $companiesToInsert = is_array($data) ? array_values($data) : [];

      if (empty($companiesToInsert)) {
        return response()->json(['message' => 'No companies to import from production.']);
      }
      $userId = Auth::id() ?? User::where('role', 'admin')->first()?->id;

      $processedCompanies = array_map(function ($company) use ($userId) {
        $company['user_id'] = $userId;
        $company['updated_user_id'] = null;
        $company['created_at'] = $company['created_at'] ?? now();
        $company['updated_at'] = $company['updated_at'] ?? now();
        return $company;
      }, $companiesToInsert);

      Company::truncate();
      Company::insert($processedCompanies);

      return response()->json(['message' => 'Companies synchronized successfully from production.']);
    } catch (\Throwable $th) {
      return response()->json(['error' => 'An unexpected error occurred: ' . $th->getMessage()], 500);
    }
  }
}
