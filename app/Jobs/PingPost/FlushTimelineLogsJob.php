<?php

namespace App\Jobs\PingPost;

use App\Models\DispatchTimelineLog;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class FlushTimelineLogsJob implements ShouldQueue
{
  use Queueable;

  public int $tries = 3;

  /**
   * @param array<int, array{fingerprint: string, lead_dispatch_id: int, event: string, message: string, context: ?array, logged_at: string}> $entries
   */
  public function __construct(
    public readonly array $entries,
  ) {}

  public function handle(): void
  {
    $now = now();

    $rows = array_map(fn(array $entry) => array_merge($entry, [
      'context' => $entry['context'] !== null ? json_encode($entry['context']) : null,
      'created_at' => $now,
      'updated_at' => $now,
    ]), $this->entries);

    DispatchTimelineLog::insert($rows);
  }
}
