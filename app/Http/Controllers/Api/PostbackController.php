<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\FirePostbackRequest;
use App\Models\ConversionLog;
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
}
