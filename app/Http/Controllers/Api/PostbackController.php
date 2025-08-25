<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\FirePostbackRequest;
use App\Http\Requests\SearchPayoutRequest;
use App\Models\Postback;
use App\Services\NaturalIntelligenceService;
use App\Jobs\ProcessPostbackJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Maxidev\Logger\TailLogger;
use Illuminate\Http\Request;

/**
 * Controller para manejar postbacks a Natural Intelligence desde landing pages
 */
class PostbackController extends Controller
{
  public array $vendorServices = [];
  public function __construct(protected NaturalIntelligenceService $niService)
  {
    $this->vendorServices = [
      'ni' =>  $niService
    ];
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
    try {
      $validated = $request->validated();
      $vendor = $validated['vendor'];
      $offerId = $validated['offer_id'];

      // Validar vendor
      if (!in_array($vendor, ['ni'])) {
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

      // Crear postback con estado pending
      $postback = Postback::create([
        'offer_id' => $offerId,
        'clid' => $validated['clid'],
        'payout' => $validated['payout'] ?? null, // Será actualizado por el job
        'txid' => $validated['txid'],
        'currency' => $validated['currency'],
        'event' => $validated['event'],
        'vendor' => $vendor,
        'status' => Postback::STATUS_PENDING
      ]);

      TailLogger::saveLog('Postback: Postback creado, despachando job', 'api/postback', 'info', [
        'postback_id' => $postback->id,
        'vendor' => $vendor,
        'offer_id' => $offerId,
        'clid' => $validated['clid']
      ]);
      $clickId = $validated['clid'];
      // Despachar job para obtener payout de Natural Intelligence
      ProcessPostbackJob::dispatch($postback->id, $clickId);
      return response()->json([
        'success' => true,
        'message' => 'Postback received and queued for processing',
        'data' => [
          'postback_id' => $postback->id,
          'vendor' => $vendor,
          'status' => $postback->status,
          'clid' => $clickId
        ]
      ], 200);

    } catch (\Exception $e) {
      TailLogger::saveLog('Postback: Error al procesar postback', 'api/postback', 'error', [
        'vendor' => $vendor,
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
      ]);

      return response()->json([
        'success' => false,
        'message' => 'Error processing postback',
        'error' => $e->getMessage()
      ], 500);
    }
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
        'clid' => $postback->clid,
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
      $clid = $validated['clid'];
      $fromDate = $validated['fromDate'];
      $toDate = $validated['toDate'];

      TailLogger::saveLog('Postback: Iniciando búsqueda manual de payout', 'api/postback', 'info', [
        'clid' => $clid,
        'from_date' => $fromDate,
        'to_date' => $toDate,
      ]);

      // Crear un postback temporal para usar con el servicio
      $tempPostback = new Postback([
        'clid' => $clid,
        'vendor' => 'ni',
        'id' => 0
      ]);
      // Obtener reporte usando el servicio de Natural Intelligence
      $this->niService->setPostbackId($tempPostback->id);
      $reportResult = $this->niService->getConversionsReport($fromDate, $toDate);

      if (!$reportResult['success']) {
        TailLogger::saveLog('Postback: Error al obtener reporte de NI', 'api/postback', 'error', [
          'clid' => $clid,
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
          'clid' => $clid,
          'from_date' => $fromDate,
          'to_date' => $toDate
        ]);

        return response()->json([
          'success' => true,
          'message' => 'No se encontraron conversiones en el período especificado',
          'data' => [
            'clid' => $clid,
            'from_date' => $fromDate,
            'to_date' => $toDate,
            'conversions' => [],
            'total_conversions' => 0
          ]
        ]);
      }

      // Buscar conversiones específicas para el clid
      $clientConversions = collect($conversions)->filter(function ($item) use ($clid) {
        return isset($item['pub_param_1']) && $item['pub_param_1'] === $clid;
      })->values();

      $responseTime = (int) ((microtime(true) - $startTime) * 1000);

      if ($clientConversions->isEmpty()) {
        TailLogger::saveLog('Postback: CLID no encontrado en conversiones', 'api/postback', 'warning', [
          'clid' => $clid,
          'from_date' => $fromDate,
          'to_date' => $toDate,
          'total_conversions' => count($conversions),
          'response_time_ms' => $responseTime
        ]);

        return response()->json([
          'success' => true,
          'message' => 'CLID no encontrado en las conversiones del período',
          'data' => [
            'clid' => $clid,
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
        'clid' => $clid,
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
          'clid' => $clid,
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
        'clid' => $validated['clid'] ?? 'N/A',
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
        'clid' => $validated['clid'] ?? 'N/A',
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
