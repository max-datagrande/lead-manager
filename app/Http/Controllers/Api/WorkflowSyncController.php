<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Workflow;
use App\Models\WorkflowBuyer;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class WorkflowSyncController extends Controller
{
  /**
   * Export all workflows with their buyer assignments.
   * Only available in production.
   */
  public function export()
  {
    if (!App::isProduction()) {
      return response()->json(['error' => 'This action is only available in the production environment.'], 403);
    }

    return [
      'workflows' => Workflow::all(),
      'workflow_buyers' => WorkflowBuyer::all(),
    ];
  }

  /**
   * Import all workflows from production.
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

    $endpoint = route('api.workflows.export');
    $productionEndpoint = str_replace($localUrl, $productionUrl, $endpoint);

    try {
      $response = Http::get($productionEndpoint);
      if ($response->failed()) {
        return response()->json([
          'error' => 'Failed to fetch workflows from production.',
          'details' => $response->body(),
        ], $response->status());
      }

      $data = $response->json();
      $workflowsData = array_values($data['workflows'] ?? []);
      $wfBuyersData = array_values($data['workflow_buyers'] ?? []);

      if (empty($workflowsData)) {
        return response()->json(['message' => 'No workflows to import from production.']);
      }

      DB::beginTransaction();
      try {
        WorkflowBuyer::truncate();
        Workflow::truncate();

        foreach ($workflowsData as $workflow) {
          $model = new Workflow();
          $model->fill($workflow);
          if (isset($workflow['id'])) {
            $model->id = $workflow['id'];
          }
          $model->user_id = Auth::id();
          $model->save();
        }

        foreach ($wfBuyersData as $wfBuyer) {
          $model = new WorkflowBuyer();
          $model->fill($wfBuyer);
          if (isset($wfBuyer['id'])) {
            $model->id = $wfBuyer['id'];
          }
          $model->save();
        }

        DB::commit();

        return response()->json([
          'message' => 'Workflows synchronized successfully from production.',
          'counts' => [
            'workflows' => count($workflowsData),
            'workflow_buyers' => count($wfBuyersData),
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
