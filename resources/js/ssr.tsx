import { createInertiaApp } from '@inertiajs/react';
import createServer from '@inertiajs/react/server';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import ReactDOMServer from 'react-dom/server';
import { type RouteName, route } from 'ziggy-js';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createServer((page) =>
  createInertiaApp({
    page,
    render: ReactDOMServer.renderToString,
    title: (title) => (title ? `${title} - ${appName}` : appName),
    resolve: async (name) => {
      const pages = {
        ...import.meta.glob('./pages/**/*.tsx'),
        ...import.meta.glob('./pages/**/*.jsx'),
      };
      // Primero intenta resolver como .tsx, luego como .jsx
      try {
        return await resolvePageComponent(`./pages/${name}.tsx`, pages);
      } catch {
        return await resolvePageComponent(`./pages/${name}.jsx`, pages);
      }
    },
    setup: ({ App, props }) => {
      /* eslint-disable */
      // @ts-expect-error
      global.route<RouteName> = (name, params, absolute) =>
        route(name, params as any, absolute, {
          // @ts-expect-error
          ...page.props.ziggy,
          // @ts-expect-error
          location: new URL(page.props.ziggy.location),
        });
      /* eslint-enable */

      return <App {...props} />;
    },
  }),
);
