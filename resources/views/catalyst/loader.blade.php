(function() {
  if (window.Catalyst && window.Catalyst.register) {
    return;
  }
  // Configuración inicial desde el backend de Laravel
  const config = @json($catalystConfig);

  // Crear el placeholder del objeto Catalyst
  const placeholder = {
    config: config,
    _q: [], // Cola de comandos
  };

  // Métodos que estarán disponibles de inmediato
  const methods = ['register'];
  methods.forEach(method => {
    placeholder[method] = function() {
      placeholder._q.push([method, ...arguments]);
    };
  });

  // Exponer el placeholder globalmente
  window.Catalyst = placeholder;

  // Crear y añadir el script principal del kit de forma asíncrona
  const script = document.createElement('script');
  script.src = '{{ $finalUrl }}';
  script.async = true;
  document.head.appendChild(script);
})();
