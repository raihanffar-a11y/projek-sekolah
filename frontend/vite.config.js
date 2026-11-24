import react from '@vitejs/plugin-react'
import { defineConfig } from 'vite'

// https://vite.dev/config/
export default defineConfig(({ command }) => ({
  plugins: [react()],
  base: command === 'serve' ? '/' : '/EnglishHub/frontend/dist/',
  build: {
    outDir: 'dist',
    emptyOutDir: true,
  },
  server: {
    proxy: {
      '/EnglishHub/api': {
        target: 'http://127.0.0.1:80',
        changeOrigin: true,
      },
    },
  },
}))
