<?php

namespace App\Http\Controllers;

use App\Services\PerformanceMetricService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PerformanceController extends Controller
{
  public function __construct(private PerformanceMetricService $service) {}

  public function index(Request $request): Response
  {
    $from = $request->query('from', now()->subDays(30)->toDateString());
    $to = $request->query('to', now()->toDateString());
    $host = $request->query('host');

    return Inertia::render('performance/index', [
      'metrics' => $this->service->getHostMetrics($host, $from, $to),
      'stats' => $this->service->getPeriodStats($host, $from, $to),
      'hosts' => $this->service->getHosts(),
      'filters' => compact('from', 'to', 'host'),
    ]);
  }
}
