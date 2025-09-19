import '../css/app.css';

import { createInertiaApp } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createRoot } from 'react-dom/client';
import { initializeTheme } from './hooks/use-appearance';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
  title: (title) => (title ? `${title} - ${appName}` : appName),
  resolve: async (name) => {
    const pages = {
      ...import.meta.glob('./pages/**/*.tsx'),
      ...import.meta.glob('./pages/**/*.jsx'),
    };
    // Primero intenta resolver como .tsx, luego como .jsx
    try {
      return await resolvePageComponent(`./pages/${name}.tsx`, pages);
    } catch (error) {
      console.log(error);
      return await resolvePageComponent(`./pages/${name}.jsx`, pages);
    }
  },
  setup({ el, App, props }) {
    const root = createRoot(el);

    root.render(<App {...props} />);
  },
  progress: {
    color: '#272931',
  },
});

// This will set light / dark mode on load...
initializeTheme();
