import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
import { resolve } from 'path';

export default defineConfig(({ mode }) => ({
  plugins: [react()],
  base: mode === 'production' ? '/wp-content/plugins/guardianes-formularios/assets/dist/' : '/',
  build: {
    outDir: resolve(__dirname, '../assets/dist'),
    emptyOutDir: true,
    manifest: true,
    rollupOptions: {
      input: {
        auth: resolve(__dirname, 'src/entries/auth.tsx'),
        docente: resolve(__dirname, 'src/entries/docente.tsx'),
        supervisor: resolve(__dirname, 'src/entries/supervisor.tsx'),
        admin: resolve(__dirname, 'src/entries/admin.tsx'),
        comite: resolve(__dirname, 'src/entries/comite.tsx'),
      },
      output: {
        entryFileNames: '[name]-[hash].js',
        chunkFileNames: 'chunks/[name]-[hash].js',
        assetFileNames: 'assets/[name]-[hash][extname]',
        manualChunks: {
          react: ['react', 'react-dom'],
          query: ['@tanstack/react-query'],
        },
      },
    },
  },
  server: {
    origin: 'http://localhost:5173',
    cors: true,
  },
  resolve: {
    alias: {
      '@': resolve(__dirname, 'src'),
    },
  },
}));
