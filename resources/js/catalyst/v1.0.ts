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
 * Define la forma del objeto "placeholder" que existe en `window` antes de la inicialización.
 */
interface CatalystPlaceholder {
  _q: [string, ...any[]][];
  config: CatalystConfig;
}

/**
 * Extiende la interfaz global `Window` para que TypeScript conozca `window.Catalyst`.
 * Puede ser la instancia real (CatalystCore) o el placeholder.
 */
declare global {
  interface Window {
    Catalyst: CatalystCore | CatalystPlaceholder;
  }
}

type EventCallback = (data: any) => void;

// ===================================================================================
// CLASE PRINCIPAL DEL SDK
// ===================================================================================

class CatalystCore {
  config: CatalystConfig;
  listeners: Record<string, EventCallback[]>;
  landingId: string | null;

  constructor(config: CatalystConfig) {
    this.config = config;
    this.listeners = {}; // Almacén para los listeners de eventos
    this.landingId = this.getLandingId();

    if (this.config.debug) {
      this.enableDebugMode();
    }

    if (!this.landingId) {
      console.error("Catalyst SDK: El parámetro 'landing_id' es requerido.");
      return;
    }

    console.log(`Catalyst SDK v1.0 inicializado para Landing ID: ${this.landingId}`);
  }

  getLandingId(): string | null {
    const currentScript = document.currentScript as HTMLScriptElement | null;
    if (!currentScript) {
      console.error('Catalyst SDK: No se pudo determinar el script actual.');
      return null;
    }
    const scriptUrl = new URL(currentScript.src);
    return scriptUrl.searchParams.get('landing_id');
  }

  enableDebugMode(): void {
    console.group('Catalyst SDK [Debug Mode]');
    console.log('Configuración recibida:', this.config);
    if (this.config.session) {
      console.log('Datos de sesión:', this.config.session);
    }
    console.groupEnd();
  }

  /**
   * Registra un nuevo evento.
   * @param {string} eventName - El nombre del evento.
   * @param {object} [data={}] - Datos adicionales para el evento.
   */
  register(eventName: string, data: Record<string, any> = {}): void {
    if (!eventName) {
      console.error('Catalyst SDK: El nombre del evento es requerido para el método register.');
      return;
    }

    console.log(`Evento registrado: ${eventName}`, data);
    // Aquí iría la lógica para enviar el evento a un backend, por ejemplo.
  }

  /**
   * Registra un callback para un evento específico.
   * @param {string} eventName - El nombre del evento a escuchar.
   * @param {function} callback - La función a ejecutar cuando el evento es emitido.
   */
  on(eventName: string, callback: EventCallback): void {
    if (!this.listeners[eventName]) {
      this.listeners[eventName] = [];
    }
    this.listeners[eventName].push(callback);
  }

  /**
   * Emite un evento, notificando a todos los listeners registrados.
   * @param {string} eventName - El nombre del evento a emitir.
   * @param {object} [data={}] - Datos para pasar a los callbacks.
   */
  dispatch(eventName: string, data: Record<string, any> = {}): void {
    if (this.config.debug) {
      console.log(`Catalyst Event Dispatched: ${eventName}`, data);
    }
    if (this.listeners[eventName]) {
      this.listeners[eventName].forEach((callback) => {
        try {
          callback(data);
        } catch (e) {
          console.error(`Catalyst SDK: Error en un listener del evento '${eventName}':`, e);
        }
      });
    }
  }
}

/**
 * ===================================================================================
 * FUNCIÓN DE INICIALIZACIÓN
 * ===================================================================================
 *
 * El script que se carga primero (`catalyst/engine.js`) es el Recepcionista del restaurante.
 *
 * El Recepcionista es rápido y crea un objeto `window.Catalyst` falso (un "placeholder")
 * con una libreta de pedidos llamada `_q` (la cola).
 *
 * Cuando un cliente (tu código) quiere hacer un pedido (ej: `Catalyst.register(...)`),
 * el Recepcionista lo anota en la libreta `_q` porque el Chef aún no ha llegado.
 *
 * Esta función `init()` es lo PRIMERO que hace el Chef cuando finalmente llega a la cocina.
 */

function init(): void {
  console.log('Catalyst SDK: Inicializando...');
  // PASO 1: El Chef busca al Recepcionista para pedirle la libreta de pedidos.
  // `window.Catalyst` en este punto es el objeto FALSO que creó el Recepcionista.
  const placeholder = window.Catalyst as CatalystPlaceholder;

  // PASO 2: El Chef comprueba si el Recepcionista hizo bien su trabajo.
  // Si no hay un `placeholder` o no tiene una libreta `_q`, algo salió muy mal.
  if (!placeholder || !placeholder._q) {
    console.error('Catalyst SDK: El script de carga no funcionó. No se encontraron pedidos pendientes.');
    return;
  }

  // PASO 3: El Chef se prepara para cocinar.
  // Crea una instancia REAL de sí mismo, usando la configuración que el Recepcionista ya tenía guardada.
  const catalystInstance = new CatalystCore(placeholder.config);

  // PASO 4: El Chef revisa la libreta `_q` y cocina todos los pedidos pendientes, uno por uno.
  // `placeholder._q` es un array de pedidos, ej: [ ['register', 'arg1'], ['track', 'arg1', 'arg2'] ]
  placeholder._q.forEach(([method, ...args]) => {
    // Para cada pedido, comprueba si es una receta que él conoce (si el método existe en la clase `Catalyst`).
    if (typeof catalystInstance[method] === 'function') {
      // Si la receta existe, la cocina usando los ingredientes (argumentos) que se anotaron.
      // Esto es como hacer `catalystInstance.register('arg1')`
      catalystInstance[method](...args);
    }
  });

  // PASO 5: El Chef despide al Recepcionista y toma su lugar.
  // Reemplaza el objeto `window.Catalyst` FALSO por la instancia REAL del Chef.
  // A partir de ahora, cualquier llamada a `Catalyst.register(...)` será atendida
  // directamente por el Chef, sin pasar por la libreta.
  window.Catalyst = catalystInstance;

  // PASO 6: El Chef anuncia que el restaurante está abierto.
  // Emite el evento 'ready' para que todos sepan que ya puede tomar pedidos en tiempo real.
  catalystInstance.dispatch('ready', { catalyst: catalystInstance });
}

// ¡Que entre el Chef! Llamamos a la función para que todo el proceso comience.
init();

// (Tratar este archivo como un módulo para permitir declaraciones globales).
export {};
