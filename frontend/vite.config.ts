import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue';

export default defineConfig(({ mode }) => ({
  plugins: [vue()],
  base: mode === 'capacitor' ? './' : '/',
  build: {
    manifest: true,
  },
  server: {
    proxy: {
      '/api': 'http://nginx',
      '/server': 'http://nginx',
    },
  },
}));
