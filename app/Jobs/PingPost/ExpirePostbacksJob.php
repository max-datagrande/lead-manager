<?php

namespace App\Jobs\PingPost;

use App\Services\PingPost\PostbackResolverService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ExpirePostbacksJob implements ShouldQueue
{
    use Queueable;

    public function handle(PostbackResolverService $service): void
    {
        $service->expireStalePostbacks();
    }
}
