<?php

namespace App\Console\Commands;

use App\Models\OfferwallConversion;
use App\Models\Integration;
use App\Models\IntegrationCallLog;
use Illuminate\Console\Command;
use Carbon\Carbon;

class BackfillOfferCompanyNames extends Command
{
  /**
   * The name and signature of the console command.
   *
   * @var string
   */
  protected $signature = 'offerwall:backfill-offer-company-names';

  /**
   * The console command description.
   *
   * @var string
   */
  protected $description = 'Backfills the offer_company_name for existing offerwall conversions from the last two months.';

  /**
   * Execute the console command.
   */
  public function handle()
  {
    $this->info('Starting backfill for offer_company_name...');

    $twoMonthsAgo = Carbon::now()->subMonths(2);

    $query = OfferwallConversion::whereNull('offer_company_name')->where('created_at', '>=', $twoMonthsAgo);

    $totalConversions = $query->count();
    $this->info("Found {$totalConversions} conversions to process.");

    $processedCount = 0;

    $query->chunkById(1000, function ($conversions) use (&$processedCount, $totalConversions) {
      foreach ($conversions as $conversion) {
        if ($conversion->offerwall_mix_log_id && $conversion->integration_id && !empty($conversion->offer_data)) {
          $callLog = IntegrationCallLog::where('loggable_type', \App\Models\OfferwallMixLog::class)
            ->where('loggable_id', $conversion->offerwall_mix_log_id)
            ->where('integration_id', $conversion->integration_id)
            ->first();

          if ($callLog) {
            $integration = Integration::find($conversion->integration_id);

            if ($integration) {
              $parserConfig = $integration->response_parser_config;
              $companyMappingPath = $parserConfig['mapping']['company'] ?? null;

              if ($companyMappingPath) {
                $offerCompanyName = data_get($conversion->offer_data, $companyMappingPath);

                if ($offerCompanyName && $conversion->offer_company_name !== $offerCompanyName) {
                  $conversion->offer_company_name = $offerCompanyName;
                  $conversion->save();
                  $this->comment("Updated conversion #{$conversion->id} with offer_company_name: {$offerCompanyName}");
                }
              } else {
                $this->warn("No 'company' mapping found for integration #{$integration->id}. Skipping conversion #{$conversion->id}.");
              }
            } else {
              $this->error("Integration #{$conversion->integration_id} not found for conversion #{$conversion->id}. Cannot backfill.");
            }
          } else {
            $this->warn("Call log not found for conversion #{$conversion->id}. Cannot backfill offer_company_name.");
          }
        } else {
          $this->warn("Conversion #{$conversion->id} missing mix_log_id, integration_id, or offer_data. Skipping.");
        }
        $processedCount++;
      }
      $this->info("Processed {$processedCount}/{$totalConversions} conversions...");
    });

    $this->info('Backfill process completed.');
  }
}
