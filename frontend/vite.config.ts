import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue';

export default defineConfig({
  plugins: [vue()],
  base: './',
  build: {
    manifest: true,
  },
  server: {
    proxy: {
      '/api': 'http://nginx',
      '/server': 'http://nginx',
    },
  },
});
