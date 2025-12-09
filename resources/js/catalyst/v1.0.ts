import { API_ROUTES } from './routes';
import {
  CatalystConfig,
  CatalystPlaceholder,
  EventCallback,
  LeadStatusEvent,
  OfferwallConversionRequest,
  OfferwallConversionResponse,
  OfferwallResponse,
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

    console.log(`Catalyst SDK v1.0 inicializado`);
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
        if (this.config.debug) console.log('Catalyst SDK: Visitante recuperado de caché (Throttle activo).', this.visitorData);
        return this.visitorData;
      } catch (e) {
        console.error('Catalyst SDK: Error leyendo datos locales, reiniciando visitante.', e);
        // Si falla la lectura local, continuamos al registro nuevo
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

      if (this.config.debug) console.log('Catalyst SDK: Visitante registrado en API.', visitorData);

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
      fields,
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
      fields,
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
    console.log('Catalyst SDK: Evento registrado:', eventName);

    // Si el evento es 'ready' y ya estamos listos, ejecutamos inmediatamente
    if (eventName === 'ready' && (this as any).isReady) {
      try {
        callback((this as any).visitorData);
      } catch (e) {
        console.error(`Catalyst SDK: Error en callback inmediato de 'ready':`, e);
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
  async getOfferwall(mixId: string, fingerprint: string): Promise<OfferwallResponse> {
    if (!fingerprint) {
      fingerprint = this.visitorData?.fingerprint;
    }
    if (!fingerprint) {
      throw new Error('Catalyst SDK: No hay fingerprint de visitante. Asegúrate de que el SDK esté inicializado.');
    }

    const payload = {
      fingerprint,
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

  /**
   * Registra una conversión de Offerwall.
   * @param data Datos de la conversión
   */
  async convertOfferwall(data: Omit<OfferwallConversionRequest, 'fingerprint'>): Promise<OfferwallConversionResponse> {
    if (!this.visitorData?.fingerprint) {
      throw new Error('Catalyst SDK: No hay fingerprint de visitante.');
    }

    const payload: OfferwallConversionRequest = {
      fingerprint: this.visitorData.fingerprint,
      ...data,
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

  // --- Helpers Privados ---

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

function init(): void {
  const placeholder = window.Catalyst as CatalystPlaceholder;

  if (!placeholder || !placeholder._q) {
    console.error('Catalyst SDK: Error crítico. Loader no encontrado.');
    return;
  }

  const catalystInstance = new CatalystCore(placeholder.config);

  // Procesar cola de comandos previos
  placeholder._q.forEach(([method, ...args]) => {
    // Si el método existe directamente en la clase (ej: 'on', 'dispatch')
    if (typeof (catalystInstance as any)[method] === 'function') {
      (catalystInstance as any)[method](...args);
    }
  });

  // Reemplazar global
  window.Catalyst = catalystInstance;

  // Inicializar el visitante y esperar a que esté listo
  catalystInstance
    .initVisitor()
    .then((visitorData) => {
      console.log('Catalyst SDK: Visitante inicializado con éxito:', visitorData);
      // ÚNICO punto de emisión del evento 'ready'
      catalystInstance.dispatch('ready', { catalyst: catalystInstance, visitorData: visitorData });
    })
    .catch((error) => {
      console.error('Catalyst SDK: Error crítico inicializando visitante.', error);
      catalystInstance.dispatch('ready', { catalyst: catalystInstance, visitorData: null });
    });
}

init();

export {};
