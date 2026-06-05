import { API_ROUTES } from './routes';
import {
  CatalystConfig,
  CatalystPlaceholder,
  ChallengeStatusEvent,
  ConvertOfferwallPostback,
  EventCallback,
  FirePostbackOptions,
  FirePostbackResponse,
  GetOfferwallOptions,
  LeadStatusEvent,
  OfferwallConversionRequest,
  OfferwallConversionResponse,
  OfferwallResponse,
  PhoneStatusEvent,
  PostbackStatusEvent,
  RequestChallengeOptions,
  RequestChallengeResponse,
  ShareLeadOptions,
  ShareLeadResponse,
  UpdateVisitData,
  UpdateVisitErrorCode,
  UpdateVisitResult,
  ValidatePhoneOptions,
  ValidatePhoneResponse,
  VerifyChallengeOptions,
  VerifyChallengeResponse,
  VisitorData,
  visitorRegisterResponse,
  WorkflowOverride,
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
  // Ventana de inactividad (minutos): una sesion/fingerprint cacheado se reusa
  // mientras la ultima actividad este dentro de esta ventana. Pasada la ventana
  // se considera stale y se re-acuña (via reload). Sin limite de dia calendario:
  // una carga continua que cruza medianoche UTC sigue viva.
  private readonly SESSION_TTL_MINUTES = 60;

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

    // Vigilar el regreso a una tab idle: si la sesion quedo stale (inactividad
    // > TTL), recargar para re-acuñar visitante y resetear el form de forma
    // consistente (identidad + UI juntas), en vez de despachar un lead parcial.
    this.setupSessionFreshnessWatch();
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
      // Reingreso: reusamos la sesion local SOLO si sigue fresca (mismo host y
      // dentro de la ventana de inactividad). Si quedo stale, la descartamos y
      // re-registramos para que el server acuñe una huella nueva.
      try {
        const parsed: VisitorData = JSON.parse(storedData);
        if (this.isSessionStale(parsed)) {
          if (this.config.debug) {
            console.log('Catalyst SDK: Sesion cacheada vencida (inactividad > TTL u otro host). Re-registrando visitante.');
          }
          this.clearVisitorSession();
        } else {
          // Reuso valido: deslizamos la ventana de inactividad (este pageview es actividad).
          this.saveVisitorSession(parsed);
          if (this.config.debug) console.log('Catalyst SDK: Visitante recuperado de caché (sesion fresca).');
          return parsed;
        }
      } catch (e) {
        console.error('Catalyst SDK: Error leyendo datos locales, reiniciando visitante.', e);
        this.clearVisitorSession();
      }
    }
    // No hay throttle, no hay datos, o la sesion era stale. Registramos nueva visita.
    return await this.registerNewVisitor();
  }

  /**
   * Realiza la petición a la API para registrar al visitante.
   */
  private async registerNewVisitor(): Promise<VisitorData> {
    if (!this.config.api_url) {
      throw new Error('Catalyst SDK: api_url has not been configured. The visitor cannot be registered.');
    }
    const urlParams = new URLSearchParams(window.location.search);
    const queryParams = Object.fromEntries(Array.from(urlParams.entries()).map(([key, value]) => [key.toLowerCase(), value]));
    const payload = {
      landing_id: this.landingId,
      user_agent: navigator.userAgent,
      referer: this.getReferer() ?? null,
      current_page: window.location.pathname,
      query_params: queryParams,
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

      // Mapeamos la respuesta de la API a nuestra estructura interna VisitorData.
      // Estampamos el host de acuñacion; `_fp_seen_at` lo sella saveVisitorSession.
      const visitorData: VisitorData = {
        fingerprint: json.fingerprint,
        ...json.data, // Incluimos device_type, is_bot, etc.
        geolocation: json.geolocation, // Incluimos geolocalización si existe
        _fp_host: window.location.hostname,
      };

      // Guardamos en memoria y persistencia (sella `_fp_seen_at` = ahora)
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
    // Marca de actividad deslizante: cada save (register/update) refresca el
    // "last seen". La frescura de la sesion se mide contra esto (no contra una
    // fecha calendario), asi una carga continua nunca se invalida sola.
    data._fp_seen_at = Date.now();
    this.visitorData = data;
    localStorage.setItem(this.STORAGE_KEY, JSON.stringify(data));
    this.setCookie(this.THROTTLE_COOKIE, '1', this.SESSION_TTL_MINUTES);
  }

  /**
   * Una sesion cacheada es "stale" cuando cambio el host, o cuando el hueco de
   * inactividad supera el TTL (o no tiene marca de actividad — sesiones previas
   * a este modelo). NO usa fecha calendario: una carga continua que cruza
   * medianoche UTC sigue fresca; un regreso tras un hueco largo se re-acuña.
   */
  private isSessionStale(data: VisitorData | null): boolean {
    if (!data || !data.fingerprint) return true;
    if (data._fp_host !== window.location.hostname) return true;
    const seenAt = typeof data._fp_seen_at === 'number' ? data._fp_seen_at : 0;
    return Date.now() - seenAt > this.SESSION_TTL_MINUTES * 60 * 1000;
  }

  /**
   * Limpia la sesion persistida y expira la cookie de throttle, para que un
   * throttle vigente no corte el re-registro tras invalidar el cache.
   */
  private clearVisitorSession(): void {
    try {
      localStorage.removeItem(this.STORAGE_KEY);
    } catch (e) {
      if (this.config.debug) console.warn('Catalyst SDK: No se pudo limpiar la sesion del visitante.', e);
    }
    this.setCookie(this.THROTTLE_COOKIE, '', -1);
    this.visitorData = null;
  }

  /**
   * Al volver a una tab que estuvo idle, si la sesion quedo stale (inactividad
   * > TTL u otro host) recargamos la pagina. El reload re-corre initVisitor
   * (re-acuña una huella fresca) y resetea el formulario, manteniendo identidad
   * y UI consistentes — evita el "lead parcial" que produciria re-acuñar en
   * silencio dejando el form con datos viejos. No hace loop: tras el reload la
   * sesion nace fresca.
   */
  private setupSessionFreshnessWatch(): void {
    if (typeof document === 'undefined' || typeof window === 'undefined') return;
    const checkOnResume = () => {
      if (document.visibilityState !== 'visible') return;
      if (this.visitorData && this.isSessionStale(this.visitorData)) {
        if (this.config.debug) {
          console.log('Catalyst SDK: Tab idle vencida al regresar (inactividad > TTL). Recargando para re-acuñar visitante y resetear el form.');
        }
        window.location.reload();
      }
    };
    document.addEventListener('visibilitychange', checkOnResume);
    window.addEventListener('focus', checkOnResume);
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
  // VISITA (Update post-registro)
  // ===================================================================================

  /**
   * Actualiza columnas de tracking de la visita YA registrada en este pageview,
   * matcheada por el `fingerprint` interno (la landing NO pasa visit id).
   *
   * Caso de uso: trafico de Google Ads / YouTube que cae directo a la landing
   * sin redirect de ClickFlare. La visita inicial nace sin `s10`; el click_id
   * recien aparece async en la cookie `cf_click_id`. La landing lo escribe y
   * llama a este metodo para persistirlo, igual que si hubiera llegado por URL.
   *
   * A diferencia del resto del SDK, NUNCA rejecta: siempre resuelve con un
   * `UpdateVisitResult` (la landing usa `success` para destrabar el CTA).
   * Idempotente. Ademas de la promesa, emite el evento `visit:updated`
   * (pub/sub interno) y un `CustomEvent('catalyst:visit:updated')` en window.
   */
  async updateVisit(data: UpdateVisitData): Promise<UpdateVisitResult> {
    const fingerprint = this.getFingerprint();

    if (!fingerprint) {
      return this.emitVisitUpdated({
        success: false,
        fingerprint: null,
        s10: null,
        error: { code: 'NO_ACTIVE_VISIT', message: 'No visitor fingerprint available. The visit has not been registered yet.' },
      });
    }

    if (!data?.s10) {
      return this.emitVisitUpdated({
        success: false,
        fingerprint,
        s10: null,
        error: { code: 'UNKNOWN', message: 's10 is required.' },
      });
    }

    const payload: Record<string, any> = {
      ...this.sanitizeFields(data),
      fingerprint,
    };

    try {
      const res = await fetch(this.getEndpoint('VISITOR.UPDATE'), {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          Accept: 'application/json',
        },
        body: JSON.stringify(payload),
      });

      const json = await res.json().catch(() => null);

      if (!res.ok) {
        const code: UpdateVisitErrorCode = json?.errors?.code ?? (res.status === 404 ? 'NO_ACTIVE_VISIT' : 'NETWORK_ERROR');
        return this.emitVisitUpdated({
          success: false,
          fingerprint,
          s10: null,
          error: { code, message: json?.message ?? `HTTP ${res.status}` },
        });
      }

      if (this.config.debug) console.log('Catalyst SDK: Visit updated successfully.', json);

      return this.emitVisitUpdated({
        success: true,
        fingerprint,
        s10: json?.data?.s10 ?? data.s10,
        updated_at: json?.data?.updated_at ?? null,
      });
    } catch (error) {
      console.error('Catalyst SDK: Error updating visit:', error);
      return this.emitVisitUpdated({
        success: false,
        fingerprint,
        s10: null,
        error: { code: 'NETWORK_ERROR', message: error instanceof Error ? error.message : String(error) },
      });
    }
  }

  /**
   * Emite el resultado de `updateVisit` por ambos canales (pub/sub interno +
   * CustomEvent en window) y lo retorna, para encadenar el `return` directo.
   */
  private emitVisitUpdated(result: UpdateVisitResult): UpdateVisitResult {
    this.dispatch('visit:updated', result);
    if (typeof window !== 'undefined' && typeof window.dispatchEvent === 'function') {
      try {
        window.dispatchEvent(new CustomEvent('catalyst:visit:updated', { detail: result }));
      } catch (e) {
        if (this.config.debug) console.warn('Catalyst SDK: Could not dispatch catalyst:visit:updated event.', e);
      }
    }
    return result;
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
      fingerprint = this.getFingerprint();
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
      console.error(`Catalyst SDK: Error getting offerwall ${mixId}:`, error);
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

    // Fallback de seguridad: normalmente el watcher de visibilidad ya recargo la
    // tab si la sesion quedo stale. Si aun asi llegamos al dispatch con una
    // sesion vencida (inactividad > TTL u otro host), recargamos en vez de
    // despachar un lead parcial/viejo. El reload re-acuña y resetea el form.
    if (this.isSessionStale(this.visitorData)) {
      if (this.config.debug) {
        console.log('Catalyst SDK: Sesion stale al despachar (fallback). Recargando en vez de despachar un lead parcial.');
      }
      this.dispatch('share:status', { success: false, workflowId, error: 'Session expired; reloading to start a fresh visit.' });
      window.location.reload();
      throw new Error('Catalyst SDK: Session expired; page is reloading.');
    }

    // URL-driven workflow override: when the landing was loaded with a numeric
    // `?workflow_id=<n>` that differs from the workflow the landing passed in, the
    // dispatch is redirected to that workflow and `workflow_override` is attached so
    // the backend can notify the change (Slack notify channel). Null when there is
    // no real override (param absent, blank, non-numeric, or equal to intended).
    const override = this.resolveWorkflowOverride(workflowId);
    const effectiveWorkflowId = override ? override.id_effective : workflowId;

    const payload: Record<string, any> = {
      fingerprint: this.visitorData!.fingerprint,
    };

    if (override) {
      payload.workflow_override = override;
    }

    if (fields && Object.keys(fields).length > 0) {
      payload.fields = this.sanitizeFields(fields);
      payload.create_on_miss = createOnMiss;
    }

    try {
      const baseUrl = this.getEndpoint('SHARE_LEADS.DISPATCH');
      const url = `${baseUrl}${effectiveWorkflowId}`;

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
        effectiveWorkflowId,
        ...(override ? { workflowOverride: override } : {}),
        data: json.data,
      });

      return json;
    } catch (error) {
      console.error(`Catalyst SDK: Error dispatching lead to workflow ${effectiveWorkflowId}:`, error);
      this.dispatch('share:status', {
        success: false,
        workflowId,
        effectiveWorkflowId,
        ...(override ? { workflowOverride: override } : {}),
        error: error instanceof Error ? error.message : error,
      });
      throw error;
    }
  }

  /**
   * Resolves an optional workflow override from the landing's URL query string.
   *
   * When the page was loaded with a numeric `?workflow_id=<n>` that differs from
   * the workflow the landing passed to `shareLead`, the dispatch is redirected to
   * that workflow and a `WorkflowOverride` is returned so the backend can notify
   * the change. Returns `null` when the param is absent, blank, non-numeric, or
   * equal to the intended workflow (no real override).
   *
   * @param intendedWorkflowId The workflow the landing intended to dispatch to.
   */
  private resolveWorkflowOverride(intendedWorkflowId: number | string): WorkflowOverride | null {
    const raw = new URLSearchParams(window.location.search).get('workflow_id');
    if (raw === null) {
      return null;
    }
    const trimmed = raw.trim();
    if (trimmed === '' || !/^\d+$/.test(trimmed)) {
      if (this.config.debug && trimmed !== '') {
        console.warn(`Catalyst SDK: Ignoring non-numeric workflow_id override "${raw}".`);
      }
      return null;
    }
    if (trimmed === String(intendedWorkflowId)) {
      return null;
    }
    return {
      id_intended: String(intendedWorkflowId),
      id_effective: trimmed,
    };
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
    const resolvedFingerprint = fingerprint ?? this.getFingerprint();
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
    const resolvedFingerprint = fingerprint ?? this.getFingerprint();
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
   *
   * Opcionalmente, si `data.postback` viene seteado, dispara uno o varios
   * postbacks internos ANTES (fire-and-forget, sin await) de registrar la
   * conversión ordinaria. Acepta un solo objeto o un array (ej. una landing
   * de auto insurance que dispara un postback paralelo si el usuario elige
   * más de un vehículo). La landing decide UUID, source y fields de cada uno.
   *
   * @param data Datos de la conversión (+ config opcional de postback(s))
   */
  async convertOfferwall(
    data: Omit<OfferwallConversionRequest, 'fingerprint'> & {
      postback?: ConvertOfferwallPostback | ConvertOfferwallPostback[];
    },
  ): Promise<OfferwallConversionResponse> {
    if (!this.visitorData?.fingerprint) {
      throw new Error('Catalyst SDK: No hay fingerprint de visitante.');
    }

    if (!data.offer_token) {
      throw new Error('Catalyst SDK: offer_token is required.');
    }

    if (data.amount === undefined || data.amount === null) {
      throw new Error('Catalyst SDK: amount is required.');
    }

    // Optionally fire one or more internal postbacks alongside the conversion.
    // Fire-and-forget (no await) and BEFORE the conversion request, so a
    // misconfigured or failing postback never blocks or breaks the conversion.
    if (data.postback) {
      const postbacks = Array.isArray(data.postback) ? data.postback : [data.postback];
      for (const pb of postbacks) {
        this.fireOfferwallPostback(pb);
      }
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

  // ===================================================================================
  // INTERNAL POSTBACK FIRE
  // ===================================================================================

  /**
   * Validates and fires the optional postback config passed to
   * `convertOfferwall`. Fire-and-forget: never awaited and never rejects the
   * caller. Missing `uuid`/`source` or empty field values only emit console
   * warnings; the conversion always proceeds.
   */
  private fireOfferwallPostback(postback: ConvertOfferwallPostback): void {
    const { uuid, source, fields } = postback;

    if (!uuid || !source) {
      console.warn('Catalyst SDK: convertOfferwall received a `postback` config but `uuid` and/or `source` are empty — postback was NOT fired.');
      return;
    }

    const cleanFields: Record<string, string | number> = {};
    const emptyKeys: string[] = [];
    for (const [key, value] of Object.entries(fields ?? {})) {
      if (value === null || value === undefined || value === '') {
        emptyKeys.push(key);
        continue;
      }
      cleanFields[key] = value;
    }

    if (emptyKeys.length > 0) {
      console.warn(`Catalyst SDK: offerwall postback fields with empty values were skipped: ${emptyKeys.join(', ')}.`);
    }

    // Fire-and-forget: never await, never let it reject the conversion flow.
    this.firePostback({ uuid, source, fields: cleanFields }).catch((err) => {
      console.warn('Catalyst SDK: offerwall postback fire failed.', err);
    });
  }

  /**
   * Fires an internal postback configured in the admin against
   * `GET /v1/postback/fire/{uuid}/{fingerprint}/{source}?<fields>`.
   *
   * Unlike the rest of the SDK (POST + JSON body), this endpoint is a GET:
   * `fields` travel as flat query params and the backend persists each one
   * that matches a Field name on the lead resolved from the fingerprint.
   *
   * `source` fills the `{source}` path segment and is passed through as-is;
   * the backend validates it against `PostbackSource` (422 on unknown).
   * `fingerprint` falls back to `visitorData.fingerprint`.
   */
  async firePostback({ uuid, source, fields = {}, fingerprint }: FirePostbackOptions): Promise<FirePostbackResponse> {
    const resolvedFingerprint = fingerprint ?? this.getFingerprint();
    if (!resolvedFingerprint) {
      const error = 'No visitor fingerprint available. Make sure the SDK is initialized.';
      this.dispatch('postback:status', { success: false, error } as PostbackStatusEvent);
      throw new Error(`Catalyst SDK: ${error}`);
    }

    if (!uuid) {
      const error = 'uuid is required.';
      this.dispatch('postback:status', { success: false, error } as PostbackStatusEvent);
      throw new Error(`Catalyst SDK: ${error}`);
    }

    if (!source) {
      const error = 'source is required.';
      this.dispatch('postback:status', { success: false, error } as PostbackStatusEvent);
      throw new Error(`Catalyst SDK: ${error}`);
    }

    // Defensive: strip accidental wrapping quotes/whitespace from the uuid.
    // A landing may pass a JSON-encoded value (e.g. `"<uuid>"`), which would
    // otherwise reach the URL as `%22<uuid>` and fail the backend route
    // constraint `[0-9a-f-]{36}` with a 404 "route could not be found".
    const cleanUuid = uuid.trim().replace(/^["']+|["']+$/g, '');

    const base = this.getEndpoint('POSTBACK.FIRE_INTERNAL');
    let url = `${base}${encodeURIComponent(cleanUuid)}/${encodeURIComponent(resolvedFingerprint)}/${encodeURIComponent(source)}`;

    const query = new URLSearchParams();
    for (const [key, value] of Object.entries(fields)) {
      if (value === undefined || value === null) continue;
      query.append(key, String(value));
    }
    const queryString = query.toString();
    if (queryString) url += `?${queryString}`;

    try {
      const res = await fetch(url, {
        method: 'GET',
        headers: {
          Accept: 'application/json',
        },
      });

      const json = await res.json();

      const flattened: FirePostbackResponse = res.ok
        ? {
            success: Boolean(json?.success),
            message: json?.message ?? 'Postback fired.',
            executionUuid: json?.data?.execution_uuid,
            status: json?.data?.status,
          }
        : {
            success: false,
            message: json?.message ?? `HTTP ${res.status}`,
          };

      if (!res.ok) {
        this.dispatch('postback:status', { success: false, error: flattened.message, data: flattened } as PostbackStatusEvent);
        throw new Error(`Catalyst SDK: ${flattened.message}`);
      }

      if (this.config.debug) console.log('Catalyst SDK: Internal postback fired.', flattened);

      this.dispatch('postback:status', { success: true, data: flattened } as PostbackStatusEvent);

      return flattened;
    } catch (error) {
      console.error(`Catalyst SDK: Error firing internal postback ${uuid}:`, error);
      this.dispatch('postback:status', {
        success: false,
        error: error instanceof Error ? error.message : error,
      } as PostbackStatusEvent);
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
      fingerprint: this.getFingerprint() ?? null,
      host: window.location.hostname,
      load_time_ms: loadTimeMs,
    };
    const url = this.getEndpoint('METRICS.PERFORMANCE');
    if (!url) return;

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
    }).catch((err) => {
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

  public mapOffers(offersData: any[]): any[] {
    if (!Array.isArray(offersData)) return [];

    return offersData.map((offer, index) => ({
      id: index + 1,
      token: offer.offer_token,
      integration_id: offer.integration_id,
      company: offer.display_name,
      title: offer.title,
      description: offer.description,
      logo_url: offer.logo_url,
      click_url: offer.click_url,
      impression_url: offer.impression_url,
      cpc: offer.cpc ? parseFloat(offer.cpc) : 0,
      rating: 4.5 + Math.random() * 0.5,
      features: this.extractFeaturesFromDescription(offer.description),
    }));
  }

  private extractFeaturesFromDescription(description: string | string[] | null): string[] {
    const defaultFeatures = ['Comprehensive Coverage', 'Fast Claims Processing', '24/7 Support'];

    if (!description) return defaultFeatures;

    // Caso 1: Es un Array
    if (Array.isArray(description)) {
      return description.length > 0 ? description.slice(0, 3) : defaultFeatures;
    }

    // Caso 2: Es un String
    if (typeof description === 'string') {
      const trimmedDesc = description.trim();
      if (!trimmedDesc) return defaultFeatures;

      // Sub-caso 2a: Contiene HTML (buscamos tags básicos)
      if (/<[a-z][\s\S]*>/i.test(trimmedDesc)) {
        try {
          const tempDiv = document.createElement('div');
          tempDiv.innerHTML = trimmedDesc;

          // Intentar buscar <li>
          const listItems = tempDiv.querySelectorAll('li');
          if (listItems.length > 0) {
            return Array.from(listItems)
              .map((li) => li.textContent.trim())
              .filter((text) => text.length > 0) // Filtrar vacíos
              .slice(0, 3);
          }

          // Si no hay <li>, intentar obtener texto plano limpio del HTML
          const textContent = tempDiv.textContent || tempDiv.innerText || '';
          return textContent.trim() ? [textContent.trim()] : defaultFeatures;
        } catch (e) {
          console.warn('Error parsing HTML description:', e);
          return defaultFeatures;
        }
      }

      // Sub-caso 2b: String plano (sin HTML obvio)
      return [trimmedDesc];
    }

    // Caso fallback
    return defaultFeatures;
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
        [
          'registerLead',
          'updateLead',
          'updateVisit',
          'getOfferwall',
          'convertOfferwall',
          'shareLead',
          'requestChallenge',
          'verifyChallenge',
          'validatePhone',
          'firePostback',
        ].includes(method);

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
    if (catalystInstance.config.debug && !startTime) {
      console.error('Catalyst SDK: The upload timestamp was not found.');
    }
    if (startTime && visitorData) {
      const loadTimeMs = Math.round(performance.now() - startTime);
      catalystInstance.reportPerformance(loadTimeMs);
    }
  }
}

init();

export {};
