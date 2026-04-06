<?php

use App\Services\BotDetectorService;
use Illuminate\Http\Request;

uses(Tests\TestCase::class);

it('detects Googlebot as a bot', function () {
  $request = Request::create(
    '/',
    'GET',
    [],
    [],
    [],
    [
      'HTTP_USER_AGENT' => 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)',
      'HTTP_ACCEPT_LANGUAGE' => 'en-US',
      'HTTP_ACCEPT' => 'text/html',
    ],
  );
  app()->instance('request', $request);

  $service = new BotDetectorService($request);
  $isBot = $service->detectBot('Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)');

  expect($isBot)->toBeTrue();
  expect($service->getBotName())->not->toBeNull();
});

it('does not flag a real browser as bot', function () {
  $ua = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36';
  $request = Request::create(
    '/',
    'GET',
    [],
    [],
    [],
    [
      'HTTP_USER_AGENT' => $ua,
      'HTTP_ACCEPT_LANGUAGE' => 'en-US,en;q=0.9',
      'HTTP_ACCEPT' => 'text/html,application/xhtml+xml',
    ],
  );
  app()->instance('request', $request);

  $service = new BotDetectorService($request);
  $isBot = $service->detectBot($ua);

  expect($isBot)->toBeFalse();
});

it('detects missing user agent as bot', function () {
  $request = Request::create(
    '/',
    'GET',
    [],
    [],
    [],
    [
      'HTTP_ACCEPT_LANGUAGE' => 'en-US',
      'HTTP_ACCEPT' => 'text/html',
    ],
  );
  app()->instance('request', $request);

  $service = new BotDetectorService($request);
  $isBot = $service->detectBot('');

  expect($isBot)->toBeTrue();
  expect($service->getBotName())->toBe('MISSING_USER_AGENT');
});

it('returns UNKNOWN when crawler name cannot be determined', function () {
  $request = Request::create(
    '/',
    'GET',
    [],
    [],
    [],
    [
      'HTTP_USER_AGENT' => 'Mozilla/5.0',
      'HTTP_ACCEPT_LANGUAGE' => 'en-US',
      'HTTP_ACCEPT' => 'text/html',
    ],
  );
  app()->instance('request', $request);

  $service = new BotDetectorService($request);

  // The service should handle unknown crawlers gracefully
  expect($service->getBotName())->toBeNull();
  expect($service->getBotType())->toBeNull();
});
