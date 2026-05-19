import { fromZonedTime, toZonedTime } from 'date-fns-tz';

/**
 * Converts a "wall clock" date (as the user sees it in the picker)
 * into an ISO UTC string, interpreting it as time in the given IANA TZ.
 *
 * Example: localDateToUtcIso(new Date('2026-05-12T00:00:00'), 'America/New_York')
 *   → '2026-05-12T04:00:00.000Z'
 */
export function localDateToUtcIso(date: Date, timezone: string): string {
  return fromZonedTime(date, timezone).toISOString();
}

/**
 * Converts an ISO UTC string back into a Date whose local fields
 * match the wall-clock representation in the given IANA TZ.
 * Useful to hydrate the picker from a backend filter.
 */
export function utcIsoToLocalDate(iso: string, timezone: string): Date {
  return toZonedTime(iso, timezone);
}

/**
 * Inclusive day count between two dates (range length shown in the picker header).
 */
export function rangeDays(from: Date, to: Date): number {
  const ms = 24 * 60 * 60 * 1000;
  const start = new Date(from.getFullYear(), from.getMonth(), from.getDate()).getTime();
  const end = new Date(to.getFullYear(), to.getMonth(), to.getDate()).getTime();
  return Math.round((end - start) / ms) + 1;
}
