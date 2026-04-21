<?php

use App\Enums\DispatchStatus;
use App\Enums\LeadQuality\ValidationLogStatus;
use App\Models\Lead;
use App\Models\LeadDispatch;
use App\Models\LeadQualityValidationLog;
use App\Models\Workflow;

use function Pest\Laravel\artisan;

function seedPendingDispatchWithLog(array $logOverrides = []): array
{
  $workflow = Workflow::factory()->bestBid()->create();
  $lead = Lead::factory()->create(['fingerprint' => 'fp-expire-' . uniqid()]);
  $dispatch = LeadDispatch::create([
    'workflow_id' => $workflow->id,
    'lead_id' => $lead->id,
    'fingerprint' => $lead->fingerprint,
    'status' => DispatchStatus::PENDING_VALIDATION,
    'strategy_used' => $workflow->strategy?->value,
    'started_at' => now(),
  ]);

  $log = LeadQualityValidationLog::factory()->create(
    array_merge(
      [
        'lead_dispatch_id' => $dispatch->id,
        'fingerprint' => $dispatch->fingerprint,
        'status' => ValidationLogStatus::SENT,
        'challenge_reference' => 'VE-exp',
        'expires_at' => now()->subMinute(),
      ],
      $logOverrides,
    ),
  );

  return [$dispatch, $log];
}

it('expires dispatches whose latest log has passed its ttl', function () {
  [$dispatch, $log] = seedPendingDispatchWithLog();

  artisan('lead-quality:expire-validation')->assertSuccessful();

  expect($dispatch->fresh()->status)->toBe(DispatchStatus::VALIDATION_FAILED);
  expect($log->fresh()->status)->toBe(ValidationLogStatus::EXPIRED);
});

it('leaves dispatches alone when the log is still within ttl', function () {
  [$dispatch, $log] = seedPendingDispatchWithLog([
    'expires_at' => now()->addMinutes(5),
  ]);

  artisan('lead-quality:expire-validation')->assertSuccessful();

  expect($dispatch->fresh()->status)->toBe(DispatchStatus::PENDING_VALIDATION);
  expect($log->fresh()->status)->toBe(ValidationLogStatus::SENT);
});

it('does not touch logs already marked FAILED', function () {
  [$dispatch, $log] = seedPendingDispatchWithLog([
    'status' => ValidationLogStatus::FAILED,
    'result' => 'fail',
    'expires_at' => now()->subMinute(),
  ]);

  artisan('lead-quality:expire-validation')->assertSuccessful();

  expect($dispatch->fresh()->status)->toBe(DispatchStatus::VALIDATION_FAILED);
  expect($log->fresh()->status)->toBe(ValidationLogStatus::FAILED); // unchanged
});

it('ignores non-pending dispatches even if their logs expired', function () {
  [$dispatch] = seedPendingDispatchWithLog();
  $dispatch->update(['status' => DispatchStatus::SOLD]);

  artisan('lead-quality:expire-validation')->assertSuccessful();

  expect($dispatch->fresh()->status)->toBe(DispatchStatus::SOLD);
});
