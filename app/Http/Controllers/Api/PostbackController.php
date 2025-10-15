<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\FirePostbackRequest;
use App\Http\Requests\SearchPayoutRequest;
use App\Http\Requests\ReconcilePayoutsRequest;
use App\Enums\PostbackVendor;
use App\Models\Postback;
use App\Services\NaturalIntelligenceService;
use App\Services\PostbackService;
// use App\Jobs\ProcessPostbackJob;
use Illuminate\Http\JsonResponse;
use Maxidev\Logger\TailLogger;
use Illuminate\Http\Request;

/**
 * Controller para manejar postbacks a Natural Intelligence desde landing pages
 */
class PostbackController extends Controller
{
  public array $vendorServices = [];
  public function __construct(protected NaturalIntelligenceService $niService, protected PostbackService $postbackService)
  {
    $this->vendorServices = [
      PostbackVendor::NI->value() =>  $niService
    ];
  }
  public function reconcilePayouts(ReconcilePayoutsRequest $request): JsonResponse
  {
    $validated = $request->validated();
    $result = $this->postbackService->reconcileDailyPayouts($validated['date']);
    $wasSuccessful = $result['success'] ?? false;
    if (!$wasSuccessful) {
      return response()->json([
        'success' => false,
        'message' => $result['message'] ?? 'An unknown error occurred during reconciliation.',
      ], 500);
    }

    return response()->json([
      'success' => true,
      'message' => "Daily payouts reconciliation finished successfully. Total conversions: {$result['total_conversions']}, created: {$result['created']}, updated: {$result['updated']}, processed: {$result['processed']}.",
      'data' => [
        'date' => $validated['date'],
        'created' => $result['created'],
        'processed' => $result['processed'],
        'total_conversions' => $result['total_conversions'],
        'updated' => $result['updated'],
      ],
    ]);
  }

  public function getCurrentVendorServices(string $vendor)
  {
    return $this->vendorServices[$vendor] ?? null;
  }

  /**
   * Endpoint fire para recibir postbacks de vendors específicos
   *
   * @param FirePostbackRequest $request
   * @return JsonResponse
   */
  public function store(FirePostbackRequest $request): JsonResponse
  {
    $validated = $request->validated();
    $vendor = $validated['vendor'];
    $offerId = $validated['offer_id'];

    // Validar vendor
    $vendorKeys = array_keys($this->vendorServices);
    if (!in_array($vendor, $vendorKeys)) {
      return response()->json([
        'success' => false,
        'message' => 'Vendor not supported',
      ], 422);
    }

    $offers = collect(config('offers.maxconv'));
    $offer = $offers->where('offer_id', $offerId)->first() ?? null;
    if (!$offer) {
      return response()->json([
        'success' => false,
        'message' => 'Offer not found',
      ], 422);
    }
    return $this->postbackService->queueForProcessing($validated);
  }

  /**
   * Endpoint para obtener el estado de un postback
   */
  public function status(Request $request, int $postbackId): JsonResponse
  {
    $postback = Postback::find($postbackId);

    if (!$postback) {
      return response()->json([
        'success' => false,
        'message' => 'Postback not found'
      ], 404);
    }

    return response()->json([
      'success' => true,
      'data' => [
        'id' => $postback->id,
        'status' => $postback->status,
        'vendor' => $postback->vendor,
        'click_id' => $postback->click_id,
        'payout' => $postback->payout,
        'processed_at' => $postback->processed_at,
        'created_at' => $postback->created_at,
        'updated_at' => $postback->updated_at
      ]
    ]);
  }

  /**
   * Endpoint para buscar payout de un cliente específico en Natural Intelligence
   *
   * @param SearchPayoutRequest $request
   * @return JsonResponse
   */
  public function searchPayout(SearchPayoutRequest $request): JsonResponse
  {
    $startTime = microtime(true);

    try {
      $validated = $request->validated();
      $click_id = $validated['clid'];
      $fromDate = $validated['fromDate'];
      $toDate = $validated['toDate'];

      TailLogger::saveLog('Postback: Iniciando búsqueda manual de payout', 'api/postback', 'info', [
        'click_id' => $click_id,
        'from_date' => $fromDate,
        'to_date' => $toDate,
      ]);

      // Crear un postback temporal para usar con el servicio
      $tempPostback = new Postback([
        'click_id' => $click_id,
        'vendor' => PostbackVendor::NI->value(),
        'id' => 0
      ]);
      // Obtener reporte usando el servicio de Natural Intelligence
      $this->niService->setPostbackId($tempPostback->id);
      $reportResult = $this->niService->getConversionsReport($fromDate, $toDate);

      if (!$reportResult['success']) {
        TailLogger::saveLog('Postback: Error al obtener reporte de NI', 'api/postback', 'error', [
          'click_id' => $click_id,
          'from_date' => $fromDate,
          'to_date' => $toDate
        ]);

        return response()->json([
          'success' => false,
          'message' => 'Error al obtener reporte de Natural Intelligence'
        ], 500);
      }
      $conversions = $reportResult['data'] ?? [];
      if (empty($conversions)) {
        TailLogger::saveLog('Postback: No se encontraron conversiones en el período', 'api/postback', 'warning', [
          'click_id' => $click_id,
          'from_date' => $fromDate,
          'to_date' => $toDate
        ]);

        return response()->json([
          'success' => true,
          'message' => 'No se encontraron conversiones en el período especificado',
          'data' => [
            'click_id' => $click_id,
            'from_date' => $fromDate,
            'to_date' => $toDate,
            'conversions' => [],
            'total_conversions' => 0
          ]
        ]);
      }

      // Buscar conversiones específicas para el click_id
      $clientConversions = collect($conversions)->filter(function ($item) use ($click_id) {
        return isset($item['pub_param_1']) && $item['pub_param_1'] === $click_id;
      })->values();

      $responseTime = (int) ((microtime(true) - $startTime) * 1000);

      if ($clientConversions->isEmpty()) {
        TailLogger::saveLog('Postback: CLICK ID no encontrado en conversiones', 'api/postback', 'warning', [
          'click_id' => $click_id,
          'from_date' => $fromDate,
          'to_date' => $toDate,
          'total_conversions' => count($conversions),
          'response_time_ms' => $responseTime
        ]);

        return response()->json([
          'success' => true,
          'message' => 'CLICK ID not found in period conversions',
          'data' => [
            'click_id' => $click_id,
            'from_date' => $fromDate,
            'to_date' => $toDate,
            'conversions' => [],
            'total_conversions' => 0,
            'total_conversions_in_period' => count($conversions)
          ]
        ]);
      }

      // Calcular totales
      $totalPayout = $clientConversions->sum('payout');
      $totalLeads = $clientConversions->sum('leads');
      $totalSales = $clientConversions->sum('sales');

      TailLogger::saveLog('Postback: Búsqueda de payout completada exitosamente', 'api/postback', 'success', [
        'click_id' => $click_id,
        'from_date' => $fromDate,
        'to_date' => $toDate,
        'conversions_found' => $clientConversions->count(),
        'total_payout' => $totalPayout,
        'response_time_ms' => $responseTime
      ]);

      return response()->json([
        'success' => true,
        'message' => 'Búsqueda completada exitosamente',
        'data' => [
          'click_id' => $click_id,
          'from_date' => $fromDate,
          'to_date' => $toDate,
          'conversions' => $clientConversions->toArray(),
          'summary' => [
            'total_conversions' => $clientConversions->count(),
            'total_payout' => $totalPayout,
            'total_leads' => $totalLeads,
            'total_sales' => $totalSales
          ],
          'response_time_ms' => $responseTime
        ]
      ]);
    } catch (\App\Services\NaturalIntelligenceServiceException $e) {
      $responseTime = (int) ((microtime(true) - $startTime) * 1000);
      TailLogger::saveLog('Postback: Error del servicio NI en búsqueda manual', 'api/postback', 'error', [
        'click_id' => $validated['clid'] ?? 'N/A',
        'error' => $e->getMessage(),
        'response_time_ms' => $responseTime,
      ]);

      $badResponse = [
        'success' => false,
        'message' => $e->getMessage()
      ];
      if (app()->environment('local')) {
        $badResponse['trace'] = $e->getTraceAsString();
        $badResponse['error'] = $e->getMessage();
        $badResponse['response_time_ms'] = $responseTime;
      }
      return response()->json($badResponse, 500);
    } catch (\Exception $e) {
      $responseTime = (int) ((microtime(true) - $startTime) * 1000);
      TailLogger::saveLog('Postback: Error inesperado en búsqueda manual', 'api/postback', 'error', [
        'click_id' => $validated['clid'] ?? 'N/A',
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString(),
        'response_time_ms' => $responseTime,
      ]);

      return response()->json([
        'success' => false,
        'message' => 'Unexpected error while processing the search',
        'error' => $e->getMessage()
      ], 500);
    }
  }
}
