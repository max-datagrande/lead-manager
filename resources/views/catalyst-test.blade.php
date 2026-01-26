<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Catalyst SDK Test Flow</title>
  <!-- Simulamos cargar el SDK con un Landing ID -->
  <script src="{{ url('/catalyst/engine.js?landing_id=123&version=1.0') }}"></script>
</head>

<body>
  <div style="font-family: sans-serif; padding: 2rem; max-width: 800px; margin: 0 auto;">
    <h1>Catalyst SDK Integration Test</h1>
    <p>Open Developer Console (F12) to see detailed logs.</p>

    <div id="status-log" style="background: #f4f4f4; padding: 1rem; border-radius: 8px; font-family: monospace;">
      <div>[WAITING] Waiting for SDK to be ready...</div>
    </div>
  </div>

  <script>
    function log(message, color = 'black') {
      const el = document.getElementById('status-log');
      const div = document.createElement('div');
      div.style.color = color;
      div.style.marginTop = '5px';
      div.innerText = `[${new Date().toLocaleTimeString()}] ${message}`;
      el.appendChild(div);
      console.log(`%c${message}`, `color: ${color}`);
    }

    // 1. Esperar a que el SDK y el Visitante estén listos
    Catalyst.on('ready', (eventData) => {
      log('✅ SDK READY: Visitor session confirmed.', 'green');
      console.log('Visitor Data:', eventData.visitor);

      // Simulamos una acción de usuario (ej: llenar formulario) después de 1 segundo
      setTimeout(() => {
        log('🚀 Iniciando registro de Lead (simulado)...', 'blue');

        // 2. Disparar evento para registrar Lead
        Catalyst.dispatch('lead:register', {
          name: 'John Doe',
          email: 'john.doe@example.com',
          phone: '+1234567890'
        });
      }, 1000);
    });

    // 3. Escuchar el estado de las operaciones de Lead (Register y Update)
    Catalyst.on('lead:status', (status) => {
      console.log('Lead Status Event:', status);

      if (status.type === 'register') {
        if (status.success) {
          log('✅ LEAD REGISTERED SUCCESS', 'green');

          // 4. Solo si el registro fue exitoso, intentamos un Update
          setTimeout(() => {
            log('🔄 Intentando actualizar Lead...', 'orange');
            Catalyst.dispatch('lead:update', {
              company: 'Datagrande Inc.',
              role: 'Developer'
            });
          }, 1500);

        } else {
          log(`❌ LEAD REGISTER FAILED: ${status.error}`, 'red');
        }
      }

      if (status.type === 'update') {
        if (status.success) {
          log('✅ LEAD UPDATED SUCCESS', 'green');
          log('🎉 Flujo completo terminado exitosamente.', 'purple');
        } else {
          log(`❌ LEAD UPDATE FAILED: ${status.error}`, 'red');
        }
      }
    });
  </script>
</body>

</html>
