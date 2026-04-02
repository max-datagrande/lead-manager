export type Locale = 'en' | 'es';

export type TranslationEntry = {
  en: string;
  es: string;
};

export type Dictionary = {
  [section: string]: {
    [key: string]: TranslationEntry;
  };
};
