<?php

namespace App\Jobs;

use App\Models\PostbackExecution;
use App\Services\PostbackDispatchService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class DispatchPostbackJob implements ShouldQueue
{
  use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

  public int $tries = 1;

  public function __construct(public PostbackExecution $execution) {}

  public function handle(PostbackDispatchService $dispatchService): void
  {
    $dispatchService->dispatch($this->execution);
  }
}
