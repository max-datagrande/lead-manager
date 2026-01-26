<?php

namespace App\Http\Controllers;

use App\Models\Field;
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
    $entries = $integrations->with('company')
      ->orderBy($col, $dir)
      ->get();
    return Inertia::render('integrations/index', [
      'rows' => $entries,
      'state' => [
        'sort' => $sort,
        'filters' => []
      ]
    ]);
  }

  /**
   * Show the form for creating a new resource.
   */
  public function create()
  {
    return Inertia::render('integrations/create', [
      'companies' => \App\Models\Company::all()->map(fn($company) => ['value' => $company->id, 'label' => $company->name]),
      'fields' => Field::all(['id', 'name', 'possible_values']),
    ]);
  }

  /**
   * Store a newly created resource in storage.
   */
  public function store(Request $request)
  {
    try {
      $data = $request->all();
      $this->integrationService->createIntegration($data);
      add_flash_message(type: "success", message: "Integration created successfully.");
      return redirect()->route('integrations.index');
    } catch (IntegrationServiceException $e) {
      add_flash_message(type: "error", message: $e->getMessage());
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
      'companies' => \App\Models\Company::all()->map(fn($company) => ['value' => $company->id, 'label' => $company->name]),
      'fields' => Field::all(['id', 'name', 'possible_values']),
    ]);
  }

  /**
   * Update the specified resource in storage.
   */
  public function update(Request $request, Integration $integration)
  {
    try {
      $data = $request->all();
      $this->integrationService->updateIntegration($integration, $data);
      add_flash_message(type: "success", message: "Integration updated successfully.");
      return back();
    } catch (IntegrationServiceException $e) {
      add_flash_message(type: "error", message: $e->getMessage());
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
      add_flash_message(type: "success", message: "Integration deleted successfully.");
      return redirect()->route('integrations.index');
    } catch (IntegrationServiceException $e) {
      add_flash_message(type: "error", message: $e->getMessage());
      return back();
    }
  }

  /**
   * Duplicate the specified resource.
   */
  public function duplicate(Request $request, Integration $integration)
  {
    try {
      $newName = $request->input('name');
      $this->integrationService->duplicateIntegration($integration, $newName);
      add_flash_message(type: "success", message: "Integration duplicated successfully.");
      return redirect()->route('integrations.index');
    } catch (IntegrationServiceException $e) {
      add_flash_message(type: "error", message: $e->getMessage());
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
      return response()->json(['error' => $e->getMessage(), 'details' => $e->getContext()], 500);
    } catch (\Throwable $e) {
      return $this->handleUnexpectedException($e, $integration, $environment);
    }
  }



  /**
   * Handle unexpected exceptions with detailed logging and response
   */
  private function handleUnexpectedException(
    \Throwable $e,
    Integration $integration,
    IntegrationEnvironment $environment
  ) {
    TailLogger::saveLog(
      'Unexpected error testing integration',
      'integrations/services',
      'errors',
      [
        'integration_id' => $integration->id,
        'environment_id' => $environment->id,
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString()
      ]
    );

    return response()->json([
      'error' => 'Unexpected error when testing integration: ' . $e->getMessage(),
      'file' => $e->getFile(),
      'line' => $e->getLine(),
    ], 500);
  }
}
