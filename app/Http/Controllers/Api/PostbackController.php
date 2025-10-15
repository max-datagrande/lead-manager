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
  public function __construct(protected PostbackService $postbackService) {}

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
    if (!$this->postbackService->isVendorRegistered($vendor)) {
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
  public function status(int $postbackId): JsonResponse
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
   * Endpoint para buscar payout de un cliente específico en un vendor determinado.
   *
   * @param SearchPayoutRequest $request
   * @return JsonResponse
   */
  public function searchPayout(SearchPayoutRequest $request): JsonResponse
  {
    $validated = $request->validated();
    $clickId = $validated['clid'];
    $vendor = $validated['vendor'];
    $fromDate = $validated['fromDate'] ?? null;
    $toDate = $validated['toDate'] ?? null;

    if (!$this->postbackService->isVendorRegistered($vendor)) {
      return response()->json(['success' => false, 'message' => 'Vendor not supported'], 422);
    }

    try {
      $payout = $this->postbackService->searchPayout($clickId, $vendor, $fromDate, $toDate);

      TailLogger::saveLog('Payout search completed', 'api/postback', 'info', [
        'click_id' => $clickId,
        'vendor' => $vendor,
        'from_date' => $fromDate,
        'to_date' => $toDate,
        'payout_found' => $payout
      ]);

      if ($payout === null) {
        return response()->json(['success' => true, 'message' => 'Click ID not found or has no payout in the specified period.']);
      }

      return response()->json([
        'success' => true,
        'data' => [
          'click_id' => $clickId,
          'vendor' => $vendor,
          'payout' => $payout,
        ]
      ]);
    } catch (\Exception $e) {
      TailLogger::saveLog('Error during payout search', 'api/postback', 'error', [
        'click_id' => $clickId,
        'vendor' => $vendor,
        'from_date' => $fromDate,
        'to_date' => $toDate,
        'error' => $e->getMessage(),
      ]);

      return response()->json(['success' => false, 'message' => 'An error occurred during the search.'], 500);
    }
  }

  /**
   * Force sync a single postback to find its payout.
   *
   * @param Postback $postback
   * @return JsonResponse
   */
  public function forceSync(Postback $postback): JsonResponse
  {
    if (!in_array($postback->status, [Postback::statusPending(), Postback::statusFailed()])) {
      return response()->json([
        'success' => false,
        'message' => 'Only pending or failed postbacks can be synced.',
      ], 422);
    }

    try {
      $result = $this->postbackService->forceSyncPostback($postback);
      return response()->json($result);
    } catch (\Exception $e) {
      TailLogger::saveLog('Error during force sync', 'api/postback', 'error', [
        'postback_id' => $postback->id,
        'error' => $e->getMessage(),
      ]);

      return response()->json(['success' => false, 'message' => 'An unexpected error occurred during the sync.'], 500);
    }
  }
}

/*

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
*/
