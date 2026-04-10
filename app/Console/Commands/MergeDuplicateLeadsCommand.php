<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MergeDuplicateLeadsCommand extends Command
{
  protected $signature = 'leads:merge-duplicates {--dry-run : Show what would be merged without making changes}';

  protected $description = 'Merge duplicate leads with the same fingerprint. Keeps the lead with most field responses, reassigns FKs, merges field responses (newest wins on conflict).';

  public function handle(): int
  {
    $dryRun = $this->option('dry-run');

    $duplicates = DB::table('leads')
      ->select('fingerprint', DB::raw('COUNT(*) as cnt'))
      ->whereNotNull('fingerprint')
      ->groupBy('fingerprint')
      ->havingRaw('COUNT(*) > 1')
      ->get();

    if ($duplicates->isEmpty()) {
      $this->info('No duplicate fingerprints found.');
      return self::SUCCESS;
    }

    $this->info("Found {$duplicates->count()} duplicate fingerprints.");

    $mergedCount = 0;
    $deletedCount = 0;

    foreach ($duplicates as $dup) {
      $leads = DB::table('leads')
        ->where('fingerprint', $dup->fingerprint)
        ->orderBy('id')
        ->get(['id']);

      $leadIds = $leads->pluck('id')->all();

      // Pick keeper: the one with most field responses
      $fieldCounts = DB::table('lead_field_responses')
        ->select('lead_id', DB::raw('COUNT(*) as cnt'))
        ->whereIn('lead_id', $leadIds)
        ->groupBy('lead_id')
        ->pluck('cnt', 'lead_id')
        ->all();

      $keeperId = collect($leadIds)->sortByDesc(fn($id) => $fieldCounts[$id] ?? 0)->first();

      $duplicateIds = array_filter($leadIds, fn($id) => $id !== $keeperId);

      if ($dryRun) {
        $this->line(
          "  [{$dup->fingerprint}] keep #{$keeperId} (" . ($fieldCounts[$keeperId] ?? 0) . ' fields), delete #' . implode(', #', $duplicateIds),
        );
        $mergedCount++;
        $deletedCount += count($duplicateIds);
        continue;
      }

      DB::transaction(function () use ($keeperId, $duplicateIds, $dup) {
        // 1. Merge field responses: for each duplicate, move its responses to keeper
        foreach ($duplicateIds as $dupeId) {
          $responses = DB::table('lead_field_responses')
            ->where('lead_id', $dupeId)
            ->get(['id', 'field_id', 'value', 'fingerprint', 'created_at', 'updated_at']);

          foreach ($responses as $resp) {
            $existing = DB::table('lead_field_responses')
              ->where('lead_id', $keeperId)
              ->where('field_id', $resp->field_id)
              ->first(['id', 'updated_at']);

            if ($existing) {
              // Conflict: keep the most recent
              if ($resp->updated_at > $existing->updated_at) {
                DB::table('lead_field_responses')
                  ->where('id', $existing->id)
                  ->update([
                    'value' => $resp->value,
                    'updated_at' => $resp->updated_at,
                  ]);
              }
              // Delete the duplicate response
              DB::table('lead_field_responses')->where('id', $resp->id)->delete();
            } else {
              // No conflict: reassign to keeper
              DB::table('lead_field_responses')
                ->where('id', $resp->id)
                ->update([
                  'lead_id' => $keeperId,
                  'fingerprint' => $dup->fingerprint,
                ]);
            }
          }
        }

        // 2. Reassign lead_dispatches
        DB::table('lead_dispatches')
          ->whereIn('lead_id', $duplicateIds)
          ->update(['lead_id' => $keeperId]);

        // 3. Delete duplicate leads
        DB::table('leads')->whereIn('id', $duplicateIds)->delete();
      });

      $mergedCount++;
      $deletedCount += count($duplicateIds);
      $this->line("  Merged #{$dup->fingerprint}: kept #{$keeperId}, deleted #" . implode(', #', $duplicateIds));
    }

    $label = $dryRun ? 'Would merge' : 'Merged';
    $this->info("{$label} {$mergedCount} fingerprints, {$label} {$deletedCount} duplicate leads.");

    return self::SUCCESS;
  }
}
