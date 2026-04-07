import dictionary from '@/i18n/docs-catalyst';
import type { Locale } from '@/i18n/types';
import { useCallback, useState } from 'react';

const STORAGE_KEY = 'docs_locale';
const DEFAULT_LOCALE: Locale = 'en';

function getStoredLocale(): Locale {
  if (typeof window === 'undefined') return DEFAULT_LOCALE;
  const stored = localStorage.getItem(STORAGE_KEY);
  return stored === 'en' || stored === 'es' ? stored : DEFAULT_LOCALE;
}

export function useLocale() {
  const [locale, setLocaleState] = useState<Locale>(getStoredLocale);

  const setLocale = useCallback((newLocale: Locale) => {
    setLocaleState(newLocale);
    localStorage.setItem(STORAGE_KEY, newLocale);
  }, []);

  const t = useCallback(
    (key: string): string => {
      const parts = key.split('.');
      if (parts.length !== 2) return key;

      const [section, field] = parts;
      const entry = dictionary[section]?.[field];

      if (!entry) return key;
      return entry[locale] ?? entry.en ?? key;
    },
    [locale],
  );

  return { locale, setLocale, t } as const;
}
