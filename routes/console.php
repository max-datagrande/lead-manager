<?php

/* use App\Jobs\RetryFailedPostbacksJob; */
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
/* use Illuminate\Support\Facades\Schedule; */

Artisan::command('inspire', function () {
  $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/* Schedule::job(new RetryFailedPostbacksJob)
    ->everyMinute()
    ->name('postback-retry-failed')
    ->withoutOverlapping(5)
    ->onOneServer(); */
