import axios from 'axios'

// Use a relative base URL so the same build works against any host the
// browser is currently on. In production, Nginx serves both the SPA and
// /api/* on the same origin, so relative requests resolve to the right
// server automatically. In local development, Vite's dev-server proxy
// (see vite.config.ts) forwards /api/* to the Symfony container on
// :8080.
//
// VITE_API_BASE is an opt-in override: leave it unset for normal local
// dev and production; set it only if you must point at a different host
// (e.g. when running the dev server against a remote backend).
const api = axios.create({
    baseURL: import.meta.env.VITE_API_BASE ?? '',
    withCredentials: true,
    headers: {
        'Content-Type': 'application/json',
    },
})

export default api
