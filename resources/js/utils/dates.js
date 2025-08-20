/**
 * Obtiene el locale desde las variables de entorno de Vite
 * Si no está definido, detecta automáticamente
 */
function getLocaleFromEnv() {
  // Obtener locale desde variable de entorno
  const envLocale = import.meta.env.VITE_APP_LOCALE;
  
  if (envLocale) {
    return envLocale;
  }
  
  // Fallback: detección automática si no está configurado en .env
  const timeZone = Intl.DateTimeFormat().resolvedOptions().timeZone;
  const browserLanguage = navigator.language || navigator.languages[0];
  
  // Colombia
  if (timeZone === 'America/Bogota' || browserLanguage === 'es-CO') {
    return 'es-CO';
  }
  
  // Otros países de habla hispana
  if (browserLanguage.startsWith('es')) {
    return 'es-ES';
  }
  
  return 'en-US';
}

const LOCALE = getLocaleFromEnv();

const LOCALE_OPTIONS = {
  year: 'numeric',
  month: '2-digit',
  day: '2-digit',
  hour: '2-digit',
  minute: '2-digit',
  second: '2-digit',
  hour12: false,
};

export default function getDateString(date) {
  if (!date) return '';
  if (typeof date === 'string') date = new Date(date);
  return date.toLocaleString(LOCALE, LOCALE_OPTIONS);
}

// Exportar el locale para uso en otros componentes
export { LOCALE };
