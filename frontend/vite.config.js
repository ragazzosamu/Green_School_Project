import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'

export default defineConfig({
  plugins: [react()],
  base: '/react/',
  server: {
    host: '0.0.0.0',
    port: 5173,
    proxy: {
      '/api': {
        target: 'http://app:80',
        changeOrigin: true,
      },
      // Proxy WebSocket Reverb. Echo apre ws://localhost:5173/app/<key>,
      // Vite tunnella verso Apache che fa proxy a reverb:8080 (vhost.conf).
      // Unica origine = niente CORS, e in dev funziona tutto con un solo URL.
      '/app': {
        target: 'ws://app:80',
        ws: true,
        changeOrigin: true,
      },
    },
  },
  build: {
    outDir: 'dist',
    emptyOutDir: true,
  },
})