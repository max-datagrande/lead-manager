import type { SharedData } from '@/types';
import { usePage } from '@inertiajs/react';

export const DEFAULT_TIMEZONE = 'America/New_York';

export interface UseUserTimezoneResult {
  /** TZ resolved for this user: auth.user.timezone ?? DEFAULT_TIMEZONE */
  timezone: string;
  /** true when user.timezone is null and we fell back to DEFAULT_TIMEZONE */
  isDefault: boolean;
}

/**
 * Reads the logged-in user's preferred timezone from Inertia shared data.
 *
 * Consumers should destructure the field they need rather than passing the
 * hook result inline, so the shape can grow without breaking call sites:
 *
 *   const { timezone } = useUserTimezone()
 *   <DateRangePicker defaultTimezone={timezone} ... />
 */
export function useUserTimezone(): UseUserTimezoneResult {
  const { auth } = usePage<SharedData>().props;
  const stored = auth?.user?.timezone ?? null;

  return {
    timezone: stored ?? DEFAULT_TIMEZONE,
    isDefault: stored === null,
  };
}
