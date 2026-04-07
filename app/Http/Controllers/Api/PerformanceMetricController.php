<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StorePerformanceMetricRequest;
use App\Http\Traits\ApiResponseTrait;
use App\Services\PerformanceMetricService;
use Illuminate\Http\JsonResponse;

class PerformanceMetricController extends Controller
{
  use ApiResponseTrait;

  public function __construct(private PerformanceMetricService $service) {}

  /**
   * Store a performance metric reported by the Catalyst SDK.
   */
  public function store(StorePerformanceMetricRequest $request): JsonResponse
  {
    $this->service->record($request->validated());

    return $this->successResponse(message: 'Metric recorded', status: 201);
  }
}
