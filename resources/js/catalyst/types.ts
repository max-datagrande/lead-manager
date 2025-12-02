// ===================================================================================
// INTERFACES Y TIPOS
// ===================================================================================

/**
 * Define la estructura del objeto de configuración que recibe el SDK.
 */
interface CatalystConfig {
  debug?: boolean;
  session?: Record<string, any>;
  environment?: 'local' | 'production' | string;
  api_url?: string;
  active?: boolean;
}

/**
 * Estructura de los datos del visitante almacenados.
 */
interface VisitorData {
  fingerprint: string;
  id?: string | number;
  lead_registered?: boolean; // Flag para saber si ya es un lead
  lead_data?: any; // Copia opcional de los datos del lead
  [key: string]: any;
}

/**
 * Estructura unificada para eventos de estado de leads.
 */
interface LeadStatusEvent {
  type: 'register' | 'update';
  success: boolean;
  data?: any;
  error?: any;
}

/**
 * Define la forma del objeto "placeholder" que existe en `window` antes de la inicialización.
 */
interface CatalystPlaceholder {
  _q: [string, ...any[]][];
  config: CatalystConfig;
}

interface EventCallback {
  (data: any): void;
}

export { type CatalystConfig, type CatalystPlaceholder, type EventCallback, type LeadStatusEvent, type VisitorData };
