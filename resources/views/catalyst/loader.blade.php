(function() {
  if (window.Catalyst && window.Catalyst.register) {
    return;
  }
  // Configuración inicial desde el backend de Laravel
  const backendConfig = @json($catalystConfig);

  // El JS es autosuficiente: detecta el modo debug desde la URL de la página
  const pageUrl = new URL(window.location.href);
  const isDebug = pageUrl.searchParams.get('catalyst_debug') === '1';

  // Fusionar la configuración del backend con la detectada en el frontend
  const finalConfig = { ...backendConfig, debug: isDebug };

  // Crear el placeholder del objeto Catalyst
  const placeholder = {
    config: finalConfig, // Usar la configuración final
    _q: [], // Cola de comandos
  };

  // Métodos que estarán disponibles de inmediato
  const methods = ['register', 'on', 'dispatch'];
  methods.forEach(method => {
    placeholder[method] = function() {
      placeholder._q.push([method, ...arguments]);
    };
  });

  // Función de ayuda para usar con async/await
  placeholder.ready = function() {
    return new Promise(resolve => {
      // Si el SDK real ya se cargó, resuelve de inmediato.
      if (window.Catalyst && !window.Catalyst._q) {
        resolve({ catalyst: window.Catalyst });
      } else {
        // Si no, espera al evento 'ready'.
        placeholder.on('ready', resolve);
      }
    });
  };

  // Exponer el placeholder globalmente
  window.Catalyst = placeholder;

  // Crear y añadir el script principal del kit de forma asíncrona
  const script = document.createElement('script');
  script.src = '{{ $finalUrl }}';
  script.async = true;
  script.type = 'module';
  document.head.appendChild(script);
})();
