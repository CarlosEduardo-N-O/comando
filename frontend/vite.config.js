import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';

// Em desenvolvimento, /api e /sanctum são encaminhados ao Nginx (serviço "web"),
// mantendo frontend e API na mesma origem do navegador (cookies de sessão + CSRF).
const alvo = process.env.VITE_PROXY_TARGET || 'http://localhost:8080';

export default defineConfig({
  plugins: [react()],
  server: {
    host: '0.0.0.0',
    port: 5173,
    strictPort: true,
    // Montagens do Windows não propagam eventos de arquivo para o container.
    watch: { usePolling: true, interval: 300 },
    proxy: {
      '/api': { target: alvo, changeOrigin: false },
      '/sanctum': { target: alvo, changeOrigin: false },
      '/docs': { target: alvo, changeOrigin: false },
    },
  },
  build: {
    sourcemap: false,
    chunkSizeWarningLimit: 700,
  },
});
