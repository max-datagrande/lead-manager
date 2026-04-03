<?php

use App\Services\TrafficLog\FingerprintGeneratorService;
use Illuminate\Http\Request;

beforeEach(function () {
  $this->service = new FingerprintGeneratorService();
});

it('detects Postman user agent and uses postman as host', function () {
  $request = Request::create(
    '/',
    'GET',
    [],
    [],
    [],
    [
      'HTTP_USER_AGENT' => 'PostmanRuntime/7.36.0',
    ],
  );
  app()->instance('request', $request);

  $fingerprint = $this->service->generate('PostmanRuntime/7.36.0', '127.0.0.1', '');

  expect($fingerprint)->toBeString()->toHaveLength(64);
});

it('throws exception when origin host is empty and not Postman', function () {
  $request = Request::create(
    '/',
    'GET',
    [],
    [],
    [],
    [
      'HTTP_USER_AGENT' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
    ],
  );
  app()->instance('request', $request);

  $this->service->generate('Mozilla/5.0', '192.168.1.1', '');
})->throws(\Exception::class, 'Origin host is empty or not valid');

it('generates a valid SHA-256 fingerprint', function () {
  $request = Request::create(
    '/',
    'GET',
    [],
    [],
    [],
    [
      'HTTP_USER_AGENT' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
    ],
  );
  app()->instance('request', $request);

  $fingerprint = $this->service->generate('Mozilla/5.0 (Windows NT 10.0; Win64; x64)', '192.168.1.100', 'https://example.com');

  expect($fingerprint)->toMatch('/^[a-f0-9]{64}$/');
});

it('generates different fingerprints for different hosts', function () {
  $request = Request::create(
    '/',
    'GET',
    [],
    [],
    [],
    [
      'HTTP_USER_AGENT' => 'Mozilla/5.0',
    ],
  );
  app()->instance('request', $request);

  $fp1 = $this->service->generate('Mozilla/5.0', '1.2.3.4', 'site-a.com');
  $fp2 = $this->service->generate('Mozilla/5.0', '1.2.3.4', 'site-b.com');

  expect($fp1)->not->toBe($fp2);
});

// ──────────────────────────────────────────────────────────────────
// Sprint 7: Normalization tests
// ──────────────────────────────────────────────────────────────────

it('generates same fingerprint for different Chrome patch versions', function () {
  $request = Request::create(
    '/',
    'GET',
    [],
    [],
    [],
    [
      'HTTP_USER_AGENT' => 'Mozilla/5.0',
    ],
  );
  app()->instance('request', $request);

  $ua1 = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/124.0.0.0 Safari/537.36';
  $ua2 = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/124.0.6367.91 Safari/537.36';

  $fp1 = $this->service->generate($ua1, '192.168.1.100', 'example.com');
  $fp2 = $this->service->generate($ua2, '192.168.1.100', 'example.com');

  expect($fp1)->toBe($fp2);
});

it('generates same fingerprint for IPs in same /24 subnet', function () {
  $request = Request::create(
    '/',
    'GET',
    [],
    [],
    [],
    [
      'HTTP_USER_AGENT' => 'Mozilla/5.0',
    ],
  );
  app()->instance('request', $request);

  $ua = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/124.0.0.0 Safari/537.36';

  $fp1 = $this->service->generate($ua, '192.168.1.100', 'example.com');
  $fp2 = $this->service->generate($ua, '192.168.1.200', 'example.com');

  expect($fp1)->toBe($fp2);
});

it('generates different fingerprint for different /24 subnets', function () {
  $request = Request::create(
    '/',
    'GET',
    [],
    [],
    [],
    [
      'HTTP_USER_AGENT' => 'Mozilla/5.0',
    ],
  );
  app()->instance('request', $request);

  $ua = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/124.0.0.0 Safari/537.36';

  $fp1 = $this->service->generate($ua, '192.168.1.100', 'example.com');
  $fp2 = $this->service->generate($ua, '192.168.2.100', 'example.com');

  expect($fp1)->not->toBe($fp2);
});

it('generates different fingerprint for different base browsers', function () {
  $request = Request::create(
    '/',
    'GET',
    [],
    [],
    [],
    [
      'HTTP_USER_AGENT' => 'Mozilla/5.0',
    ],
  );
  app()->instance('request', $request);

  $chrome = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/124.0.0.0 Safari/537.36';
  $firefox = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:124.0) Gecko/20100101 Firefox/124.0';

  $fp1 = $this->service->generate($chrome, '192.168.1.100', 'example.com');
  $fp2 = $this->service->generate($firefox, '192.168.1.100', 'example.com');

  expect($fp1)->not->toBe($fp2);
});
