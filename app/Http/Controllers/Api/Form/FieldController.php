<?php

namespace App\Http\Controllers\Api\Form;

use App\Http\Controllers\Controller;
use App\Models\Field;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class FieldController extends Controller
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

    return Field::all();
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
    $endpoint = route('api.fields.export');
    // Replace local host for production
    $endpoint = str_replace($localUrl, $productionUrl, $endpoint);
    try {
      $response = Http::get($endpoint);
      if ($response->failed()) {
        return response()->json([
          'error' => 'Failed to fetch fields from production.',
          'details' => $response->body()
        ], $response->status());
      }

      $data = $response->json();

      // Ensure we have a plain, numerically-indexed array for the 'insert' method.
      // This converts an object like {"0":{...}, "1":{...}} into a proper array [{...}, {...}]
      $fieldsToInsert = is_array($data) ? array_values($data) : [];

      if (empty($fieldsToInsert)) {
        return response()->json(['message' => 'No fields to import from production.']);
      }

      // Pre-process fields to handle array-to-JSON conversion for 'possible_values'
      $processedFields = array_map(function ($field) {
        if (isset($field['possible_values']) && is_array($field['possible_values'])) {
          $field['possible_values'] = json_encode($field['possible_values']);
        }
        // Ensure 'created_at' and 'updated_at' are present, otherwise DB might complain
        $field['created_at'] = $field['created_at'] ?? now();
        $field['updated_at'] = $field['updated_at'] ?? now();
        return $field;
      }, $fieldsToInsert);

      // Using PostgreSQL syntax from project context
      DB::statement('TRUNCATE TABLE fields RESTART IDENTITY CASCADE');

      Field::insert($processedFields);

      return response()->json(['message' => 'Fields synchronized successfully from production.']);
    } catch (\Throwable $th) {
      return response()->json(['error' => 'An unexpected error occurred: ' . $th->getMessage()], 500);
    }
  }
}
