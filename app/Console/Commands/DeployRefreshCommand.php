<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class DeployRefreshCommand extends Command
{
  protected $signature = 'deploy:refresh';

  protected $description = 'Clear and re-cache config, routes, views, events, and restart queue workers.';

  public function handle(): int
  {
    $steps = [
      ['optimize:clear', 'Clearing caches'],
      ['optimize', 'Caching config, routes, views & events'],
    ];

    foreach ($steps as [$command, $label]) {
      $this->info("→ {$label}...");
      $this->call($command);
    }

    $this->info('→ Restarting queue worker...');
    $this->call('queue:restart');

    // Launch a new worker in background if none is running
    $php = PHP_BINARY;
    $artisan = base_path('artisan');
    $logFile = storage_path('logs/worker.log');

    if (str_contains(PHP_OS, 'WIN')) {
      pclose(popen("start /B {$php} {$artisan} queue:work --sleep=3 --tries=3 >> \"{$logFile}\" 2>&1", 'r'));
    } else {
      exec("{$php} {$artisan} queue:work --sleep=3 --tries=3 >> \"{$logFile}\" 2>&1 &");
    }

    $this->info('  Worker launched in background (logging to storage/logs/worker.log)');

    $this->newLine();
    $this->info('✔ Deploy refresh complete.');

    return self::SUCCESS;
  }
}
