<?php

namespace App\Console\Commands;

use App\Models\LeadDispatch;
use App\Models\TrafficLog;
use Illuminate\Console\Command;

class BackfillDispatchUtmSourceCommand extends Command
{
  protected $signature = 'dispatches:backfill-utm-source';

  protected $description = 'Backfill utm_source on lead_dispatches from traffic_logs by fingerprint';

  public function handle(): int
  {
    $total = LeadDispatch::whereNull('utm_source')->count();
    $this->info("Found {$total} dispatches without utm_source.");

    if ($total === 0) {
      $this->info('Nothing to backfill.');
      return self::SUCCESS;
    }

    $bar = $this->output->createProgressBar($total);
    $updated = 0;

    LeadDispatch::whereNull('utm_source')->chunkById(500, function ($dispatches) use ($bar, &$updated) {
      $fingerprints = $dispatches->pluck('fingerprint')->unique()->filter()->values();

      $utmMap = TrafficLog::whereIn('fingerprint', $fingerprints)
        ->orderByDesc('visit_date')
        ->get(['fingerprint', 'utm_source'])
        ->unique('fingerprint')
        ->pluck('utm_source', 'fingerprint');

      foreach ($dispatches as $dispatch) {
        $utm = $utmMap[$dispatch->fingerprint] ?? null;
        if ($utm) {
          $dispatch->updateQuietly(['utm_source' => $utm]);
          $updated++;
        }
        $bar->advance();
      }
    });

    $bar->finish();
    $this->newLine();
    $this->info("Updated {$updated} dispatches.");

    return self::SUCCESS;
  }
}
