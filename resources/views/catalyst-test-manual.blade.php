<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Catalyst SDK Test Flow (Manual Loader)</title>

  <!-- Catalyst SDK Manual Loader (Script Optimizado) -->
  <script>
    (function(w,d,s,u,c){
      w.Catalyst=w.Catalyst||{_q:[],config:c};
      ['on','dispatch','registerLead','updateLead','getOfferwall','convertOfferwall'].forEach(function(m){
        w.Catalyst[m]=function(){w.Catalyst._q.push([m].concat([].slice.call(arguments)))};
      });
      var j=d.createElement(s),f=d.getElementsByTagName(s)[0];
      j.async=1;j.type='module';j.src=u;f.parentNode.insertBefore(j,f);
    })(window,document,'script','{{ url("/catalyst/v1.0.js") }}?landing_id=999',{
      api_url: "{{ config('catalyst.api_url') }}", // Inyectado por Blade para el test local
      debug: true
    });
  </script>
</head>

<body>
  <div style="font-family: sans-serif; padding: 2rem; max-width: 800px; margin: 0 auto;">
    <h1>Catalyst SDK Integration Test (Manual Mode)</h1>
    <p>Testing the "Script Raro" implementation bypassing engine.js loader.</p>
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
      log('✅ SDK READY (Manual Mode): Visitor session confirmed.', 'green');
      console.log('Visitor Data:', eventData.visitor);

      // Simulamos una acción de usuario (ej: llenar formulario) después de 1 segundo
      setTimeout(() => {
        log('🚀 Iniciando registro de Lead (simulado)...', 'blue');

        // 2. Disparar evento para registrar Lead
        Catalyst.dispatch('lead:register', {
          name: 'Jane Manual',
          email: 'jane.manual@example.com',
          phone: '+9876543210'
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
              company: 'Manual Loader Corp.',
              role: 'Tester'
            });
          }, 1500);

        } else {
          log(`❌ LEAD REGISTER FAILED: ${status.error}`, 'red');
        }
      }

      if (status.type === 'update') {
        if (status.success) {
          log('✅ LEAD UPDATED SUCCESS', 'green');
          log('🎉 Flujo completo (Manual) terminado exitosamente.', 'purple');
        } else {
          log(`❌ LEAD UPDATE FAILED: ${status.error}`, 'red');
        }
      }
    });

    async function testOfferwall() {
      log('Trying to fetch Offerwall Mix 4...', 'blue');
      try {
        // Wait for SDK if not ready (simple check)
        if (window.Catalyst._q) {
           log('SDK still loading (proxy active)... waiting 1s', 'orange');
           await new Promise(r => setTimeout(r, 1000));
        }

        const offers = await Catalyst.getOfferwall('4');
        log(`Offerwall Loaded: ${offers.data.length} offers`, 'green');
        console.log(offers);

        if (offers.data.length > 0) {
          // Test conversion
          log('Testing conversion...', 'purple');
          const conv = await Catalyst.convertOfferwall({
             offer_id: 'TEST-OFFER-1',
             amount: 1.50,
             currency: 'USD'
          });
          log(`Conversion registered: ${conv.status}`, 'green');
        }

      } catch (e) {
        log(`Error: ${e.message}`, 'red');
        console.error(e);
      }
    }
  </script>
  <div style="margin-top: 20px;">
    <button onclick="testOfferwall()" style="padding: 10px; background: #333; color: white; border: none; cursor: pointer;">
      Test Offerwall Fetch
    </button>
  </div>
</body>

</html>
