<?php

use App\Services\PostbackFireService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
  $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// ─── Scheduled Tasks ──────────────────────────────────────────────────────────

$retryInterval = config('postbacks.retry_interval', 30);

Schedule::call(function () {
  app(PostbackFireService::class)->processRetryableExecutions();
})
  ->cron("*/{$retryInterval} * * * *")
  ->name('postback:retry-failed')
  ->withoutOverlapping()
  ->onOneServer();

Schedule::command('ping-post:expire-postbacks')
  ->daily()
  ->name('ping-post:expire-postbacks')
  ->withoutOverlapping()
  ->onOneServer();
