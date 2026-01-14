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
  [key: string]: any;
}

/*
Bad Response
{
  "success": false,
  "data": null,
  "message": "Failed to create traffic log",
  "errors": [
    {
      "message": "Failed to create traffic log",
      "file": "TrafficLogService.php",
      "line": 141
    },
    {
      "message": "Origin host is empty or not valid",
      "file": "FingerprintGeneratorService.php",
      "line": 27
    }
  ]
}
Success response
{
  "success": true,
  "data": {
    "device_type": "desktop",
    "is_bot": false
  },
  "message": "Traffic log created successfully",
  "fingerprint": "71d7d3d9475986b6c2620674a8bfe6f1c940edf8da34cb43e3cb60586bea14a9",
  "geolocation": {
    "ip": "216.131.83.235",
    "city": "New York City",
    "region": "New York",
    "region_code": "NY",
    "country": "US",
    "postal": "10013",
    "timezone": "America\/New_York",
    "currency": "USD"
  }
}
*/

type geolocationVisit = {
  ip: string;
  city: string;
  region: string;
  region_code: string;
  country: string;
  postal: string;
  timezone: string;
  currency: string;
};
interface visitorRegisterResponse {
  success: boolean;
  fingerprint?: string;
  geolocation?: geolocationVisit | null;
  data?: Record<string, any> | null;
  message?: string;
  errors?: { message: string; file: string; line: number }[];
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
 * Estructura genérica de una oferta.
 * Las claves dependen del mapeo de la integración, pero se sugieren campos comunes.
 */
interface Offer {
  title?: string;
  description?: string;
  link?: string;
  image?: string;
  payout?: number;
  currency?: string;
  [key: string]: any;
}

interface GetOfferwallOptions {
  mixId: string;
  placement?: string;
  fingerprint?: string;
  data?: object;
}

/**
 * Respuesta del endpoint de trigger de Offerwall.
 */
interface OfferwallResponse {
  success: boolean;
  message: string;
  data: Offer[];
  meta?: {
    total_offers: number;
    successful_integrations: number;
    failed_integrations: number;
    duration_ms: number;
  };
}

/**
 * Payload para el evento de conversión de Offerwall.
 */
interface OfferwallConversionRequest {
  fingerprint: string;
  offer_token: string;
  amount: number;
  offer_id?: string;
  currency?: string;
  transaction_id?: string;
  [key: string]: any;
}

/**
 * Respuesta del evento de conversión.
 */
interface OfferwallConversionResponse {
  status: string;
  message: string;
  data: any;
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

export {
  type CatalystConfig,
  type CatalystPlaceholder,
  type EventCallback,
  type LeadStatusEvent,
  type VisitorData,
  type visitorRegisterResponse,
  type Offer,
  type OfferwallResponse,
  type OfferwallConversionRequest,
  type OfferwallConversionResponse,
  type GetOfferwallOptions,
};
