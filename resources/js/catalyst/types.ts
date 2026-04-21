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
 * Options for the shareLead method.
 */
interface ShareLeadOptions {
  workflowId: number | string;
  fields?: Record<string, any>;
  createOnMiss?: boolean;
}

/**
 * Response from the share-leads dispatch endpoint.
 */
interface ShareLeadResponse {
  success: boolean;
  data: {
    dispatch_uuid?: string;
    status?: string;
    strategy_used?: string;
    final_price?: number | null;
    total_duration_ms?: number | null;
    queued?: boolean;
    workflow_id?: number;
  };
  message: string;
}

/**
 * Options for requestChallenge(). Kicks off a Lead Quality validation flow
 * (OTP, etc.) against one or more rules associated to the workflow's buyers.
 *
 * - `workflowId` is required.
 * - `fingerprint` falls back to `visitorData.fingerprint` when omitted — the
 *   server resolves the lead from it, matching the `shareLead` contract.
 * - `to`, `channel`, `locale` are provider-specific delivery options.
 * - `fields` is an optional merge-update applied to the lead atomically before
 *   the challenge is issued — saves a separate `updateLead` round-trip when
 *   the landing wants to persist context (timestamps, UX flags, last-minute
 *   UTM captures, etc.) alongside the request. If the merge fails, the whole
 *   request aborts and no challenge is emitted.
 * - `createOnMiss` lets the server create the lead when only the traffic log
 *   exists and no lead row has been registered yet. Same flag as `shareLead`
 *   — useful for one-shot landings that merge register + challenge.
 */
interface RequestChallengeOptions {
  workflowId: number | string;
  fingerprint?: string;
  to?: string;
  channel?: 'sms' | 'call' | 'email' | 'whatsapp';
  locale?: string;
  fields?: Record<string, unknown>;
  createOnMiss?: boolean;
}

interface ChallengeIssued {
  challenge_token: string;
  rule_id: number;
  rule_name: string;
  channel: string | null;
  masked_destination: string | null;
  expires_at: string;
}

interface ChallengeError {
  rule_id: number;
  rule_name: string;
  error: string;
}

/**
 * Response from POST /v1/lead-quality/challenge/send.
 * `challenges` is empty when no rules apply; the caller can proceed with
 * `shareLead()` directly.
 */
interface RequestChallengeResponse {
  success: boolean;
  message: string;
  data: {
    dispatch_id: number;
    dispatch_uuid: string;
    challenges: ChallengeIssued[];
    errors: ChallengeError[];
  };
}

/**
 * Options for verifyChallenge(). `challengeToken` comes from
 * `RequestChallengeResponse.data.challenges[i].challenge_token`.
 */
interface VerifyChallengeOptions {
  challengeToken: string;
  code: string;
  to?: string;
}

/**
 * Unified verify response. When verified is true the dispatch has been
 * transitioned to RUNNING and queued server-side.
 */
interface VerifyChallengeResponse {
  success: boolean;
  message: string;
  verified: boolean;
  status: 'verified' | 'already_verified' | 'retry' | 'failed' | 'expired' | 'already_failed' | 'invalid_token' | 'not_found' | 'error';
  retry_remaining?: number;
  reason?: string;
  dispatch_uuid?: string;
}

/**
 * Unified challenge lifecycle event. Emitted by `challenge:status`.
 */
interface ChallengeStatusEvent {
  type: 'send' | 'verify';
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

export {
  type CatalystConfig,
  type CatalystPlaceholder,
  type ChallengeError,
  type ChallengeIssued,
  type ChallengeStatusEvent,
  type EventCallback,
  type GetOfferwallOptions,
  type LeadStatusEvent,
  type Offer,
  type OfferwallConversionRequest,
  type OfferwallConversionResponse,
  type OfferwallResponse,
  type RequestChallengeOptions,
  type RequestChallengeResponse,
  type ShareLeadOptions,
  type ShareLeadResponse,
  type VerifyChallengeOptions,
  type VerifyChallengeResponse,
  type VisitorData,
  type visitorRegisterResponse,
};
