<?php

namespace App\Http\Controllers\LeadQuality;

use App\Http\Controllers\Controller;
use App\Models\LeadQualityProvider;
use App\Services\LeadQuality\LeadQualityProviderResolver;
use Illuminate\Http\JsonResponse;

class ProviderTestController extends Controller
{
  public function __construct(private readonly LeadQualityProviderResolver $resolver) {}

  public function test(LeadQualityProvider $provider): JsonResponse
  {
    try {
      $service = $this->resolver->forProvider($provider);
    } catch (\InvalidArgumentException $e) {
      return response()->json(
        [
          'ok' => false,
          'error' => $e->getMessage(),
        ],
        422,
      );
    }

    $result = $service->testConnection($provider);

    return response()->json(
      [
        'ok' => $result->ok,
        'message' => $result->message,
        'error' => $result->error,
      ],
      $result->ok ? 200 : 422,
    );
  }
}
