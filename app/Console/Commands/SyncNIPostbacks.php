<?php

namespace App\Console\Commands;

use App\Enums\PostbackVendor;
use App\Events\PostbackProcessed;
use App\Models\Postback;
use App\Services\NaturalIntelligenceService;
use Illuminate\Console\Command;
use Maxidev\Logger\TailLogger;

class SyncNIPostbacks extends Command
{
  /**
   * The name and signature of the console command.
   *
   * @var string
   */
  protected $signature = 'postbacks:sync-ni';

  /**
   * The console command description.
   *
   * @var string
   */
  protected $description = 'Fetches recent NI reports and updates pending postbacks with their payout.';

  /**
   * Create a new command instance.
   *
   * @param NaturalIntelligenceService $niService
   * @return void
   */
  public function __construct(protected NaturalIntelligenceService $niService)
  {
    parent::__construct();
  }

  /**
   * Execute the console command.
   */
  public function handle()
  {
    $this->info('Starting NI Postback Synchronization for date: ' . now()->format('Y-m-d') . ' ...');
    TailLogger::saveLog('SyncNIPostbacks: Starting job.', 'cron/sync-ni', 'info', []);

    // 1. Fetch NI Report
    $this->line('Fetching recent conversions report from Natural Intelligence...');
    try {
      $report = $this->niService->getConversionsReport(null, null, 'sync-job');
      if (!$report['success']) {
        $this->error('Failed to fetch NI report.');
        TailLogger::saveLog('SyncNIPostbacks: Failed to fetch NI report.', 'cron/sync-ni', 'error', $report);
        return 1; // Error exit code
      }
      $niConversions = collect($report['data'] ?? [])->keyBy('pub_param_1');
      $this->info('Successfully fetched ' . $niConversions->count() . ' conversions from NI report.');
    } catch (\Exception $e) {
      $this->error('An exception occurred while fetching the NI report: ' . $e->getMessage());
      TailLogger::saveLog('SyncNIPostbacks: Exception fetching NI report.', 'cron/sync-ni', 'error', ['exception' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
      return 1;
    }

    // 2. Fetch Pending Postbacks
    $this->line('Fetching pending postbacks for vendor: ' . PostbackVendor::NI->value);
    $pendingPostbacks = Postback::where('status', Postback::statusPending())
      ->where('vendor', PostbackVendor::NI->value)
      ->get();
    if ($pendingPostbacks->isEmpty()) {
      $this->info('No pending NI postbacks to process. Job finished.');
      TailLogger::saveLog('SyncNIPostbacks: No pending NI postbacks found.', 'cron/sync-ni', 'info', []);
      return 0; // Success exit code
    }
    $this->info('Found ' . $pendingPostbacks->count() . ' pending postbacks to process.');

    // 3. Match and Update
    $updatedCount = 0;
    $notFoundCount = 0;
    $this->withProgressBar($pendingPostbacks, function ($postback) use ($niConversions, &$updatedCount, &$notFoundCount) {
      $clickId = $postback->click_id;

      if ($niConversions->has($clickId)) {
        $conversion = $niConversions->get($clickId);
        $payout = $conversion['payout'] ?? null;

        if ($payout > 0) {
          $postback->update([
            'payout' => $payout,
            'status' => Postback::statusProcessed(),
            'message' => 'Payout updated via sync job.',
            'response_data' => $conversion,
            'processed_at' => now(),
          ]);

          // 4. Dispatch Event
          PostbackProcessed::dispatch($postback);
          $updatedCount++;
          TailLogger::saveLog('SyncNIPostbacks: Updated postback.', 'cron/sync-ni', 'info', ['postback_id' => $postback->id, 'click_id' => $clickId, 'payout' => $payout]);
        } else if ($payout !== null) { // Handles payout == 0
          $postback->update([
            'payout' => 0,
            'status' => Postback::statusSkipped(),
            'message' => 'Payout was 0, postback skipped.',
            'response_data' => $conversion,
            'processed_at' => now(),
          ]);
          // No event dispatch for skipped postbacks
          TailLogger::saveLog('SyncNIPostbacks: Postback skipped, payout was 0.', 'cron/sync-ni', 'info', ['postback_id' => $postback->id, 'click_id' => $clickId]);
        } else {
          // This case handles when the 'payout' key is missing from the conversion data
          TailLogger::saveLog('SyncNIPostbacks: Conversion found but no payout value.', 'cron/sync-ni', 'warning', ['postback_id' => $postback->id, 'click_id' => $clickId, 'conversion' => $conversion]);
        }
      } else {
        $notFoundCount++;
        TailLogger::saveLog('SyncNIPostbacks: Click ID not found in NI report.', 'cron/sync-ni', 'warning', ['postback_id' => $postback->id, 'click_id' => $clickId]);
      }
    });

    $this->newLine(2);
    $this->info('NI Postback Synchronization Finished.');
    $this->line("Summary: {$updatedCount} postbacks updated, {$notFoundCount} not found in report.");
    TailLogger::saveLog('SyncNIPostbacks: Job finished.', 'cron/sync-ni', 'info', ['updated' => $updatedCount, 'not_found' => $notFoundCount]);

    return 0;
  }
}
