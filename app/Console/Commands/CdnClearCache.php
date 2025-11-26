<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class CdnClearCache extends Command
{
  /**
   * The name and signature of the console command.
   *
   * @var string
   */
  protected $signature = 'catalyst:clear-cache';

  /**
   * The console command description.
   *
   * @var string
   */
  protected $description = 'Clear the cache for the Catalyst CDN';

  /**
   * Execute the console command.
   */
  public function handle()
  {
    try {
      Cache::forget('catalyst.manifest');
      $this->info('CDN Catalyst manifest cache cleared successfully.');
    } catch (\Throwable $th) {
      $this->error('Error clearing CDN Catalyst manifest cache: ' . $th->getMessage());
    }
  }
}
