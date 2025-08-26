<?php

namespace App\Events;

use App\Models\Postback;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PostbackProcessed
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Postback $postback;

    /**
     * Create a new event instance.
     */
    public function __construct(Postback $postback)
    {
        $this->postback = $postback;
    }
}
