<?php

namespace App\Services\PingPost;

use App\Models\Buyer;
use App\Models\BuyerScheduleWindow;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class ScheduleCheckerService
{
  public const DEFAULT_TIMEZONE = 'America/New_York';

  /**
   * Whether the buyer is allowed to receive leads at the given moment.
   *
   * No windows configured -> always within schedule (24/7).
   */
  public function isWithinSchedule(Buyer $buyer, ?Carbon $now = null): bool
  {
    $windows = $this->loadWindows($buyer);

    if ($windows->isEmpty()) {
      return true;
    }

    $localNow = $this->localizeNow($buyer, $now);
    $currentDay = (int) $localNow->dayOfWeek; // 0=Sunday .. 6=Saturday
    $currentTime = $localNow->format('H:i:s');

    foreach ($windows as $window) {
      if (!in_array($currentDay, $this->normalizeDays($window->days_of_week), true)) {
        continue;
      }

      $start = $this->normalizeTime($window->start_time);
      $end = $this->normalizeTime($window->end_time);

      if ($currentTime >= $start && $currentTime < $end) {
        return true;
      }
    }

    return false;
  }

  /**
   * Human-readable reason describing why the buyer is outside its schedule.
   *
   * Returns null when within schedule (or no windows configured).
   */
  public function getSkipReason(Buyer $buyer, ?Carbon $now = null): ?string
  {
    $windows = $this->loadWindows($buyer);

    if ($windows->isEmpty() || $this->isWithinSchedule($buyer, $now)) {
      return null;
    }

    $localNow = $this->localizeNow($buyer, $now);
    $timezone = $this->resolveTimezone($buyer);
    $currentLabel = $localNow->format('D H:i');
    $windowLabels = $windows->map(fn(BuyerScheduleWindow $w): string => $this->formatWindow($w))->implode(', ');

    return "Outside schedule window (current: {$currentLabel} {$timezone}; windows: {$windowLabels})";
  }

  private function loadWindows(Buyer $buyer): Collection
  {
    if ($buyer->relationLoaded('scheduleWindows')) {
      return $buyer->scheduleWindows;
    }

    return $buyer->scheduleWindows()->get();
  }

  private function localizeNow(Buyer $buyer, ?Carbon $now): CarbonImmutable
  {
    $base = $now ? CarbonImmutable::instance($now) : CarbonImmutable::now();

    return $base->setTimezone($this->resolveTimezone($buyer));
  }

  private function resolveTimezone(Buyer $buyer): string
  {
    $tz = $buyer->buyerConfig?->schedule_timezone;

    return $tz !== null && $tz !== '' ? $tz : self::DEFAULT_TIMEZONE;
  }

  /**
   * Normalize the days_of_week cast value into a list of ints (0=Sunday..6=Saturday).
   */
  private function normalizeDays(mixed $days): array
  {
    if (!is_array($days)) {
      return [];
    }

    return array_values(array_map(fn($d): int => (int) $d, $days));
  }

  /**
   * Normalize a time value (MySQL TIME or "HH:MM"/"HH:MM:SS") to "HH:MM:SS".
   */
  private function normalizeTime(string $value): string
  {
    return strlen($value) === 5 ? $value . ':00' : $value;
  }

  private function formatWindow(BuyerScheduleWindow $window): string
  {
    $dayLabels = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
    $days = collect($this->normalizeDays($window->days_of_week))
      ->map(fn(int $d): string => $dayLabels[$d] ?? (string) $d)
      ->implode('/');

    $start = substr($this->normalizeTime($window->start_time), 0, 5);
    $end = substr($this->normalizeTime($window->end_time), 0, 5);

    return "{$days} {$start}-{$end}";
  }
}
