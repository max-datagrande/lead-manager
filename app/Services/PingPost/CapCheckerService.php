<?php

namespace App\Services\PingPost;

use App\Models\Integration;
use App\Models\PostResult;
use Carbon\Carbon;

class CapCheckerService
{
  /**
   * Check if any cap rule is exceeded for the given integration.
   */
  public function isCapExceeded(Integration $integration): bool
  {
    foreach ($integration->capRules as $rule) {
      $start = $this->periodStart($rule->period);

      $query = PostResult::query()
        ->where('integration_id', $integration->id)
        ->whereIn('status', ['accepted', 'postback_resolved'])
        ->where('created_at', '>=', $start);

      if ($rule->max_leads !== null) {
        if ($query->count() >= $rule->max_leads) {
          return true;
        }
      }

      if ($rule->max_revenue !== null) {
        $revenue = (clone $query)->sum('price_final');
        if ($revenue >= $rule->max_revenue) {
          return true;
        }
      }
    }

    return false;
  }

  private function periodStart(string $period): Carbon
  {
    return match ($period) {
      'day' => Carbon::now()->startOfDay(),
      'week' => Carbon::now()->startOfWeek(),
      'month' => Carbon::now()->startOfMonth(),
      'year' => Carbon::now()->startOfYear(),
      default => Carbon::now()->startOfDay(),
    };
  }
}
