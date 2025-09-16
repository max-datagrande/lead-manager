<?php

namespace App\Http\Controllers;

use App\Models\Integration;
use App\Models\IntegrationEnvironment;
use App\Services\IntegrationService;
use App\Services\IntegrationServiceException;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Maxidev\Logger\TailLogger;

class IntegrationController extends Controller
{
  protected $integrationService;

  public function __construct(IntegrationService $integrationService)
  {
    $this->integrationService = $integrationService;
  }

  /**
   * Display a listing of the resource.
   */
  public function index(Request $request)
  {
    $sort = $request->get('sort', 'created_at:desc');
    [$col, $dir] = get_sort_data($sort);
    $data = $request->all();
    $integrations = $this->integrationService->getIntegrations($data);
    $entries = $integrations->orderBy($col, $dir)
      ->get();
    return Inertia::render('integrations/index', [
      'rows' => $entries,
      'filters' => [
        'sort' => $data['sort'] ?? "created_at:desc",
      ],
    ]);
  }

  /**
   * Show the form for creating a new resource.
   */
  public function create()
  {
    return Inertia::render('integrations/create');
  }

  /**
   * Store a newly created resource in storage.
   */
  public function store(Request $request)
  {
    try {
      $this->integrationService->createIntegration($request->all());
      add_flash_message('success', 'Integration created successfully.');
      return redirect()->route('integrations.index');
    } catch (IntegrationServiceException $e) {
      TailLogger::saveLog('Failed to create integration', 'integrations/services', 'errors', ['error' => $e->getMessage()]);
      add_flash_message('error', $e->getMessage());
      return back();
    }
  }

  /**
   * Display the specified resource.
   */
  public function show(Integration $integration)
  {
    return Inertia::render('integrations/show', [
      'integration' => $integration->load('environments'),
    ]);
  }

  /**
   * Show the form for editing the specified resource.
   */
  public function edit(Integration $integration)
  {
    return Inertia::render('integrations/edit', [
      'integration' => $integration->load('environments'),
    ]);
  }

  /**
   * Update the specified resource in storage.
   */
  public function update(Request $request, Integration $integration)
  {
    try {
      $this->integrationService->updateIntegration($integration, $request->all());
      add_flash_message('success', 'Integration updated successfully.');
      return back();
    } catch (IntegrationServiceException $e) {
      TailLogger::saveLog('Failed to update integration', 'integrations/services', 'errors', ['integration_id' => $integration->id, 'error' => $e->getMessage()]);
      add_flash_message('error', $e->getMessage());
      return back();
    }
  }

  /**
   * Remove the specified resource from storage.
   */
  public function destroy(Integration $integration)
  {
    try {
      $this->integrationService->deleteIntegration($integration);
      add_flash_message('success', 'Integration deleted successfully.');
      return redirect()->route('integrations.index');
    } catch (IntegrationServiceException $e) {
      TailLogger::saveLog('Failed to delete integration', 'integrations/services', 'errors', ['integration_id' => $integration->id, 'error' => $e->getMessage()]);
      add_flash_message('error', $e->getMessage());
      return back();
    }
  }

  /**
   * Test an integration environment connection.
   */
  public function test(Integration $integration, IntegrationEnvironment $environment)
  {
    try {
      $result = $this->integrationService->testIntegrationEnvironment($environment);
      return response()->json($result);
    } catch (IntegrationServiceException $e) {
      TailLogger::saveLog('Failed to test integration', 'integrations/services', 'errors', ['integration_id' => $integration->id, 'environment_id' => $environment->id, 'error' => $e->getMessage()]);
      return response()->json(['error' => $e->getMessage()], 500);
    }
  }
}
