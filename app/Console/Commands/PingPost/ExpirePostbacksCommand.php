<?php

namespace App\Console\Commands\PingPost;

use App\Services\PingPost\PostbackResolverService;
use Illuminate\Console\Command;

class ExpirePostbacksCommand extends Command
{
  protected $signature = 'ping-post:expire-postbacks';

  protected $description = 'Expire stale pending postbacks and close unsold dispatches.';

  public function handle(PostbackResolverService $service): int
  {
    $expired = $service->expireStalePostbacks();

    $this->info("Expired {$expired} stale postback(s).");

    return Command::SUCCESS;
  }
}
