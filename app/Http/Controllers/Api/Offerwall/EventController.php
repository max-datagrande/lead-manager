<?php

namespace App\Http\Controllers\Api\Offerwall;

use App\Http\Controllers\Controller;
use App\Services\Offerwall\ConversionService as OfferwallConversionService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Exception;

class EventController extends Controller
{
  /**
   * @var ConversionService
   */
  protected $conversionService;

  /**
   * EventController constructor.
   *
   * @param ConversionService $conversionService
   */
  public function __construct(OfferwallConversionService $conversionService)
  {
    $this->conversionService = $conversionService;
  }

  /**
   * Handle the incoming offerwall conversion event.
   *
   * @param  \Illuminate\Http\Request  $request
   * @return \Illuminate\Http\JsonResponse
   */
  public function handleOfferwallConversion(Request $request): JsonResponse
  {
    try {
      $data = $request->validate([
        'offer_token' => 'required|string',
        'integration_id' => 'required|integer|exists:integrations,id',
        'amount' => 'required|numeric|min:0',
        'fingerprint' => 'required|string',
        'pathname' => 'nullable|string',
      ]);
      $conversion = $this->conversionService->createConversion($data);

      return new JsonResponse([
        'status' => 'success',
        'message' => 'Conversion created successfully',
        'data' => $conversion
      ], 201);
    } catch (Exception $e) {
      // Log the exception details if needed (the service already does this)
      return new JsonResponse([
        'status' => 'error',
        'message' => 'Failed to create conversion: ' . $e->getMessage()
      ], 500);
    }
  }
}
