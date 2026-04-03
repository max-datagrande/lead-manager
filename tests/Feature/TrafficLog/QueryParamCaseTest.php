<?php

use App\Models\TrafficLog;
use App\Services\TrafficLog\TrafficLogService;
use App\Services\TrafficLog\DeviceDetectionService;
use App\Services\TrafficLog\FingerprintGeneratorService;
use App\Services\GeolocationService;
use App\Services\UtmService;
use Illuminate\Http\Request;

/**
 * Tests que verifican que query_params con case mixto se resuelven correctamente.
 * Mockea las dependencias externas (geo, fingerprint) para aislar la lógica.
 */
beforeEach(function () {
  $this->fingerprint = hash('sha256', 'test-case-sensitivity-' . now()->timestamp . rand(1, 9999));

  // Mock FingerprintGeneratorService
  $fingerprintMock = Mockery::mock(FingerprintGeneratorService::class);
  $fingerprintMock->shouldReceive('generate')->andReturn($this->fingerprint);

  // Mock GeolocationService
  $geoMock = Mockery::mock(GeolocationService::class);
  $geoMock->shouldReceive('getIpAddress')->andReturn('192.168.1.1');
  $geoMock->shouldReceive('getGeolocation')->andReturn([
    'country' => 'US',
    'region' => 'California',
    'city' => 'Los Angeles',
    'postal' => '90001',
  ]);

  // Create request with Origin header and geoService macro
  $request = Request::create('/', 'POST');
  $request->headers->set('origin', 'https://example.com');
  $request->headers->set('user-agent', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)');

  // Register geoService macro on the request
  Request::macro('geoService', function () use ($geoMock) {
    return $geoMock;
  });
  app()->instance('request', $request);

  $this->service = new TrafficLogService(
    new DeviceDetectionService(new \Jenssegers\Agent\Agent()),
    $fingerprintMock,
    $geoMock,
    new UtmService(),
    $request,
  );
});

it('extracts cptype regardless of URL parameter case', function () {
  $data = [
    'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
    'current_page' => '/offer',
    'query_params' => ['CPTYPE' => 'search', 'UTM_SOURCE' => 'google'],
  ];

  $log = $this->service->createTrafficLog($data);

  expect($log->campaign_code)->toBe('search');
});

it('extracts s1 through s10 regardless of URL parameter case', function () {
  $data = [
    'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
    'current_page' => '/offer',
    'query_params' => [
      'S1' => 'affiliate_123',
      'S2' => 'banner_top',
      'S3' => 'v3',
      'S4' => 'geo_us',
      'S10' => 'segment_high',
    ],
  ];

  $log = $this->service->createTrafficLog($data);

  expect($log->s1)->toBe('affiliate_123');
  expect($log->s2)->toBe('banner_top');
  expect($log->s3)->toBe('v3');
  expect($log->s4)->toBe('geo_us');
  expect($log->s10)->toBe('segment_high');
});

it('preserves original query params case in the stored JSON', function () {
  $data = [
    'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
    'current_page' => '/offer',
    'query_params' => ['CPTYPE' => 'native', 'MyParam' => 'value'],
  ];

  $log = $this->service->createTrafficLog($data);

  // The stored query_params should preserve original casing
  expect($log->query_params['CPTYPE'])->toBe('native');
  expect($log->query_params['MyParam'])->toBe('value');
});
