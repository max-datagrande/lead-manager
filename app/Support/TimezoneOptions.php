<?php

namespace App\Support;

class TimezoneOptions
{
  /**
   * Decorated list of supported timezones for UI selectors.
   *
   * Source: config('timezones.schedule').
   * UTC offsets are computed at runtime so labels stay accurate across
   * DST transitions without manual maintenance.
   *
   * @return array<int, array{value: string, label: string, name: string, offset: string|null, description: string|null}>
   */
  public static function all(): array
  {
    return array_map(function (array $tz): array {
      $name = $tz['prefix'] ?? str_replace('_', ' ', $tz['value']);
      $offset = $tz['value'] === 'UTC' ? null : self::currentUtcOffset($tz['value']);
      $description = $tz['description'] ?? null;

      // Flat string for SearchableSelect fuzzy search.
      $label = $name . ($offset ? " ({$offset})" : '') . ($description ? " {$description}" : '');

      return [
        'value' => $tz['value'],
        'label' => $label,
        'name' => $name,
        'offset' => $offset,
        'description' => $description,
      ];
    }, config('timezones.schedule', []));
  }

  /**
   * Plain list of IANA values, for validation.
   *
   * @return array<int, string>
   */
  public static function values(): array
  {
    return array_column(config('timezones.schedule', []), 'value');
  }

  public static function isValid(string $timezone): bool
  {
    return in_array($timezone, self::values(), true);
  }

  private static function currentUtcOffset(string $timezone): string
  {
    $offsetSeconds = (new \DateTimeZone($timezone))->getOffset(new \DateTimeImmutable('now', new \DateTimeZone('UTC')));
    $hours = intdiv($offsetSeconds, 3600);
    $minutes = abs(intdiv($offsetSeconds % 3600, 60));
    $sign = $hours >= 0 ? '+' : '-';

    $hoursStr = (string) abs($hours);

    return $minutes === 0 ? "UTC{$sign}{$hoursStr}" : sprintf('UTC%s%s:%02d', $sign, $hoursStr, $minutes);
  }
}
