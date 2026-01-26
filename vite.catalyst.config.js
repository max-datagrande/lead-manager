import laravel from 'laravel-vite-plugin';
import { resolve } from 'path';
import { defineConfig } from 'vite';

export default defineConfig({
  publicDir: false,

  plugins: [
    laravel({
      input: ['resources/js/catalyst/v1.0.ts'],
      buildDirectory: 'cdn/catalyst',
      hotFile: 'public/catalyst.hot',
    }),
  ],

  build: {
    emptyOutDir: true,
    outDir: resolve(__dirname, 'public/cdn/catalyst'),
    manifest: 'manifest.json',
    assetsDir: 'kit',
  },
});
