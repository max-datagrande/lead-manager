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
 * Input para `updateVisit()`. Objeto plano: la landing manda `s10` (el click_id
 * que llega async via cookie `cf_click_id` cuando el trafico viene de Google
 * Ads / YouTube sin redirect de ClickFlare). El SDK agrega el `fingerprint`
 * internamente — la landing NO pasa ningun visit id. Plano y libre: parametros
 * futuros se agregan al mismo nivel (sin anidar).
 */
interface UpdateVisitData {
  s10: string;
  [key: string]: string | number | null | undefined;
}

/**
 * Codigos de error de `updateVisit()`:
 *  - `NO_ACTIVE_VISIT` → el SDK aun no tenia visita registrada al recibir la llamada.
 *  - `NETWORK_ERROR`   → fallo la persistencia contra el backend.
 *  - `UNKNOWN`         → cualquier otro fallo (input invalido, 5xx inesperado).
 */
type UpdateVisitErrorCode = 'NO_ACTIVE_VISIT' | 'NETWORK_ERROR' | 'UNKNOWN';

/**
 * Resultado de `updateVisit()`. El metodo NO es fire-and-forget: siempre
 * resuelve (nunca rejecta) con este objeto, para que la landing use `success`
 * y destrabe el CTA. `error` solo esta presente cuando `success === false`.
 */
interface UpdateVisitResult {
  success: boolean;
  fingerprint: string | null;
  s10: string | null;
  updated_at?: string | null;
  error?: {
    code: UpdateVisitErrorCode;
    message: string;
  };
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
 * Optional postback-fire config attached to a `convertOfferwall()` call.
 * When present and valid, the SDK fires an internal postback alongside the
 * conversion — fire-and-forget, BEFORE the awaited conversion request.
 *
 * The landing owns these values (the UUID lives in the landing, per offerwall
 * integration). `uuid` and `source` are required; if either is missing/empty
 * the postback is skipped with a console warning and the conversion still
 * proceeds. Empty `fields` values are dropped (and warned) before firing.
 */
interface ConvertOfferwallPostback {
  uuid: string;
  source: string;
  fields?: Record<string, string | number>;
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
 * Options for `validatePhone()`. Sync pre-submit phone validation against
 * Melissa Global Phone. Called right before `requestChallenge()` (or
 * `shareLead()`) to filter out fakes/disposables/disconnected numbers
 * before spending an SMS credit.
 *
 * The validator is **workflow-agnostic** by design — it is a global
 * utility, not a per-buyer rule. There is no `workflowId` here.
 *
 * - `phone` is required — raw user input is fine, the backend normalizes
 *   it for cache keying.
 * - `country` defaults to `US` when omitted (forwarded as `ctry` to Melissa).
 * - `fingerprint` falls back to `visitorData.fingerprint` automatically.
 */
interface ValidatePhoneOptions {
  phone: string;
  country?: string;
  fingerprint?: string;
}

/**
 * Possible classifications returned by Melissa, mapped from the upstream
 * codes (PS22, PE01, PS19, etc.). See backend `PhoneValidationResult` for
 * the canonical mapping.
 *
 * Hard-rejected when `valid: false` and classification is one of:
 *   - `invalid_phone`        → bad shape / unknown number
 *   - `disconnected_phone`   → number was disconnected
 *   - `high_risk_phone`      → disposable / temporary phone
 *
 * Soft-accepted (`valid: true`) classifications:
 *   - `valid_high_confidence` (PS22 — Premium real-time confirmed)
 *   - `valid_low_confidence`  (PS01)
 *   - `low_confidence`        (PS20 only)
 *   - `compliance_risk`       (PS18 — DNC, surface to caller)
 *   - `pending_or_timeout`    (PS30 — Premium timeout, treat optimistically)
 *
 * `validation_error` is technical (license issue, upstream timeout, no
 * provider configured); the SDK throws when this is the result. The caller
 * decides what to do — block the submit, let the flow continue, retry, log
 * silently, whatever the landing's policy is. The SDK is agnostic.
 */
type ValidatePhoneClassification =
  | 'valid_high_confidence'
  | 'valid_low_confidence'
  | 'low_confidence'
  | 'compliance_risk'
  | 'pending_or_timeout'
  | 'invalid_phone'
  | 'disconnected_phone'
  | 'high_risk_phone'
  | 'validation_error';

/**
 * Response from POST /v1/lead-quality/phone/validate. The SDK flattens the
 * envelope so the caller can read `valid` directly.
 */
interface ValidatePhoneResponse {
  success: boolean;
  message: string;
  valid: boolean;
  classification: ValidatePhoneClassification;
  line_type?: 'cellular' | 'landline' | 'voip' | null;
  country?: string | null;
  carrier?: string | null;
  normalized_phone?: string | null;
  error?: string | null;
}

/**
 * Emitted on `phone:status` for analytics / loaders. Mirrors the shape of
 * `ChallengeStatusEvent` but with `type: 'validate'`.
 */
interface PhoneStatusEvent {
  type: 'validate';
  success: boolean;
  data?: ValidatePhoneResponse;
  error?: any;
}

/**
 * Options for `firePostback()`. Fires an internal postback against
 * GET /v1/postback/fire/{uuid}/{fingerprint}/{source}?<fields>.
 *
 * - `uuid` is the internal postback UUID (required).
 * - `source` is the PostbackSource value that fills the `{source}` path
 *   segment (e.g. 'manual', 'offerwall'). Passed through as-is; the backend
 *   validates it (422 on an unknown value).
 * - `fields` are sent as flat query params; the backend persists each one
 *   that matches a Field name on the lead resolved from the fingerprint.
 * - `fingerprint` falls back to `visitorData.fingerprint` automatically,
 *   matching the rest of the SDK.
 */
interface FirePostbackOptions {
  uuid: string;
  source: string;
  fields?: Record<string, string | number>;
  fingerprint?: string;
}

/**
 * Flattened response from the internal postback fire endpoint. The SDK
 * lifts `execution_uuid` / `status` out of the envelope so the caller can
 * read them at the top level (mirrors verifyChallenge/validatePhone).
 */
interface FirePostbackResponse {
  success: boolean;
  message: string;
  executionUuid?: string;
  status?: string;
}

/**
 * Emitted on `postback:status` for analytics / loaders. Mirrors the shape
 * of `ShareStatusEvent` / `ChallengeStatusEvent`.
 */
interface PostbackStatusEvent {
  success: boolean;
  data?: FirePostbackResponse;
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
  type ConvertOfferwallPostback,
  type EventCallback,
  type FirePostbackOptions,
  type FirePostbackResponse,
  type GetOfferwallOptions,
  type LeadStatusEvent,
  type Offer,
  type OfferwallConversionRequest,
  type OfferwallConversionResponse,
  type OfferwallResponse,
  type PhoneStatusEvent,
  type PostbackStatusEvent,
  type RequestChallengeOptions,
  type RequestChallengeResponse,
  type ShareLeadOptions,
  type ShareLeadResponse,
  type UpdateVisitData,
  type UpdateVisitErrorCode,
  type UpdateVisitResult,
  type ValidatePhoneClassification,
  type ValidatePhoneOptions,
  type ValidatePhoneResponse,
  type VerifyChallengeOptions,
  type VerifyChallengeResponse,
  type VisitorData,
  type visitorRegisterResponse,
};
