<?php

namespace App\Http\Controllers;

use App\Services\PerformanceMetricService;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
  public function __construct(private PerformanceMetricService $performanceMetricService) {}

  public function index(): Response
  {
    return Inertia::render('dashboard', [
      'performanceSummary' => Inertia::defer(fn() => $this->performanceMetricService->getDashboardSummary(30)),
    ]);
  }
}
