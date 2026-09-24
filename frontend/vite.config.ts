import react from '@vitejs/plugin-react'
import { defineConfig } from 'vite'

// https://vite.dev/config/
export default defineConfig({
  plugins: [react()],
  server: {
    // Forward backend routes to the Symfony container during local dev.
    // The frontend itself uses relative URLs (see src/api/axios.ts), so
    // these proxied paths are reached as same-origin requests from the
    // browser — no CORS, no cross-origin cookies required.
    proxy: {
      '/api': 'http://localhost:8080',
      '/connect': 'http://localhost:8080',
      '/verify': 'http://localhost:8080',
    },
  },
})
