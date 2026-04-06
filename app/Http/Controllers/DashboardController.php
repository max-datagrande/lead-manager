<?php

namespace App\Http\Controllers;

use App\Services\HostingerVpsService;
use App\Services\PerformanceMetricService;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
  public function __construct(private PerformanceMetricService $performanceMetricService, private HostingerVpsService $hostingerVpsService) {}

  public function index(): Response
  {
    return Inertia::render('dashboard', [
      'performanceSummary' => Inertia::defer(fn() => $this->performanceMetricService->getDashboardSummary(30)),
      'vpsMetrics' => Inertia::defer(fn() => $this->hostingerVpsService->getMetrics()),
    ]);
  }
}
