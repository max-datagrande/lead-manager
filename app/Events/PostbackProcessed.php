<?php

namespace App\Events;

use App\Models\PostbackQueue;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PostbackProcessed
{
  use Dispatchable, InteractsWithSockets, SerializesModels;

  public PostbackQueue $postback;

  /**
   * Create a new event instance.
   */
  public function __construct(PostbackQueue $postback)
  {
    $this->postback = $postback;
  }
}
