<?php

namespace App\Http\Controllers\Api\Offerwall;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use App\Models\OfferwallMix;
use App\Services\Offerwall\MixService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MixController extends Controller
{
  use ApiResponseTrait;

  protected $mixService;

  public function __construct(MixService $mixService)
  {
    $this->mixService = $mixService;
  }

  public function trigger(Request $request, OfferwallMix $offerwallMix): JsonResponse
  {
    $validated = $request->validate([
      'fingerprint' => 'required|string',
    ]);
    
    $result = $this->mixService->fetchAndAggregateOffers($offerwallMix, $validated['fingerprint']);
    
    // Extraer información de la respuesta del servicio
    $success = $result['success'] ?? false;
    $message = $result['message'] ?? 'Unknown response';
    $data = $result['data'] ?? null;
    $statusCode = $result['status_code'] ?? 500;
    $meta = $result['meta'] ?? null;
    
    if ($success) {
      return $this->successResponse($data, $message, $statusCode, $meta);
    } else {
      return $this->errorResponse($message, null, $statusCode);
    }
  }
}
