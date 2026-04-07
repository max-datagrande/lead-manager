<?php

namespace App\Http\Controllers;

use App\Services\HostingerVpsService;
use Illuminate\Http\JsonResponse;

class VpsMetricsController extends Controller
{
  public function __construct(private HostingerVpsService $vpsService) {}

  public function refresh(): JsonResponse
  {
    $this->vpsService->flush();
    $metrics = $this->vpsService->getMetrics();

    return response()->json($metrics);
  }
}
