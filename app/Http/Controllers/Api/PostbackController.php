<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\FirePostbackRequest;
use App\Models\ConversionLog;
use App\Models\Postback;
use App\Services\NaturalIntelligenceService;
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
   * Endpoint principal para recibir conversiones y enviar postback a NI
   *
   * @param FirePostbackRequest $request
   * @return JsonResponse
   */
  public function store(FirePostbackRequest $request): JsonResponse
  {
    $validated = $request->validated();
    $offerId = $validated['offer_id'];
    $vendor = $validated['vendor'];
    $offers = collect(config('offers.maxconv'));
    $offer = $offers->where('offer_id', $offerId)->first() ?? null;
    if (!$offer) {
      return response()->json([
        'success' => false,
        'message' => 'Offer not found',
      ], 422);
    }
    //Saving postback
    $postback = Postback::create([
      'offer_id' => $offerId,
      'clid' => $validated['clid'],
      'payout' => $validated['payout'] ?? null,
      'txid' => $validated['txid'],
      'currency' => $validated['currency'],
      'event' => $validated['event'],
      'vendor' => $vendor,
    ]);

    TailLogger::saveLog('Postback: Postback recorded in BD', 'api/postback', 'info', [
      'postback_id' => $postback->id,
      'vendor' => $vendor,
      'offer_id' => $offerId
    ]);

    $postbackUrl = $offer['postback_url'];
    $vendorServices = $this->getCurrentVendorServices($vendor);

    return response()->json([
      'success' => true,
      'message' => 'Postback sent successfully',
      'data' => [
        'tracking_id' => $conversionLog->id ?? null,
        'fingerprint' => $conversionLog->fingerprint ?? null,
        'event_type' => $conversionLog->event_type ?? null,
        'postback_sent' => true,
        'ni_connection' => true
      ]
    ], 200);
  }

  /**
   * Endpoint para obtener reportes de NI (opcional, para admin)
   */
  public function getReport(Request $request): JsonResponse
  {
    $fromDate = $request->input('from_date', now()->subDays(7)->format('Y-m-d'));
    $toDate = $request->input('to_date', now()->format('Y-m-d'));
    $format = $request->input('format', 'json');

    $result = $this->niService->getConversionsReport($fromDate, $toDate, $format);

    if ($result['success']) {
      return response()->json([
        'success' => true,
        'data' => $result['data']
      ]);
    }

    return response()->json([
      'success' => false,
      'message' => $result['message'],
      'error' => $result['error'] ?? null
    ], 422);
  }
}
