import { resolve } from 'path';
import { defineConfig } from 'vite';

export default defineConfig({
  // No procesar la carpeta public para evitar duplicados.
  publicDir: false,

  build: {
    // Limpiar el directorio de salida antes de cada build.
    emptyOutDir: true,

    // La carpeta de salida será public/cdn.
    outDir: resolve(__dirname, 'public/cdn/catalyst'),

    // Generar el mapa de archivos con un nombre específico.
    manifest: 'manifest.json',

    // Especificar el nombre de la carpeta de assets.
    assetsDir: 'kit',

    rollupOptions: {
      input: {
        'v1.0': resolve(__dirname, 'resources/js/catalyst/v1.0.js'),
      },
    },
  },
});
