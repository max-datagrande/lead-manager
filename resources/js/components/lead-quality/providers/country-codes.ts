/**
 * Curated list of dial codes for the OTP tester. Not exhaustive — just the
 * countries most relevant to our lead-gen operation plus the big English
 * markets. If a destination doesn't fit, fall back to "Other" and the admin
 * can paste the full E.164 number (including the `+`) directly.
 */
export interface CountryCode {
  code: string; // ISO 3166-1 alpha-2, used as Select value
  name: string;
  dial: string; // Includes the leading +
}

export const COUNTRY_CODES: readonly CountryCode[] = [
  { code: 'US', name: 'United States', dial: '+1' },
  { code: 'CA', name: 'Canada', dial: '+1' },
  { code: 'MX', name: 'Mexico', dial: '+52' },
  { code: 'AR', name: 'Argentina', dial: '+54' },
  { code: 'BR', name: 'Brazil', dial: '+55' },
  { code: 'CO', name: 'Colombia', dial: '+57' },
  { code: 'CL', name: 'Chile', dial: '+56' },
  { code: 'PE', name: 'Peru', dial: '+51' },
  { code: 'VE', name: 'Venezuela', dial: '+58' },
  { code: 'UY', name: 'Uruguay', dial: '+598' },
  { code: 'PY', name: 'Paraguay', dial: '+595' },
  { code: 'BO', name: 'Bolivia', dial: '+591' },
  { code: 'EC', name: 'Ecuador', dial: '+593' },
  { code: 'ES', name: 'Spain', dial: '+34' },
  { code: 'GB', name: 'United Kingdom', dial: '+44' },
  { code: 'DE', name: 'Germany', dial: '+49' },
  { code: 'FR', name: 'France', dial: '+33' },
  { code: 'IT', name: 'Italy', dial: '+39' },
  { code: 'PT', name: 'Portugal', dial: '+351' },
  { code: 'AU', name: 'Australia', dial: '+61' },
  { code: 'OTHER', name: 'Other (paste full E.164)', dial: '' },
] as const;

export const DEFAULT_COUNTRY: CountryCode = COUNTRY_CODES[0];

export function findCountry(code: string): CountryCode | undefined {
  return COUNTRY_CODES.find((c) => c.code === code);
}

/**
 * Strips the leading +, spaces, dashes, parens from a local number fragment.
 * Keeps only digits. For `OTHER` we trust the admin to paste the full string.
 */
export function sanitizeLocal(value: string): string {
  return value.replace(/[^\d]/g, '');
}
