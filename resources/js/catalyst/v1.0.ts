import { API_ROUTES } from './routes';
import {
  CatalystConfig,
  CatalystPlaceholder,
  ChallengeStatusEvent,
  EventCallback,
  GetOfferwallOptions,
  LeadStatusEvent,
  OfferwallConversionRequest,
  OfferwallConversionResponse,
  OfferwallResponse,
  PhoneStatusEvent,
  RequestChallengeOptions,
  RequestChallengeResponse,
  ShareLeadOptions,
  ShareLeadResponse,
  ValidatePhoneOptions,
  ValidatePhoneResponse,
  VerifyChallengeOptions,
  VerifyChallengeResponse,
  VisitorData,
  visitorRegisterResponse,
} from './types';
/**
 * Extiende la interfaz global `Window` para que TypeScript conozca `window.Catalyst`.
 * Puede ser la instancia real (CatalystCore) o el placeholder.
 */
declare global {
  interface Window {
    Catalyst: CatalystCore | CatalystPlaceholder;
  }
}

// ===================================================================================
// CLASE PRINCIPAL DEL SDK
// ===================================================================================

class CatalystCore {
  config: CatalystConfig;
  listeners: Record<string, EventCallback[]>;
  landingId: string | null;
  visitorData: VisitorData | null = null;
  isReady: boolean = false;

  // Constantes para Storage y Cookies
  private readonly STORAGE_KEY = 'catalyst_visitor_session';
  private readonly THROTTLE_COOKIE = 'catalyst_throttle';
  private readonly THROTTLE_MINUTES = 15;

  constructor(config: CatalystConfig) {
    this.config = config;
    this.listeners = {}; // Almacén para los listeners de eventos
    this.landingId = this.getLandingId();

    if (this.config.debug) {
      this.enableDebugMode();
    }

    // Registrar escuchas de eventos internos para acciones de leads
    this.on('lead:register', (data) => this.registerLead(data));
    this.on('lead:update', (data) => this.updateLead(data));
  }

  // ===================================================================================
  // LÓGICA DE VISITANTE (Visitor)
  // ===================================================================================

  /**
   * Inicializa al visitante. Revisa si existe una sesión válida (cookie throttle).
   * Si existe, carga de localStorage. Si no, registra una nueva visita en la API.
   * Retorna una Promesa con los datos del visitante.
   */
  public async initVisitor(): Promise<VisitorData> {
    const hasThrottle = this.getCookie(this.THROTTLE_COOKIE);
    const storedData = localStorage.getItem(this.STORAGE_KEY);

    if (hasThrottle && storedData) {
      // CASO 1: Reingreso a corto plazo (dentro de los 15 mins). Usamos datos locales.
      try {
        this.visitorData = JSON.parse(storedData);
        if (this.config.debug) console.log('Catalyst SDK: Visitante recuperado de caché (Throttle activo).');
        return this.visitorData;
      } catch (e) {
        console.error('Catalyst SDK: Error leyendo datos locales, reiniciando visitante.', e);
      }
    }
    // CASO 2: No hay throttle o no hay datos. Registramos nueva visita (o renovamos).
    return await this.registerNewVisitor();
  }

  /**
   * Realiza la petición a la API para registrar al visitante.
   */
  private async registerNewVisitor(): Promise<VisitorData> {
    if (!this.config.api_url) {
      throw new Error('Catalyst SDK: api_url has not been configured. The visitor cannot be registered.');
    }

    const payload = {
      landing_id: this.landingId,
      user_agent: navigator.userAgent,
      referer: this.getReferer() ?? null,
      current_page: window.location.pathname,
      query_params: Object.fromEntries(new URLSearchParams(window.location.search)),
    };
    let headers = {
      'Content-Type': 'application/json',
      Accept: 'application/json',
    };
    if (this.config.debug && this.config?.dev_origin) {
      headers['Dev-Origin'] = this.config.dev_origin;
    }
    try {
      const res = await fetch(this.getEndpoint('visitor.register'), {
        method: 'POST',
        headers: headers,
        body: JSON.stringify(payload),
      });

      if (!res.ok) throw new Error(`HTTP error! status: ${res.status}`);

      const json: visitorRegisterResponse = await res.json();

      if (!json.success || !json.fingerprint) {
        throw new Error(json.message || 'Error desconocido al registrar visitante');
      }

      // Mapeamos la respuesta de la API a nuestra estructura interna VisitorData
      const visitorData: VisitorData = {
        fingerprint: json.fingerprint,
        ...json.data, // Incluimos device_type, is_bot, etc.
        geolocation: json.geolocation, // Incluimos geolocalización si existe
      };

      // Guardamos en memoria y persistencia
      this.saveVisitorSession(visitorData);
      return visitorData;
    } catch (error) {
      console.error('Catalyst SDK: Error registrando visitante:', error);
      throw error;
    }
  }

  /**
   * Guarda la sesión del visitante y establece la cookie de throttle.
   */
  private saveVisitorSession(data: VisitorData) {
    this.visitorData = data;
    localStorage.setItem(this.STORAGE_KEY, JSON.stringify(data));
    this.setCookie(this.THROTTLE_COOKIE, '1', this.THROTTLE_MINUTES);
  }

  // ===================================================================================
  // LÓGICA DE LEADS (Register & Update)
  // ===================================================================================

  /**
   * Registra un lead asociado al visitante actual.
   * Puede ser llamado directamente o vía evento 'lead:register'.
   */
  async registerLead(fields: Record<string, any> = {}) {
    if (!this.visitorData?.fingerprint) {
      console.warn('Catalyst SDK: No hay fingerprint de visitante. Esperando inicialización...');
      this.dispatch('lead:status', {
        type: 'register',
        success: false,
        error: 'No visitor fingerprint available',
      } as LeadStatusEvent);
      return;
    }

    // Si ya está registrado localmente, podríamos decidir si actualizar o ignorar.
    // Aquí asumimos que siempre intentamos registrar si se pide.

    const payload = {
      fingerprint: this.visitorData.fingerprint,
      fields: this.sanitizeFields(fields),
    };

    try {
      const res = await fetch(this.getEndpoint('leads.register'), {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          Accept: 'application/json',
        },
        body: JSON.stringify(payload),
      });

      if (!res.ok) {
        const errorBody = await res.text(); // Intentar leer el cuerpo del error
        throw new Error(`HTTP ${res.status}: ${errorBody}`);
      }

      const json = await res.json();

      // Actualizamos nuestro almacenamiento local para saber que este visitante ya es lead
      if (this.visitorData) {
        this.visitorData.lead_registered = true;
        this.visitorData.lead_data = json;
        this.saveVisitorSession(this.visitorData); // Actualiza storage
      }

      if (this.config.debug) console.log('Catalyst SDK: Lead registrado exitosamente.', json);

      // Emitimos evento UNIFICADO de éxito
      this.dispatch('lead:status', {
        type: 'register',
        success: true,
        data: json,
      } as LeadStatusEvent);
    } catch (error) {
      console.error('Catalyst SDK: Error registrando lead:', error);
      // Emitimos evento UNIFICADO de error
      this.dispatch('lead:status', {
        type: 'register',
        success: false,
        error: error instanceof Error ? error.message : error,
      } as LeadStatusEvent);
    }
  }

  /**
   * Actualiza un lead existente.
   * Puede ser llamado directamente o vía evento 'lead:update'.
   */
  async updateLead(fields: Record<string, any>) {
    if (!this.visitorData?.fingerprint) {
      console.warn('Catalyst SDK: No hay fingerprint para actualizar el lead.');
      this.dispatch('lead:status', {
        type: 'update',
        success: false,
        error: 'No visitor fingerprint available',
      } as LeadStatusEvent);
      return;
    }

    const payload = {
      fingerprint: this.visitorData.fingerprint,
      fields: this.sanitizeFields(fields),
    };

    try {
      const res = await fetch(this.getEndpoint('leads.update'), {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          Accept: 'application/json',
        },
        body: JSON.stringify(payload),
      });

      if (!res.ok) {
        const errorBody = await res.text();
        throw new Error(`HTTP ${res.status}: ${errorBody}`);
      }

      const json = await res.json();

      // Actualizamos datos locales si es necesario
      if (this.visitorData) {
        this.visitorData.lead_data = { ...this.visitorData.lead_data, ...json };
        this.saveVisitorSession(this.visitorData);
      }

      if (this.config.debug) console.log('Catalyst SDK: Lead actualizado exitosamente.', json);

      // Emitimos evento UNIFICADO de éxito
      this.dispatch('lead:status', {
        type: 'update',
        success: true,
        data: json,
      } as LeadStatusEvent);
    } catch (error) {
      console.error('Catalyst SDK: Error actualizando lead:', error);
      // Emitimos evento UNIFICADO de error
      this.dispatch('lead:status', {
        type: 'update',
        success: false,
        error: error instanceof Error ? error.message : error,
      } as LeadStatusEvent);
    }
  }

  // ===================================================================================
  // UTILIDADES Y MÉTODOS CORE
  // ===================================================================================

  getLandingId(): string | null {
    const currentScript = document.currentScript as HTMLScriptElement | null;
    if (!currentScript) {
      // Intento fallback por si se llama asíncronamente y currentScript se pierde
      // Buscamos scripts que contengan 'catalyst/v1.0'
      const scripts = document.querySelectorAll('script');
      for (let i = 0; i < scripts.length; i++) {
        if (scripts[i].src && scripts[i].src.includes('catalyst')) {
          const url = new URL(scripts[i].src);
          const id = url.searchParams.get('landing_id');
          if (id) return id;
        }
      }
      return null;
    }
    const scriptUrl = new URL(currentScript.src);
    return scriptUrl.searchParams.get('landing_id');
  }

  /**
   * Construye la URL completa para una ruta de la API usando dot notation.
   * @param routeKey Clave de la ruta (ej: 'visitor.register' o 'leads.update')
   */
  private getEndpoint(routeKey: string): string {
    const keys = routeKey.toUpperCase().split('.');
    let path: any = API_ROUTES;

    for (const key of keys) {
      if (path[key] === undefined) {
        console.error(`Catalyst SDK: Ruta API no encontrada para '${routeKey}'`);
        return '';
      }
      path = path[key];
    }

    const baseUrl = this.config.api_url?.replace(/\/$/, '') || '';
    return `${baseUrl}${path}`;
  }

  enableDebugMode(): void {
    console.group('Catalyst SDK [Debug Mode]');
    console.log('Catalyst SDK Config:', this.config);
    console.log('API URL:', this.config.api_url);
    console.groupEnd();
  }

  /**
   * Sistema de eventos simple (Pub/Sub)
   */
  on(eventName: string, callback: EventCallback): void {
    // Si el evento es 'ready' y ya estamos listos, ejecutamos inmediatamente
    if (eventName === 'ready' && this.isReady) {
      try {
        // CORRECCIÓN: Enviamos la misma estructura que en el dispatch original
        callback({
          catalyst: this,
          visitorData: this.visitorData,
        });
      } catch (e) {
        console.error(`Catalyst SDK: Error en callback inmediato de 'ready':`, e);
        throw e; // Relanzamos para que el desarrollador vea su error
      }
    }

    if (!this.listeners[eventName]) {
      this.listeners[eventName] = [];
    }
    this.listeners[eventName].push(callback);
  }

  // ===================================================================================
  // OFFERWALL METHODS
  // ===================================================================================

  /**
   * Obtiene las ofertas de un Offerwall Mix específico.
   * @param mixId ID o UUID del Offerwall Mix
   */

  async getOfferwall({ mixId, placement, fingerprint, data = {} }: GetOfferwallOptions): Promise<OfferwallResponse> {
    if (!fingerprint) {
      fingerprint = this.visitorData?.fingerprint;
    }
    if (!fingerprint) {
      throw new Error('Catalyst SDK: No hay fingerprint de visitante. Asegúrate de que el SDK esté inicializado.');
    }

    const payload: Record<string, any> = {
      fingerprint,
      placement,
      ...data,
    };

    try {
      // Construir URL: /v1/offerwall/mix/{mixId}
      // API_ROUTES.OFFERWALL.TRIGGER es '/v1/offerwall/mix/'
      const baseUrl = this.getEndpoint('OFFERWALL.TRIGGER');
      // Remove trailing slash if present to avoid double slash, though getEndpoint might handle it.
      // But here we append ID.
      const url = `${baseUrl}${mixId}`;

      const res = await fetch(url, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          Accept: 'application/json',
        },
        body: JSON.stringify(payload),
      });

      if (!res.ok) {
        const errorBody = await res.text();
        throw new Error(`HTTP ${res.status}: ${errorBody}`);
      }

      const json: OfferwallResponse = await res.json();

      // Disparar evento para quienes escuchen
      this.dispatch('offerwall:loaded', { mixId, count: json.data.length });

      return json;
    } catch (error) {
      console.error(`Catalyst SDK: Error obteniendo offerwall ${mixId}:`, error);
      this.dispatch('offerwall:error', { mixId, error });
      throw error;
    }
  }

  getFingerprint(): string | null {
    return this.visitorData?.fingerprint || null;
  }

  // ===================================================================================
  // SHARE LEADS (Ping-Post Dispatch)
  // ===================================================================================

  /**
   * Dispatches the current lead to a share-leads workflow.
   * Uses the build-in-one mode: sends fingerprint + fields + create_on_miss
   * so the backend creates/updates the lead and dispatches in a single request.
   *
   * @param options.workflowId  The workflow ID to dispatch to
   * @param options.fields      Optional lead fields to create/update before dispatching
   * @param options.createOnMiss If true, creates the lead if it doesn't exist yet (default: false)
   */
  async shareLead({ workflowId, fields, createOnMiss = false }: ShareLeadOptions): Promise<ShareLeadResponse> {
    if (!this.visitorData?.fingerprint) {
      const error = 'No visitor fingerprint available. Make sure the SDK is initialized.';
      this.dispatch('share:status', { success: false, workflowId, error });
      throw new Error(`Catalyst SDK: ${error}`);
    }

    const payload: Record<string, any> = {
      fingerprint: this.visitorData.fingerprint,
    };

    if (fields && Object.keys(fields).length > 0) {
      payload.fields = this.sanitizeFields(fields);
      payload.create_on_miss = createOnMiss;
    }

    try {
      const baseUrl = this.getEndpoint('SHARE_LEADS.DISPATCH');
      const url = `${baseUrl}${workflowId}`;

      const res = await fetch(url, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          Accept: 'application/json',
        },
        body: JSON.stringify(payload),
      });

      if (!res.ok) {
        const errorBody = await res.text();
        throw new Error(`HTTP ${res.status}: ${errorBody}`);
      }

      const json: ShareLeadResponse = await res.json();

      if (this.config.debug) console.log('Catalyst SDK: Lead dispatched successfully.', json);

      this.dispatch('share:status', {
        success: true,
        workflowId,
        data: json.data,
      });

      return json;
    } catch (error) {
      console.error(`Catalyst SDK: Error dispatching lead to workflow ${workflowId}:`, error);
      this.dispatch('share:status', {
        success: false,
        workflowId,
        error: error instanceof Error ? error.message : error,
      });
      throw error;
    }
  }

  // ===================================================================================
  // LEAD QUALITY (Challenge send / verify)
  // ===================================================================================

  /**
   * Issues a Lead Quality challenge (OTP, etc.) for the given workflow before dispatch.
   *
   * The backend resolves which validation rules apply based on the workflow's buyers,
   * creates a `LeadDispatch` in `PENDING_VALIDATION` state, and delegates to each
   * provider (Twilio Verify, etc.) to send the challenge.
   *
   * Canonical landing flow:
   *   1. `catalyst.registerLead({...})`         // lead exists in our DB
   *   2. `catalyst.requestChallenge({ workflowId, to })`   // user receives SMS
   *   3. user types the code → `catalyst.verifyChallenge({ challengeToken, code, to })`
   *   4. on `verified: true`, the backend auto-queues the dispatch — no extra call needed.
   *
   * When the workflow has no applicable validation rules, the response comes back with
   * `challenges: []` + `errors: []`; the caller can skip straight to `shareLead()`.
   *
   * Passing `fields` merges them onto the lead atomically before the challenge is
   * issued, saving a separate `updateLead()` round-trip. If the merge fails, the
   * whole request aborts and no challenge is emitted.
   */
  async requestChallenge({
    workflowId,
    fingerprint,
    to,
    channel,
    locale,
    fields,
    createOnMiss,
  }: RequestChallengeOptions): Promise<RequestChallengeResponse> {
    const resolvedFingerprint = fingerprint ?? this.visitorData?.fingerprint;
    if (!resolvedFingerprint) {
      const error = 'No visitor fingerprint available. Make sure the SDK is initialized.';
      this.dispatch('challenge:status', { type: 'send', success: false, error } as ChallengeStatusEvent);
      throw new Error(`Catalyst SDK: ${error}`);
    }

    const payload: Record<string, any> = {
      workflow_id: workflowId,
      fingerprint: resolvedFingerprint,
    };
    if (to) payload.to = to;
    if (channel) payload.channel = channel;
    if (locale) payload.locale = locale;
    if (fields && Object.keys(fields).length > 0) payload.fields = fields;
    if (createOnMiss) payload.create_on_miss = true;

    try {
      const res = await fetch(this.getEndpoint('LEAD_QUALITY.CHALLENGE_SEND'), {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          Accept: 'application/json',
        },
        body: JSON.stringify(payload),
      });

      const json = (await res.json()) as RequestChallengeResponse;

      if (!res.ok) {
        const msg = (json as any)?.message ?? `HTTP ${res.status}`;
        this.dispatch('challenge:status', { type: 'send', success: false, error: msg, data: json } as ChallengeStatusEvent);
        throw new Error(`Catalyst SDK: ${msg}`);
      }

      if (this.config.debug) console.log('Catalyst SDK: Challenge sent.', json);

      this.dispatch('challenge:status', { type: 'send', success: true, data: json.data } as ChallengeStatusEvent);

      return json;
    } catch (error) {
      console.error('Catalyst SDK: Error requesting challenge:', error);
      this.dispatch('challenge:status', {
        type: 'send',
        success: false,
        error: error instanceof Error ? error.message : error,
      } as ChallengeStatusEvent);
      throw error;
    }
  }

  /**
   * Verifies a challenge code entered by the user. On `verified: true`, the
   * backend auto-transitions the associated dispatch from PENDING_VALIDATION
   * to RUNNING and queues the DispatchLeadJob — the landing does NOT need to
   * call `shareLead()` afterwards.
   *
   * Non-success outcomes:
   *   - `retry` → wrong code, `retry_remaining` is set.
   *   - `expired` / `failed` → terminal, the dispatch is VALIDATION_FAILED.
   *   - `invalid_token` / `not_found` → token was tampered or doesn't exist.
   */
  async verifyChallenge({ challengeToken, code, to }: VerifyChallengeOptions): Promise<VerifyChallengeResponse> {
    const payload: Record<string, any> = {
      challenge_token: challengeToken,
      code,
    };
    if (to) payload.to = to;

    try {
      const res = await fetch(this.getEndpoint('LEAD_QUALITY.CHALLENGE_VERIFY'), {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          Accept: 'application/json',
        },
        body: JSON.stringify(payload),
      });

      const json = await res.json();

      // Success envelope: { success: true, data: { verified, status, dispatch_uuid? }, message }
      // Failure envelope: { success: false, message, errors: { verified, status, retry_remaining?, reason? } }
      const flattened: VerifyChallengeResponse = res.ok
        ? {
            success: true,
            message: json.message ?? 'Challenge verified.',
            verified: Boolean(json.data?.verified),
            status: json.data?.status ?? 'verified',
            dispatch_uuid: json.data?.dispatch_uuid,
          }
        : {
            success: false,
            message: json.message ?? `HTTP ${res.status}`,
            verified: Boolean(json.errors?.verified),
            status: json.errors?.status ?? 'error',
            retry_remaining: json.errors?.retry_remaining,
            reason: json.errors?.reason ?? json.message,
          };

      if (this.config.debug) console.log('Catalyst SDK: Challenge verify result.', flattened);

      this.dispatch('challenge:status', {
        type: 'verify',
        success: flattened.verified,
        data: flattened,
      } as ChallengeStatusEvent);

      return flattened;
    } catch (error) {
      console.error('Catalyst SDK: Error verifying challenge:', error);
      this.dispatch('challenge:status', {
        type: 'verify',
        success: false,
        error: error instanceof Error ? error.message : error,
      } as ChallengeStatusEvent);
      throw error;
    }
  }

  /**
   * Validates a phone number against the configured sync phone-validation
   * provider (Melissa Global Phone). Designed as an **on-submit pre-filter**
   * — call once right before `requestChallenge()` (or `shareLead()`) to drop
   * fakes/disposables/disconnected numbers before spending an SMS credit.
   *
   * Behaviour by classification:
   *   - `valid_high_confidence` / `valid_low_confidence` / `low_confidence`
   *     / `compliance_risk` / `pending_or_timeout` → `valid: true`, proceed.
   *   - `invalid_phone` / `disconnected_phone` / `high_risk_phone` → `valid: false`,
   *     show inline error and abort the submit.
   *   - `validation_error` (license invalid, upstream timeout, no provider configured)
   *     → SDK throws. The caller decides the policy — block, fall through,
   *     retry, log. The validator is agnostic to whatever flow surrounds it.
   *
   * The endpoint is workflow-agnostic and does NOT create any
   * `LeadDispatch` / `LeadQualityValidationLog` — just an entry in
   * `external_service_requests` per real upstream call (cache hits are free).
   */
  async validatePhone({ phone, country, fingerprint }: ValidatePhoneOptions): Promise<ValidatePhoneResponse> {
    const resolvedFingerprint = fingerprint ?? this.visitorData?.fingerprint;
    if (!resolvedFingerprint) {
      const error = 'No visitor fingerprint available. Make sure the SDK is initialized.';
      this.dispatch('phone:status', { type: 'validate', success: false, error } as PhoneStatusEvent);
      throw new Error(`Catalyst SDK: ${error}`);
    }

    if (!phone) {
      const error = 'phone is required.';
      this.dispatch('phone:status', { type: 'validate', success: false, error } as PhoneStatusEvent);
      throw new Error(`Catalyst SDK: ${error}`);
    }

    const payload: Record<string, any> = {
      fingerprint: resolvedFingerprint,
      phone,
    };
    if (country) payload.country = country;

    try {
      const res = await fetch(this.getEndpoint('LEAD_QUALITY.PHONE_VALIDATE'), {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          Accept: 'application/json',
        },
        body: JSON.stringify(payload),
      });

      const json = await res.json();

      // 502 → backend mapped a `validation_error` classification (license, timeout,
      // no provider). Surface it as a thrown error so the caller's catch path
      // can decide whether to fall through to OTP.
      if (!res.ok) {
        const msg = json?.message ?? `HTTP ${res.status}`;
        this.dispatch('phone:status', {
          type: 'validate',
          success: false,
          error: msg,
          data: json,
        } as PhoneStatusEvent);
        throw new Error(`Catalyst SDK: ${msg}`);
      }

      // Flatten the envelope so callers read `valid` / `classification` from
      // the top level, without having to navigate `.data` for every field.
      const flattened: ValidatePhoneResponse = {
        success: Boolean(json?.success),
        message: json?.message ?? '',
        valid: Boolean(json?.data?.valid),
        classification: json?.data?.classification ?? 'validation_error',
        line_type: json?.data?.line_type ?? null,
        country: json?.data?.country ?? null,
        carrier: json?.data?.carrier ?? null,
        normalized_phone: json?.data?.normalized_phone ?? null,
        error: json?.data?.error ?? null,
      };

      if (this.config.debug) console.log('Catalyst SDK: Phone validate result.', flattened);

      this.dispatch('phone:status', {
        type: 'validate',
        success: flattened.valid,
        data: flattened,
      } as PhoneStatusEvent);

      return flattened;
    } catch (error) {
      console.error('Catalyst SDK: Error validating phone:', error);
      this.dispatch('phone:status', {
        type: 'validate',
        success: false,
        error: error instanceof Error ? error.message : error,
      } as PhoneStatusEvent);
      throw error;
    }
  }

  /**
   * Registra una conversión de Offerwall.
   * @param data Datos de la conversión
   */
  async convertOfferwall(data: Omit<OfferwallConversionRequest, 'fingerprint'>): Promise<OfferwallConversionResponse> {
    if (!this.visitorData?.fingerprint) {
      throw new Error('Catalyst SDK: No hay fingerprint de visitante.');
    }

    if (!data.offer_token) {
      throw new Error('Catalyst SDK: offer_token is required.');
    }

    if (data.amount === undefined || data.amount === null) {
      throw new Error('Catalyst SDK: amount is required.');
    }

    const payload: OfferwallConversionRequest = {
      fingerprint: this.visitorData.fingerprint,
      pathname: window.location.pathname,
      amount: Number(data.amount),
      offer_token: data.offer_token,
    };

    try {
      const res = await fetch(this.getEndpoint('OFFERWALL.CONVERSION'), {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          Accept: 'application/json',
        },
        body: JSON.stringify(payload),
      });

      if (!res.ok) {
        const errorBody = await res.text();
        throw new Error(`HTTP ${res.status}: ${errorBody}`);
      }

      const json: OfferwallConversionResponse = await res.json();

      this.dispatch('offerwall:conversion', { success: true, data: json });

      return json;
    } catch (error) {
      console.error('Catalyst SDK: Error registrando conversión de offerwall:', error);
      this.dispatch('offerwall:conversion', { success: false, error });
      throw error;
    }
  }

  /**
   * Despacha un evento a los listeners registrados.
   */
  dispatch(eventName: string, data: Record<string, any> = {}): void {
    // Capturamos el estado ready para futuros listeners
    if (eventName === 'ready') {
      this.isReady = true;
    }
    /* if (this.config.debug) {
      console.log(`Catalyst Event Dispatched: ${eventName}`, data);
    } */
    if (this.listeners[eventName]) {
      this.listeners[eventName].forEach((callback) => {
        try {
          callback(data);
        } catch (e) {
          console.error(`Catalyst SDK: Error en listener de '${eventName}':`, e);
        }
      });
    }
  }

  // ===================================================================================
  // PERFORMANCE METRICS
  // ===================================================================================

  /**
   * Envía la métrica de tiempo de carga al servidor. Fire-and-forget (fetch sin await).
   */
  reportPerformance(loadTimeMs: number): void {
    const data = {
      fingerprint: this.visitorData?.fingerprint ?? null,
      host: window.location.hostname,
      load_time_ms: loadTimeMs,
    };
    const url = this.getEndpoint('METRICS.PERFORMANCE');
    if (!url) return;

    if (this.config.debug) {
      console.log('Catalyst SDK: Reporting performance metric', { url, ...data });
    }

    let headers: Record<string, string> = {
      'Content-Type': 'application/json',
      Accept: 'application/json',
    };
    if (this.config.debug && this.config?.dev_origin) {
      headers['Dev-Origin'] = this.config.dev_origin;
    }

    fetch(url, {
      method: 'POST',
      headers,
      body: JSON.stringify(data),
    })
      .then((res) => {
        if (this.config.debug) {
          console.log(`Catalyst SDK: Performance metric reported (${res.status})`, { load_time_ms: loadTimeMs });
        }
      })
      .catch((err) => {
        if (this.config.debug) {
          console.warn('Catalyst SDK: Failed to report performance metric', err);
        }
      });
  }

  // --- Helpers Privados ---

  private sanitizeFields(fields: Record<string, any>): Record<string, any> {
    const sanitized: Record<string, any> = {};
    for (const [key, value] of Object.entries(fields)) {
      sanitized[key] = typeof value === 'string' ? value.replace(/\s+/g, ' ').trim() : value;
    }
    return sanitized;
  }

  private getReferer(): string | null {
    const referer = document.referrer;
    const host = window.location.hostname;
    if (referer && referer.includes(host)) {
      return null; // Referrer interno, no nos interesa
    }
    return referer || null;
  }

  private setCookie(name: string, value: string, minutes: number) {
    const date = new Date();
    date.setTime(date.getTime() + minutes * 60 * 1000);
    const expires = 'expires=' + date.toUTCString();
    document.cookie = name + '=' + value + ';' + expires + ';path=/';
  }

  private getCookie(name: string): string | null {
    const nameEQ = name + '=';
    const ca = document.cookie.split(';');
    for (let i = 0; i < ca.length; i++) {
      let c = ca[i];
      while (c.charAt(0) === ' ') c = c.substring(1, c.length);
      if (c.indexOf(nameEQ) === 0) return c.substring(nameEQ.length, c.length);
    }
    return null;
  }
}

// ===================================================================================
// INICIALIZACIÓN (CHEF)
// ===================================================================================

async function init(): Promise<void> {
  const placeholder = window.Catalyst as CatalystPlaceholder;

  if (!placeholder || !placeholder._q) {
    console.error('Catalyst SDK: Error crítico. Loader no encontrado.');
    return;
  }

  const catalystInstance = new CatalystCore(placeholder.config);

  // Procesar cola de comandos previos (Lógica extraída para reutilizar)
  const processQueue = async () => {
    // Es posible que el array crezca mientras procesamos si no hemos hecho el swap aún,
    // pero aquí ya deberíamos haber hecho el swap o congelado la referencia.
    // Usamos un bucle for..of para asegurar secuencialidad en llamadas async.
    for (const item of placeholder._q) {
      const method = item[0] as string;
      const fn = (catalystInstance as any)[method];

      if (typeof fn !== 'function') continue;

      // Detectar proxy de promesa
      const possibleResolve = item[2];
      const possibleReject = item[3];
      const isPromiseProxy =
        item.length === 4 &&
        typeof possibleResolve === 'function' &&
        typeof possibleReject === 'function' &&
        ['registerLead', 'updateLead', 'getOfferwall', 'convertOfferwall', 'shareLead', 'requestChallenge', 'verifyChallenge', 'validatePhone'].includes(
          method,
        );

      if (isPromiseProxy) {
        const args = Array.from((item[1] as ArrayLike<any>) || []);
        try {
          // AWAIT aquí asegura que la siguiente instrucción en la cola no empiece
          // hasta que esta termine. Crucial para dependencias register -> update.
          const result = await fn.apply(catalystInstance, args);
          possibleResolve(result);
        } catch (error) {
          possibleReject(error);
        }
      } else {
        const [, ...args] = item;
        fn.apply(catalystInstance, args);
      }
    }
  };

  // Inicializar el visitante y esperar a que esté listo
  let visitorData: VisitorData | null = null;
  try {
    visitorData = await catalystInstance.initVisitor();
    if (catalystInstance.config.debug) {
      console.log('Catalyst SDK: Visitante inicializado con éxito:', visitorData);
    }
  } catch (error) {
    console.error('Catalyst SDK: Error crítico inicializando visitante.', error);
  } finally {
    // 1. Reemplazar global AHORA que la instancia está lista (con o sin datos)
    window.Catalyst = catalystInstance;

    // 2. Procesar la cola secuencialmente
    await processQueue();

    // 3. Emitir evento ready
    catalystInstance.dispatch('ready', { catalyst: catalystInstance, visitorData });

    // 4. Fire-and-forget: reportar métrica de tiempo de carga (no bloquea nada)
    const startTime = (placeholder as any)._startTime;
    console.log('Catalyst SDK: Load time start:', startTime);
    if (startTime && visitorData) {
      const loadTimeMs = Math.round(performance.now() - startTime);
      catalystInstance.reportPerformance(loadTimeMs);
    }
  }
}

init();

export {};
