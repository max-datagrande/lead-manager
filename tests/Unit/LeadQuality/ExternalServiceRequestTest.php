<?php

use App\Models\ExternalServiceRequest;
use App\Models\LeadQualityValidationLog;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

it('casts json columns to arrays', function () {
  $req = ExternalServiceRequest::factory()->create();

  expect($req->request_headers)->toBeArray();
  expect($req->request_body)->toBeArray();
  expect($req->response_body)->toBeArray();
});

it('attaches to a loggable via polymorphic relation', function () {
  $log = LeadQualityValidationLog::factory()->create();
  $req = ExternalServiceRequest::factory()->create([
    'loggable_type' => LeadQualityValidationLog::class,
    'loggable_id' => $log->id,
  ]);

  expect($req->loggable)->not->toBeNull();
  expect($req->loggable->id)->toBe($log->id);
  expect($log->externalRequests()->first()->id)->toBe($req->id);
});

it('supports failed state', function () {
  $req = ExternalServiceRequest::factory()->failed()->create();

  expect($req->status)->toBe('failed');
  expect($req->error_message)->not->toBeNull();
});

it('supports timeout state', function () {
  $req = ExternalServiceRequest::factory()->timeout()->create();

  expect($req->status)->toBe('timeout');
  expect($req->response_status_code)->toBeNull();
});

it('separates requests by module and service_name', function () {
  ExternalServiceRequest::factory()->create(['module' => 'lead_quality', 'service_name' => 'twilio_verify']);
  ExternalServiceRequest::factory()->create(['module' => 'lead_quality', 'service_name' => 'ipqs']);
  ExternalServiceRequest::factory()->create(['module' => 'postbacks', 'service_name' => 'sendgrid']);

  expect(ExternalServiceRequest::where('module', 'lead_quality')->count())->toBe(2);
  expect(ExternalServiceRequest::where('service_name', 'twilio_verify')->count())->toBe(1);
});
