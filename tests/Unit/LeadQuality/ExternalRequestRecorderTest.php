<?php

use App\Models\ExternalServiceRequest;
use App\Models\LeadQualityValidationLog;
use App\Services\ExternalServiceRequest\ExternalRequestRecorder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

uses(Tests\TestCase::class, RefreshDatabase::class);

it('persists a successful call with response payload', function () {
  Http::fake([
    'example.test/*' => Http::response(['ok' => true, 'id' => 42], 200),
  ]);

  $log = LeadQualityValidationLog::factory()->create();
  $recorder = app(ExternalRequestRecorder::class);

  $response = $recorder->record(fn() => Http::post('https://example.test/foo', ['a' => 1]), [
    'module' => 'lead_quality',
    'service_name' => 'twilio_verify',
    'service_id' => 99,
    'operation' => 'send_challenge',
    'loggable' => $log,
    'request_method' => 'POST',
    'request_url' => 'https://example.test/foo',
    'request_body' => ['a' => 1],
  ]);

  expect($response->status())->toBe(200);

  $row = ExternalServiceRequest::first();
  expect($row->module)->toBe('lead_quality');
  expect($row->service_name)->toBe('twilio_verify');
  expect($row->service_id)->toBe(99);
  expect($row->operation)->toBe('send_challenge');
  expect($row->status)->toBe('success');
  expect($row->response_status_code)->toBe(200);
  expect($row->response_body)->toMatchArray(['ok' => true, 'id' => 42]);
  expect($row->loggable_type)->toBe(LeadQualityValidationLog::class);
  expect($row->loggable_id)->toBe($log->id);
  expect($row->duration_ms)->toBeInt();
});

it('persists a failed call when response is not successful', function () {
  Http::fake([
    'example.test/*' => Http::response(['error' => 'bad'], 400),
  ]);

  $recorder = app(ExternalRequestRecorder::class);

  $recorder->record(fn() => Http::post('https://example.test/foo'), [
    'module' => 'lead_quality',
    'service_name' => 'twilio_verify',
    'operation' => 'send_challenge',
    'request_method' => 'POST',
    'request_url' => 'https://example.test/foo',
  ]);

  $row = ExternalServiceRequest::first();
  expect($row->status)->toBe('failed');
  expect($row->response_status_code)->toBe(400);
  expect($row->error_message)->toContain('HTTP 400');
});

it('persists a timeout row and re-throws the exception', function () {
  Http::fake(function () {
    throw new ConnectionException('cURL error 28: Operation timed out');
  });

  $recorder = app(ExternalRequestRecorder::class);

  expect(
    fn() => $recorder->record(fn() => Http::timeout(1)->post('https://example.test/foo'), [
      'module' => 'lead_quality',
      'service_name' => 'twilio_verify',
      'operation' => 'send_challenge',
      'request_method' => 'POST',
      'request_url' => 'https://example.test/foo',
    ]),
  )->toThrow(ConnectionException::class);

  $row = ExternalServiceRequest::first();
  expect($row)->not->toBeNull();
  expect($row->status)->toBe('timeout');
  expect($row->error_message)->toContain('timed out');
});

it('persists an exception row and re-throws when closure throws a generic error', function () {
  $recorder = app(ExternalRequestRecorder::class);

  expect(
    fn() => $recorder->record(
      function () {
        throw new \RuntimeException('boom');
      },
      [
        'module' => 'lead_quality',
        'service_name' => 'twilio_verify',
        'operation' => 'send_challenge',
        'request_method' => 'POST',
        'request_url' => 'https://example.test/foo',
      ],
    ),
  )->toThrow(\RuntimeException::class, 'boom');

  $row = ExternalServiceRequest::first();
  expect($row->status)->toBe('exception');
  expect($row->error_message)->toBe('boom');
});
