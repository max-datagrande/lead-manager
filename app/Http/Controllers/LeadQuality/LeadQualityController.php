<?php

namespace App\Http\Controllers\LeadQuality;

use App\Http\Controllers\Controller;
use App\Models\LeadQualityProvider;
use App\Models\LeadQualityValidationLog;
use App\Models\LeadQualityValidationRule;
use Inertia\Inertia;
use Inertia\Response;

class LeadQualityController extends Controller
{
  public function index(): Response
  {
    return Inertia::render('lead-quality/index', [
      'providers_count' => LeadQualityProvider::query()->count(),
      'rules_count' => LeadQualityValidationRule::query()->count(),
      'logs_count' => LeadQualityValidationLog::query()->count(),
    ]);
  }
}
