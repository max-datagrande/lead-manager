<?php

namespace App\Http\Controllers\Offerwall;

use App\Http\Controllers\Controller;
use App\Http\Requests\OfferwallTesterExecuteRequest;
use App\Http\Requests\OfferwallTesterRequest;
use App\Models\Integration;
use App\Services\Offerwall\TesterService;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class TesterController extends Controller
{
  public function __construct(private TesterService $testerService) {}

  public function index(): Response
  {
    $integrations = Integration::query()
      ->activeOfferwalls()
      ->with('company:id,name')
      ->get(['id', 'name', 'company_id']);

    $request = request();
    $isDevMode = env('APP_ENV') === 'local';
    return Inertia::render('offerwall/tester/index', [
      'integrations' => $integrations,
      'clientIp' => $isDevMode ? config('app.fake_ip') : $request->ip(),
      'deviceType' => strtolower(get_device_type($request->userAgent() ?? '')),
    ]);
  }

  public function getFields(Integration $integration): JsonResponse
  {
    $data = $this->testerService->getIntegrationFields($integration);

    return response()->json($data);
  }

  /**
   * Prepare test context (synthetic data + mix log) for sequential per-cptype execution.
   */
  public function prepare(OfferwallTesterRequest $request): JsonResponse
  {
    try {
      $context = $this->testerService->prepareTest(
        integrationId: $request->validated('integration_id'),
        fieldValues: $request->validated('field_values'),
        user: $request->user(),
      );

      return response()->json([
        'success' => true,
        'message' => 'Test prepared',
        'data' => $context,
      ]);
    } catch (Throwable $e) {
      return response()->json(
        [
          'success' => false,
          'message' => $e->getMessage(),
          'data' => null,
        ],
        422,
      );
    }
  }

  /**
   * Execute a single cptype test call.
   */
  public function execute(OfferwallTesterExecuteRequest $request): JsonResponse
  {
    try {
      $result = $this->testerService->executeSingleCptype(
        integrationId: $request->validated('integration_id'),
        mixLogId: $request->validated('mix_log_id'),
        leadId: $request->validated('lead_id'),
        cptype: $request->validated('cptype'),
      );

      return response()->json($result);
    } catch (Throwable $e) {
      return response()->json(
        [
          'success' => false,
          'message' => $e->getMessage(),
          'data' => null,
        ],
        500,
      );
    }
  }
}
