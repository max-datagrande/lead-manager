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
      ['queue:restart', 'Restarting queue workers'],
    ];

    foreach ($steps as [$command, $label]) {
      $this->info("→ {$label}...");
      $this->call($command);
    }

    $this->newLine();
    $this->info('✔ Deploy refresh complete.');

    return self::SUCCESS;
  }
}
