<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Catalyst Test Page</title>
  <script src="{{ url('/catalyst/engine.js?landing_id=123&version=1.0') }}"></script>
</head>

<body>
  <h1>Catalyst Test Page</h1>
  <p>Open the developer console to see the output.</p>

  <script>
    // Ejemplo de cómo escuchar eventos del SDK.
    // El evento 'ready' se dispara cuando el SDK está completamente cargado e inicializado.
    Catalyst.on('ready', function(data) {
      console.log('****************************************');
      console.log('CATALYST SDK EVENT: El SDK está listo!', data);
      console.log('****************************************');

      // Ahora es seguro registrar eventos que dependen de que el SDK esté completamente funcional.
      Catalyst.register('post_ready_event', { message: 'Este evento se registró después de que el SDK estuviera listo.' });
    });

    // Esta llamada será encolada por el placeholder y procesada cuando el SDK esté listo.
    Catalyst.register('page_view', {
      url: window.location.pathname
    });

    // También puedes llamarlo con un retardo.
    setTimeout(() => {
      Catalyst.register('delayed_event', {
        delay: '2 seconds'
      });
    }, 2000);

    // Ejemplo de uso con async/await
    (async function() {
      console.log('Esperando a que Catalyst esté listo usando async/await...');
      const data = await Catalyst.ready();
      console.log('¡Catalyst está listo! (obtenido con await)', data);
      Catalyst.register('event_from_await', { source: 'async/await' });
    })();
  </script>
</body>

</html>
