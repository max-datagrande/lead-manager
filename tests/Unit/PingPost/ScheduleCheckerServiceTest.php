<?php

use App\Models\Buyer;
use App\Models\BuyerConfig;
use App\Models\BuyerScheduleWindow;
use App\Models\Integration;
use App\Services\PingPost\ScheduleCheckerService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
  $this->service = app(ScheduleCheckerService::class);
});

function makeBuyer(?string $timezone = null): Buyer
{
  $integration = Integration::factory()->pingPost()->create();
  $buyer = Buyer::create([
    'name' => 'Test Buyer',
    'integration_id' => $integration->id,
    'is_active' => true,
  ]);

  BuyerConfig::create([
    'integration_id' => $integration->id,
    'price_source' => 'fixed',
    'fixed_price' => 1.0,
    'schedule_timezone' => $timezone,
  ]);

  return $buyer->fresh(['buyerConfig', 'scheduleWindows']);
}

it('treats buyers with no windows as always within schedule', function (): void {
  $buyer = makeBuyer();

  expect($this->service->isWithinSchedule($buyer, Carbon::parse('2026-05-11 03:00:00', 'UTC')))->toBeTrue();
  expect($this->service->getSkipReason($buyer, Carbon::parse('2026-05-11 03:00:00', 'UTC')))->toBeNull();
});

it('returns true when current time falls inside a window in the configured timezone', function (): void {
  // 14:00 UTC = 10:00 America/New_York (DST, May)
  $buyer = makeBuyer('America/New_York');

  BuyerScheduleWindow::create([
    'buyer_id' => $buyer->id,
    'days_of_week' => [1], // Monday
    'start_time' => '09:00',
    'end_time' => '17:00',
    'sort_order' => 0,
  ]);

  // May 11, 2026 is a Monday
  $now = Carbon::parse('2026-05-11 14:00:00', 'UTC');

  expect($this->service->isWithinSchedule($buyer->fresh(['buyerConfig', 'scheduleWindows']), $now))->toBeTrue();
});

it('returns false when current time is outside every window', function (): void {
  $buyer = makeBuyer('America/New_York');

  BuyerScheduleWindow::create([
    'buyer_id' => $buyer->id,
    'days_of_week' => [1, 2, 3, 4, 5],
    'start_time' => '09:00',
    'end_time' => '17:00',
    'sort_order' => 0,
  ]);

  // 02:00 UTC Monday = 22:00 Sunday America/New_York — outside every window
  $now = Carbon::parse('2026-05-11 02:00:00', 'UTC');

  $fresh = $buyer->fresh(['buyerConfig', 'scheduleWindows']);
  expect($this->service->isWithinSchedule($fresh, $now))->toBeFalse();
  expect($this->service->getSkipReason($fresh, $now))->toContain('Outside schedule window');
});

it('matches the correct day of week after timezone conversion', function (): void {
  $buyer = makeBuyer('America/New_York');

  // Window only Monday 08:30-19:00
  BuyerScheduleWindow::create([
    'buyer_id' => $buyer->id,
    'days_of_week' => [1],
    'start_time' => '08:30',
    'end_time' => '19:00',
    'sort_order' => 0,
  ]);

  // Tuesday 12:00 UTC = Tuesday 08:00 NY — same calendar day but wrong day-of-week (Tuesday not Monday)
  $tuesday = Carbon::parse('2026-05-12 12:00:00', 'UTC');

  expect($this->service->isWithinSchedule($buyer->fresh(['buyerConfig', 'scheduleWindows']), $tuesday))->toBeFalse();

  // Monday 13:00 UTC = Monday 09:00 NY — within window
  $monday = Carbon::parse('2026-05-11 13:00:00', 'UTC');

  expect($this->service->isWithinSchedule($buyer->fresh(['buyerConfig', 'scheduleWindows']), $monday))->toBeTrue();
});

it('handles multiple windows on different days for the same buyer', function (): void {
  $buyer = makeBuyer('America/New_York');

  // Mondays 08:30-19:00, Tuesday-Friday 10:30-20:00 (IBC case from the slack screenshot)
  BuyerScheduleWindow::create([
    'buyer_id' => $buyer->id,
    'days_of_week' => [1],
    'start_time' => '08:30',
    'end_time' => '19:00',
    'sort_order' => 0,
  ]);
  BuyerScheduleWindow::create([
    'buyer_id' => $buyer->id,
    'days_of_week' => [2, 3, 4, 5],
    'start_time' => '10:30',
    'end_time' => '20:00',
    'sort_order' => 1,
  ]);

  $monday0900Ny = Carbon::parse('2026-05-11 13:00:00', 'UTC'); // Mon 09:00 NY — first window
  $tuesday0900Ny = Carbon::parse('2026-05-12 13:00:00', 'UTC'); // Tue 09:00 NY — BEFORE second window starts
  $tuesday1200Ny = Carbon::parse('2026-05-12 16:00:00', 'UTC'); // Tue 12:00 NY — inside second window

  $fresh = $buyer->fresh(['buyerConfig', 'scheduleWindows']);

  expect($this->service->isWithinSchedule($fresh, $monday0900Ny))->toBeTrue();
  expect($this->service->isWithinSchedule($fresh, $tuesday0900Ny))->toBeFalse();
  expect($this->service->isWithinSchedule($fresh, $tuesday1200Ny))->toBeTrue();
});

it('defaults to America/New_York when buyer has no timezone configured but has windows', function (): void {
  $buyer = makeBuyer(null);

  BuyerScheduleWindow::create([
    'buyer_id' => $buyer->id,
    'days_of_week' => [1],
    'start_time' => '09:00',
    'end_time' => '17:00',
    'sort_order' => 0,
  ]);

  // Monday 14:00 UTC = Monday 10:00 NY (DST) — within window
  $now = Carbon::parse('2026-05-11 14:00:00', 'UTC');

  expect($this->service->isWithinSchedule($buyer->fresh(['buyerConfig', 'scheduleWindows']), $now))->toBeTrue();
});
