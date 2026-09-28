import { defineConfig } from 'vite';
import preact from '@preact/preset-vite';
import path from 'path';

// Builds the Admin Station only. Output lands in dist/ at stable paths that
// Core\AssetLoader enqueues on the /station/ route.
export default defineConfig({
  plugins: [preact()],
  resolve: {
    alias: {
      '@': path.resolve(__dirname, 'resources/ts'),
    },
  },
  build: {
    outDir: 'dist',
    emptyOutDir: true,
    rollupOptions: {
      input: {
        'admin-station': 'resources/ts/modules/admin-station.ts',
        // The shared drawer stylesheet is its own entry so it emits at a stable
        // path (dist/css/drawer-kit.css), enqueued as a dependency of the
        // Admin Station stylesheet.
        'drawer-kit':    'resources/css/modules/drawer-kit.css',
      },
      output: {
        entryFileNames: 'js/[name].js',
        chunkFileNames: 'js/[name]-[hash].js',
        assetFileNames: (assetInfo) => {
          if (assetInfo.name?.endsWith('.css')) {
            return 'css/[name][extname]';
          }
          return 'assets/[name]-[hash][extname]';
        },
      },
    },
  },
});
