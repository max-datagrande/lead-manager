(function () {
  if (typeof window === 'undefined') {
    return;
  }

  // Usar variables globales en lugar de import.meta.env para archivos en /public
  const env = window.__ENV__ || {};

  const ORIGIN = env.PUBLIC_ORIGIN || window.location.origin;
  const Visitor = {
    data: null,
    ready: false,
    baseUrl: null, // Se establecerá dinámicamente

    setApiUrl() {
      const currentHost = window.location.hostname;

      // Detectar entorno basado en el hostname
      if (currentHost === 'localhost' || currentHost.endsWith('.test') || currentHost.endsWith('.local')) {
        // Entorno de desarrollo
        this.baseUrl = 'https://top-api.test';
      } else {
        // Entorno de producción
        this.baseUrl = 'https://top-api.com';
      }

      return this.baseUrl;
    },

    async init() {
      // Establecer la URL de la API según el entorno
      this.setApiUrl();

      try {
        const payload = {
          user_agent: navigator.userAgent,
          referer: this.getReferer() ?? null,
          current_page: window.location.pathname,
          query_params: Object.fromEntries(new URLSearchParams(window.location.search)),
        };

        const res = await fetch(`${this.baseUrl}/v1/visitor/register`, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'Dev-Origin': ORIGIN,
          },
          body: JSON.stringify(payload),
        });

        const json = await res.json();
        this.data = json;
        this.ready = true;

        // Dispara evento global
        window.dispatchEvent(new CustomEvent('visitor:ready', { detail: json }));
        console.log('✅ Visitor listo:', json);
      } catch (err) {
        console.error('Error inicializando visitante:', err);
      }
    },

    async registerLead(fields = {}) {
      if (!this.data?.fingerprint) {
        console.warn('⚠️ There is no fingerprint to register the potential customer.');
        return;
      }

      const payload = {
        fingerprint: this.data.fingerprint,
        fields,
      };

      const res = await fetch(`${this.baseUrl}/v1/leads/register`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          Accept: 'application/json',
        },
        body: JSON.stringify(payload),
      });

      const json = await res.json();
      console.log('🎯 Lead registrado:', json);
      return json;
    },

    async updateLead(fields) {
      if (!this.data?.fingerprint) {
        console.warn('⚠️ There is no fingerprint to update.');
        return;
      }

      const payload = {
        fingerprint: this.data.fingerprint,
        fields,
      };

      const res = await fetch(`${this.baseUrl}/v1/leads/update`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          Accept: 'application/json',
        },
        body: JSON.stringify(payload),
      });

      const json = await res.json();
      console.log('📝 Lead actualizado:', json);
      return json;
    },

    getData() {
      return this.data;
    },
    getReferer() {
      let referer = document.referrer;
      let host = window.location.hostname;
      if (referer.includes(host)) {
        return null;
      }
      return referer;
    },
  };

  // Exponer globalmente
  window.Visitor = Visitor;
  Visitor.init();
})();
