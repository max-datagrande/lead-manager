<?php

use App\Enums\LeadQuality\ValidationLogStatus;
use App\Models\ExternalServiceRequest;
use App\Models\LeadQualityValidationLog;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

it('casts status to enum and context to array', function () {
  $log = LeadQualityValidationLog::factory()->create();

  expect($log->status)->toBeInstanceOf(ValidationLogStatus::class);
  expect($log->context)->toBeArray();
});

it('marks as verified updates status result and resolved_at', function () {
  $log = LeadQualityValidationLog::factory()->sent()->create();

  $log->markVerified('Approved');

  $log->refresh();
  expect($log->status)->toBe(ValidationLogStatus::VERIFIED);
  expect($log->result)->toBe('pass');
  expect($log->resolved_at)->not->toBeNull();
  expect($log->message)->toBe('Approved');
});

it('marks as failed updates status and captures message', function () {
  $log = LeadQualityValidationLog::factory()->sent()->create();

  $log->markFailed('Invalid code');

  $log->refresh();
  expect($log->status)->toBe(ValidationLogStatus::FAILED);
  expect($log->message)->toBe('Invalid code');
});

it('marks as expired', function () {
  $log = LeadQualityValidationLog::factory()->sent()->create();

  $log->markExpired();

  expect($log->fresh()->status)->toBe(ValidationLogStatus::EXPIRED);
});

it('increments attempt counter', function () {
  $log = LeadQualityValidationLog::factory()->create(['attempts_count' => 0]);

  $log->incrementAttempt();
  $log->incrementAttempt();

  expect($log->fresh()->attempts_count)->toBe(2);
});

it('detects expired status via expires_at', function () {
  $expired = LeadQualityValidationLog::factory()->create(['expires_at' => now()->subMinute()]);
  $valid = LeadQualityValidationLog::factory()->create(['expires_at' => now()->addMinutes(10)]);

  expect($expired->isExpired())->toBeTrue();
  expect($valid->isExpired())->toBeFalse();
});

it('has polymorphic relation to external requests', function () {
  $log = LeadQualityValidationLog::factory()->create();

  $log->externalRequests()->create(
    ExternalServiceRequest::factory()
      ->make([
        'loggable_type' => null,
        'loggable_id' => null,
      ])
      ->toArray(),
  );

  expect($log->externalRequests()->count())->toBe(1);
});

it('isPending enum helper covers pending and sent', function () {
  expect(ValidationLogStatus::PENDING->isPending())->toBeTrue();
  expect(ValidationLogStatus::SENT->isPending())->toBeTrue();
  expect(ValidationLogStatus::VERIFIED->isPending())->toBeFalse();
  expect(ValidationLogStatus::FAILED->isPending())->toBeFalse();
});
