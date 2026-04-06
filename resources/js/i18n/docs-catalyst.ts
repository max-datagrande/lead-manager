import type { Dictionary } from './types';

const dictionary: Dictionary = {
  nav: {
    overview: { en: 'Overview', es: 'Resumen' },
    installation: { en: 'Installation', es: 'Instalaci\u00f3n' },
    visitor: { en: 'Visitor', es: 'Visitante' },
    leads: { en: 'Leads', es: 'Leads' },
    share_leads: { en: 'Share Leads', es: 'Share Leads' },
    offerwall: { en: 'Offerwall', es: 'Offerwall' },
    events: { en: 'Events', es: 'Eventos' },
    examples: { en: 'Examples', es: 'Ejemplos' },
  },

  overview: {
    title: { en: 'Catalyst SDK', es: 'Catalyst SDK' },
    description: {
      en: 'The Catalyst SDK manages visitor identification, lead registration, and event-driven communication between landing pages and the DataLeads API. It handles fingerprinting, session caching, and provides a pub/sub interface for all interactions.',
      es: 'El SDK de Catalyst gestiona la identificaci\u00f3n de visitantes, el registro de leads y la comunicaci\u00f3n basada en eventos entre landing pages y la API de DataLeads. Maneja fingerprinting, cach\u00e9 de sesi\u00f3n y provee una interfaz pub/sub para todas las interacciones.',
    },
    config_title: { en: 'Configuration Options', es: 'Opciones de Configuraci\u00f3n' },
    config_api_url: {
      en: 'Base URL of the DataLeads API (required)',
      es: 'URL base de la API de DataLeads (requerido)',
    },
    config_debug: {
      en: 'Enable console logging for all events and API calls',
      es: 'Habilitar logs en consola para todos los eventos y llamadas API',
    },
    config_environment: {
      en: 'Environment identifier (local, production)',
      es: 'Identificador de entorno (local, production)',
    },
    config_dev_origin: {
      en: 'Override origin header for local development',
      es: 'Sobreescribir header de origen para desarrollo local',
    },
    architecture_title: { en: 'Architecture', es: 'Arquitectura' },
    architecture_desc: {
      en: 'The SDK uses a placeholder queue pattern (similar to Google Analytics). Before the SDK loads, method calls are queued. Once loaded, the queue is processed sequentially, ensuring proper order of operations.',
      es: 'El SDK usa un patr\u00f3n de cola con placeholder (similar a Google Analytics). Antes de que el SDK cargue, las llamadas a m\u00e9todos se encolan. Una vez cargado, la cola se procesa secuencialmente, asegurando el orden correcto de operaciones.',
    },
  },

  installation: {
    title: { en: 'Installation', es: 'Instalaci\u00f3n' },
    description: {
      en: 'Insert the following snippet into the <head> of your HTML page. This loads the SDK asynchronously and queues any method calls made before it finishes loading.',
      es: 'Inserta el siguiente snippet en el <head> de tu p\u00e1gina HTML. Esto carga el SDK de forma as\u00edncrona y encola cualquier llamada a m\u00e9todos hecha antes de que termine de cargar.',
    },
    important_note: {
      en: 'You must configure the api_url in the config object to point to your DataLeads API instance.',
      es: 'Debes configurar el api_url en el objeto config para que apunte a tu instancia de la API de DataLeads.',
    },
    astro_note: {
      en: 'If you are using Astro, add the is:inline attribute to the script tag.',
      es: 'Si usas Astro, a\u00f1ade el atributo is:inline a la etiqueta script.',
    },
    comparison_title: { en: 'Loader vs Manual', es: 'Loader vs Manual' },
    comparison_loader: {
      en: 'Automatic configuration injected by the server. Ideal for Laravel Blade landings.',
      es: 'Configuraci\u00f3n autom\u00e1tica inyectada por el servidor. Ideal para landings con Laravel Blade.',
    },
    comparison_manual: {
      en: 'Manual configuration in HTML. Ideal for SPAs, WordPress, or static sites.',
      es: 'Configuraci\u00f3n manual en HTML. Ideal para SPAs, WordPress o sitios est\u00e1ticos.',
    },
  },

  visitor: {
    title: { en: 'Visitor Management', es: 'Gesti\u00f3n de Visitantes' },
    description: {
      en: 'The SDK automatically manages visitor identification through fingerprinting. On first visit, a unique fingerprint is generated and stored. Subsequent visits within 15 minutes use the cached session.',
      es: 'El SDK gestiona autom\u00e1ticamente la identificaci\u00f3n de visitantes mediante fingerprinting. En la primera visita, se genera y almacena un fingerprint \u00fanico. Visitas posteriores dentro de 15 minutos usan la sesi\u00f3n en cach\u00e9.',
    },
    init_title: { en: 'initVisitor()', es: 'initVisitor()' },
    init_desc: {
      en: 'Called automatically during SDK initialization. Checks for a cached session (15-minute throttle cookie). If found, loads from localStorage. Otherwise, registers a new visitor via the API.',
      es: 'Se llama autom\u00e1ticamente durante la inicializaci\u00f3n del SDK. Verifica si hay una sesi\u00f3n en cach\u00e9 (cookie de throttle de 15 minutos). Si la encuentra, carga desde localStorage. De lo contrario, registra un nuevo visitante via la API.',
    },
    return_title: { en: 'Return Type: VisitorData', es: 'Tipo de retorno: VisitorData' },
    return_desc: {
      en: 'Contains the visitor fingerprint, device information, bot detection flag, and geolocation data (IP, city, region, country, postal code, timezone).',
      es: 'Contiene el fingerprint del visitante, informaci\u00f3n del dispositivo, flag de detecci\u00f3n de bot y datos de geolocalizaci\u00f3n (IP, ciudad, regi\u00f3n, pa\u00eds, c\u00f3digo postal, zona horaria).',
    },
    throttle_title: { en: 'Session Throttle', es: 'Throttle de Sesi\u00f3n' },
    throttle_desc: {
      en: 'The SDK implements a 15-minute throttle using localStorage and cookies. Page reloads within this window use cached data without making API calls. The ready event fires just as quickly with cached data.',
      es: 'El SDK implementa un throttle de 15 minutos usando localStorage y cookies. Las recargas de p\u00e1gina dentro de esta ventana usan datos en cach\u00e9 sin hacer llamadas API. El evento ready se dispara igual de r\u00e1pido con datos cacheados.',
    },
    fingerprint_title: { en: 'getFingerprint()', es: 'getFingerprint()' },
    fingerprint_desc: {
      en: "Returns the current visitor's fingerprint string, or null if not yet initialized.",
      es: 'Retorna el string del fingerprint del visitante actual, o null si a\u00fan no se ha inicializado.',
    },
  },

  leads: {
    title: { en: 'Lead Management', es: 'Gesti\u00f3n de Leads' },
    description: {
      en: 'Register and update leads associated with the current visitor. Leads can be managed through events (dispatch) or direct async method calls.',
      es: 'Registra y actualiza leads asociados al visitante actual. Los leads se pueden gestionar mediante eventos (dispatch) o llamadas directas a m\u00e9todos async.',
    },
    register_title: { en: 'registerLead(fields)', es: 'registerLead(fields)' },
    register_desc: {
      en: 'Sends form data to create a Lead associated with the current visitor. Can be called directly (returns a Promise) or via event dispatch.',
      es: 'Env\u00eda datos del formulario para crear un Lead asociado al visitante actual. Puede llamarse directamente (retorna una Promise) o via dispatch de evento.',
    },
    update_title: { en: 'updateLead(fields)', es: 'updateLead(fields)' },
    update_desc: {
      en: 'Adds or modifies information for an already registered lead. This will fail if the visitor has not been previously registered as a lead.',
      es: 'A\u00f1ade o modifica informaci\u00f3n de un lead ya registrado. Fallar\u00e1 si el visitante no ha sido registrado previamente como lead.',
    },
    event_mode_title: { en: 'Event Mode', es: 'Modo Eventos' },
    event_mode_desc: {
      en: 'Use Catalyst.dispatch() to trigger lead actions. Listen for results on the lead:status event.',
      es: 'Usa Catalyst.dispatch() para disparar acciones de leads. Escucha los resultados en el evento lead:status.',
    },
    async_mode_title: { en: 'Async Mode', es: 'Modo Async' },
    async_mode_desc: {
      en: 'Call methods directly for Promise-based flow control. Ideal for complex validations or dependent action chains.',
      es: 'Llama a los m\u00e9todos directamente para control de flujo basado en Promises. Ideal para validaciones complejas o cadenas de acciones dependientes.',
    },
    warning_update: {
      en: "Always wait for registerLead to complete before calling updateLead. Use await or listen for the lead:status event with type: 'register' and success: true.",
      es: "Siempre espera a que registerLead complete antes de llamar updateLead. Usa await o escucha el evento lead:status con type: 'register' y success: true.",
    },
  },

  share_leads: {
    title: { en: 'Share Leads (Ping-Post)', es: 'Share Leads (Ping-Post)' },
    description: {
      en: 'Dispatch leads to ping-post workflows for real-time lead distribution to buyers. The shareLead method uses the build-in-one pattern: it creates/updates the lead and dispatches in a single request.',
      es: 'Despacha leads a workflows de ping-post para distribuci\u00f3n en tiempo real a compradores. El m\u00e9todo shareLead usa el patr\u00f3n build-in-one: crea/actualiza el lead y despacha en un solo request.',
    },
    method_title: { en: 'shareLead(options)', es: 'shareLead(options)' },
    method_desc: {
      en: "Dispatches the current visitor's lead to a specified workflow. Requires the visitor to be initialized (fingerprint available).",
      es: 'Despacha el lead del visitante actual a un workflow especificado. Requiere que el visitante est\u00e9 inicializado (fingerprint disponible).',
    },
    options_title: { en: 'ShareLeadOptions', es: 'ShareLeadOptions' },
    opt_workflow: {
      en: 'workflowId (required) \u2014 The ID of the workflow to dispatch to',
      es: 'workflowId (requerido) \u2014 El ID del workflow al cual despachar',
    },
    opt_fields: {
      en: 'fields (optional) \u2014 Lead fields to create/update before dispatching',
      es: 'fields (opcional) \u2014 Campos del lead a crear/actualizar antes de despachar',
    },
    opt_create: {
      en: "createOnMiss (optional, default: true) \u2014 If true, creates the lead if it doesn't exist yet",
      es: 'createOnMiss (opcional, default: true) \u2014 Si es true, crea el lead si no existe a\u00fan',
    },
    response_title: { en: 'ShareLeadResponse', es: 'ShareLeadResponse' },
    response_desc: {
      en: 'Returns dispatch_uuid, status, strategy_used, final_price, and total_duration_ms for sync dispatches. For async, returns queued: true with workflow_id.',
      es: 'Retorna dispatch_uuid, status, strategy_used, final_price y total_duration_ms para despachos sync. Para async, retorna queued: true con workflow_id.',
    },
  },

  offerwall: {
    title: { en: 'Offerwall', es: 'Offerwall' },
    description: {
      en: 'Load offerwall offers and register conversions directly from the SDK.',
      es: 'Carga ofertas de offerwall y registra conversiones directamente desde el SDK.',
    },
    get_title: { en: 'getOfferwall(options)', es: 'getOfferwall(options)' },
    get_desc: {
      en: 'Retrieves available offers for the current visitor based on an Offerwall Mix ID. Optionally specify a placement for reporting.',
      es: 'Obtiene las ofertas disponibles para el visitante actual basado en un Offerwall Mix ID. Opcionalmente especifica un placement para reportes.',
    },
    get_options: {
      en: 'mixId (required), placement (optional), fingerprint (optional override), data (optional extra params)',
      es: 'mixId (requerido), placement (opcional), fingerprint (override opcional), data (par\u00e1metros extra opcionales)',
    },
    convert_title: { en: 'convertOfferwall(data)', es: 'convertOfferwall(data)' },
    convert_desc: {
      en: 'Registers that the user completed an offer. The offer_token is required and is unique to each offer impression \u2014 do not reuse it between sessions.',
      es: 'Registra que el usuario complet\u00f3 una oferta. El offer_token es requerido y es \u00fanico para cada impresi\u00f3n de la oferta \u2014 no lo reutilices entre sesiones.',
    },
    convert_options: {
      en: 'offer_token (required), amount (required), offer_id (optional), currency (optional), transaction_id (optional)',
      es: 'offer_token (requerido), amount (requerido), offer_id (opcional), currency (opcional), transaction_id (opcional)',
    },
  },

  events: {
    title: { en: 'Events Reference', es: 'Referencia de Eventos' },
    description: {
      en: 'The SDK uses a pub/sub event system. Use Catalyst.on(eventName, callback) to listen for events.',
      es: 'El SDK usa un sistema de eventos pub/sub. Usa Catalyst.on(eventName, callback) para escuchar eventos.',
    },
    on_title: { en: 'on(eventName, callback)', es: 'on(eventName, callback)' },
    on_desc: {
      en: "Registers an event listener. If the event is 'ready' and the SDK is already initialized, the callback fires immediately.",
      es: "Registra un listener de evento. Si el evento es 'ready' y el SDK ya est\u00e1 inicializado, el callback se ejecuta inmediatamente.",
    },
    ready_title: { en: 'Event: ready', es: 'Evento: ready' },
    ready_desc: {
      en: 'Fired once when the SDK has loaded and the visitor session is confirmed. Always wrap your logic within this listener. Payload: { catalyst, visitorData }',
      es: 'Se dispara una vez cuando el SDK ha cargado y la sesi\u00f3n del visitante est\u00e1 confirmada. Siempre envuelve tu l\u00f3gica dentro de este listener. Payload: { catalyst, visitorData }',
    },
    lead_status_title: { en: 'Event: lead:status', es: 'Evento: lead:status' },
    lead_status_desc: {
      en: "Unified event for lead operation results. Payload: { type: 'register'|'update', success: boolean, data?: object, error?: string }",
      es: "Evento unificado para resultados de operaciones de leads. Payload: { type: 'register'|'update', success: boolean, data?: object, error?: string }",
    },
    share_status_title: { en: 'Event: share:status', es: 'Evento: share:status' },
    share_status_desc: {
      en: 'Fired after a shareLead dispatch completes. Payload: { success: boolean, workflowId, data?: object, error?: string }',
      es: 'Se dispara despu\u00e9s de que un despacho de shareLead completa. Payload: { success: boolean, workflowId, data?: object, error?: string }',
    },
    offerwall_loaded_title: { en: 'Event: offerwall:loaded', es: 'Evento: offerwall:loaded' },
    offerwall_loaded_desc: {
      en: 'Fired when getOfferwall successfully returns offers. Payload: { mixId, count }',
      es: 'Se dispara cuando getOfferwall retorna ofertas exitosamente. Payload: { mixId, count }',
    },
    offerwall_error_title: { en: 'Event: offerwall:error', es: 'Evento: offerwall:error' },
    offerwall_error_desc: {
      en: 'Fired when getOfferwall fails. Payload: { mixId, error }',
      es: 'Se dispara cuando getOfferwall falla. Payload: { mixId, error }',
    },
    offerwall_conversion_title: { en: 'Event: offerwall:conversion', es: 'Evento: offerwall:conversion' },
    offerwall_conversion_desc: {
      en: 'Fired after convertOfferwall completes. Payload: { success: boolean, data?: object, error?: object }',
      es: 'Se dispara despu\u00e9s de que convertOfferwall completa. Payload: { success: boolean, data?: object, error?: object }',
    },
  },

  examples: {
    title: { en: 'Usage Examples', es: 'Ejemplos de Uso' },
    description: {
      en: 'Complete integration examples showing common patterns and flows.',
      es: 'Ejemplos completos de integraci\u00f3n mostrando patrones y flujos comunes.',
    },
    basic_title: { en: 'Basic: Register Lead on Form Submit', es: 'B\u00e1sico: Registrar Lead al Enviar Formulario' },
    basic_desc: {
      en: 'Wait for the SDK to be ready, then register a lead when the user submits a form.',
      es: 'Espera a que el SDK est\u00e9 listo, luego registra un lead cuando el usuario env\u00eda un formulario.',
    },
    multi_step_title: {
      en: 'Multi-Step: Register \u2192 Update \u2192 Share',
      es: 'Multi-Paso: Registrar \u2192 Actualizar \u2192 Compartir',
    },
    multi_step_desc: {
      en: 'Chain multiple operations using async/await for a multi-step funnel.',
      es: 'Encadena m\u00faltiples operaciones usando async/await para un funnel de m\u00faltiples pasos.',
    },
    offerwall_title: { en: 'Offerwall: Load and Convert', es: 'Offerwall: Cargar y Convertir' },
    offerwall_desc: {
      en: 'Fetch offers and handle user conversions.',
      es: 'Obtener ofertas y manejar conversiones del usuario.',
    },
    faq_title: { en: 'FAQ', es: 'Preguntas Frecuentes' },
    faq_update_q: {
      en: "Why aren't my Lead changes saved if I call update immediately?",
      es: '\u00bfPor qu\u00e9 mis cambios en el Lead no se guardan si llamo a update inmediatamente?',
    },
    faq_update_a: {
      en: 'updateLead requires the visitor to already have a Lead ID. If you trigger register and update simultaneously, the update might arrive before registration finishes. Use await or listen for lead:status.',
      es: 'updateLead requiere que el visitante ya tenga un Lead ID. Si disparas register y update simult\u00e1neamente, el update podr\u00eda llegar antes de que el registro termine. Usa await o escucha lead:status.',
    },
    faq_reload_q: {
      en: 'Does every page reload count as a new visit?',
      es: '\u00bfCada recarga de p\u00e1gina cuenta como una nueva visita?',
    },
    faq_reload_a: {
      en: 'No. The SDK implements a 15-minute throttle using localStorage and cookies. Reloads within this window use cached data without API calls.',
      es: 'No. El SDK implementa un throttle de 15 minutos usando localStorage y cookies. Las recargas dentro de esta ventana usan datos en cach\u00e9 sin llamadas API.',
    },
    faq_error_q: {
      en: 'What happens if the API fails on init?',
      es: '\u00bfQu\u00e9 pasa si la API falla al iniciar?',
    },
    faq_error_a: {
      en: 'The SDK captures the error internally and logs it if debug: true. The ready event will NOT fire, preventing lead operations without a valid session.',
      es: 'El SDK captura el error internamente y lo logea si debug: true. El evento ready NO se disparar\u00e1, previniendo operaciones de leads sin una sesi\u00f3n v\u00e1lida.',
    },
    faq_debug_q: {
      en: 'Where can I see debug logs?',
      es: '\u00bfD\u00f3nde veo los logs de depuraci\u00f3n?',
    },
    faq_debug_a: {
      en: 'Set debug: true in the config object. This prints every event and API response to the browser console.',
      es: 'Configura debug: true en el objeto config. Esto imprime cada evento y respuesta API en la consola del navegador.',
    },
    faq_auto_q: {
      en: 'Should I register the lead automatically on page load?',
      es: '\u00bfDebo registrar el lead autom\u00e1ticamente al cargar la p\u00e1gina?',
    },
    faq_auto_a: {
      en: 'Generally no \u2014 lead registration should be a voluntary user action. Exception: in all-in-one flows where you need to show an offerwall immediately, register the lead inside the ready event.',
      es: 'Generalmente no \u2014 el registro del lead debe ser una acci\u00f3n voluntaria del usuario. Excepci\u00f3n: en flujos all-in-one donde necesitas mostrar un offerwall inmediatamente, registra el lead dentro del evento ready.',
    },
  },
};

export default dictionary;
