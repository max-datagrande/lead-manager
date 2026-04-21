<?php

namespace App\Console\Commands\LeadQuality;

use App\Enums\DispatchStatus;
use App\Enums\LeadQuality\ValidationLogStatus;
use App\Models\LeadDispatch;
use App\Models\LeadQualityValidationLog;
use App\Services\PingPost\DispatchTimelineService;
use Illuminate\Console\Command;

/**
 * Sweeps dispatches stuck in PENDING_VALIDATION whose most recent validation
 * log has already expired. Closes them out as VALIDATION_FAILED and flips the
 * log to EXPIRED if the verifier never got a chance to mark it.
 */
class ExpireValidationDispatchesCommand extends Command
{
  protected $signature = 'lead-quality:expire-validation';

  protected $description = 'Expire pending_validation dispatches whose challenge logs have passed their TTL.';

  public function handle(DispatchTimelineService $timeline): int
  {
    $pendingDispatches = LeadDispatch::query()->where('status', DispatchStatus::PENDING_VALIDATION->value)->get();

    $expiredDispatches = 0;
    $expiredLogs = 0;

    foreach ($pendingDispatches as $dispatch) {
      $latestLog = LeadQualityValidationLog::query()->where('lead_dispatch_id', $dispatch->id)->latest('id')->first();

      if (!$latestLog) {
        continue;
      }

      if (!$latestLog->expires_at || !$latestLog->expires_at->isPast()) {
        continue;
      }

      if (!in_array($latestLog->status, [ValidationLogStatus::EXPIRED, ValidationLogStatus::FAILED], true)) {
        $latestLog->markExpired();
        $expiredLogs++;
      }

      $dispatch->update(['status' => DispatchStatus::VALIDATION_FAILED->value]);
      $expiredDispatches++;

      $timeline->logSingle(
        $dispatch->id,
        (string) $dispatch->fingerprint,
        DispatchTimelineService::VALIDATION_FAILED,
        'Challenge expired before verification; dispatch closed by scheduled sweep',
        [
          'validation_log_id' => $latestLog->id,
          'expired_at' => $latestLog->expires_at?->toIso8601String(),
          'reason' => 'quality_expired',
          'source' => 'lead-quality:expire-validation',
        ],
      );
    }

    $this->info("Expired {$expiredDispatches} dispatch(es) and {$expiredLogs} log(s).");

    return Command::SUCCESS;
  }
}
