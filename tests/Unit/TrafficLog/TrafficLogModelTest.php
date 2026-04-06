<?php

use App\Models\TrafficLog;

uses(Tests\TestCase::class, Illuminate\Foundation\Testing\RefreshDatabase::class);

it('persists platform and channel via mass assignment', function () {
  $log = TrafficLog::create([
    'id' => (string) \Illuminate\Support\Str::uuid(),
    'fingerprint' => hash('sha256', 'test-fingerprint-' . now()->timestamp),
    'visit_date' => now()->toDateString(),
    'visit_count' => 1,
    'ip_address' => '192.168.1.1',
    'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
    'host' => 'example.com',
    'is_bot' => false,
    'platform' => 'Google Ads',
    'channel' => 'ads',
  ]);

  $log->refresh();

  expect($log->platform)->toBe('Google Ads');
  expect($log->channel)->toBe('ads');
});

it('includes platform and channel in fillable attributes', function () {
  $model = new TrafficLog();
  $fillable = $model->getFillable();

  expect($fillable)->toContain('platform');
  expect($fillable)->toContain('channel');
});
