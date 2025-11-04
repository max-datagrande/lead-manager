<?php

namespace App\Listeners;

use App\Events\PostbackProcessed;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use App\Services\PostbackService;

class RedirectPostback implements ShouldQueue
{
  use InteractsWithQueue;
  protected $postbackService;

  public function __construct(PostbackService $postbackService)
  {
    $this->postbackService = $postbackService;
  }
  /**
   * Handle the event.
   */
  public function handle(PostbackProcessed $event): void
  {
    $this->postbackService->redirectPostback($event->postback);
  }
}
