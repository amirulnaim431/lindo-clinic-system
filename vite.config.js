import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
  plugins: [
    laravel({
      input: ['resources/css/app.css', 'resources/js/app.js'],
      refresh: true,
    }),
  ],

  // Important on Windows/IIS/Plesk so Vite doesn't try to crawl outside app root
  server: {
    origin: 'http://localhost',
  },

  build: {
    outDir: 'public/build',
    manifest: true,
    emptyOutDir: true,
    rollupOptions: {
      input: {
        app: 'resources/js/app.js',
      },
    },
  },
});