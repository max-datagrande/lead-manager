<?php

namespace App\Jobs\PingPost;

use App\Models\DispatchBuyerEvent;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Maxidev\Logger\TailLogger;

class FlushBuyerEventsJob implements ShouldQueue
{
  use Queueable;

  public int $tries = 3;

  /**
   * @param array<int, array{lead_dispatch_id: int, integration_id: int, event: string, reason: string, detail: ?string, created_at: string}> $entries
   */
  public function __construct(public readonly array $entries) {}

  public function handle(): void
  {
    TailLogger::saveLog('FlushBuyerEventsJob START', 'dispatch/buyer-events', 'info', [
      'count' => count($this->entries),
    ]);

    $now = now();

    $rows = array_map(
      fn(array $entry) => array_merge($entry, [
        'created_at' => $now,
      ]),
      $this->entries,
    );

    DispatchBuyerEvent::insert($rows);

    TailLogger::saveLog('FlushBuyerEventsJob DONE', 'dispatch/buyer-events', 'info', [
      'inserted' => count($rows),
    ]);
  }
}
