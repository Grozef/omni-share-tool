import axios from 'axios'
import { useAuthStore } from '@/stores/auth'

export const api = axios.create({
  baseURL: import.meta.env.VITE_API_URL || 'http://localhost:8000/api',
  headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
})

// Response interceptor: auto-refresh expired tokens
api.interceptors.response.use(
  (response) => response,
  async (error) => {
    const originalRequest = error.config

    if (error.response?.status === 401 && !originalRequest._retry) {
      originalRequest._retry = true
      try {
        const authStore = useAuthStore()
        await authStore.refreshToken()
        originalRequest.headers['Authorization'] = `Bearer ${authStore.token}`
        return api(originalRequest)
      } catch {
        const authStore = useAuthStore()
        authStore.logout()
        window.location.href = '/login'
      }
    }

    return Promise.reject(error)
  }
)

// WebSocket / Echo setup
export function setupEcho(token: string) {
  // Using Laravel Echo with Reverb
  // @ts-ignore
  if (typeof window !== 'undefined' && window.Echo) return window.Echo

  import('laravel-echo').then(({ default: Echo }) => {
    import('pusher-js').then(({ default: Pusher }) => {
      // @ts-ignore
      window.Pusher = Pusher
      // @ts-ignore
      window.Echo = new Echo({
        broadcaster: 'reverb',
        key: import.meta.env.VITE_REVERB_APP_KEY,
        wsHost: import.meta.env.VITE_REVERB_HOST || 'localhost',
        wsPort: import.meta.env.VITE_REVERB_PORT || 8080,
        wssPort: import.meta.env.VITE_REVERB_PORT || 8080,
        forceTLS: import.meta.env.VITE_REVERB_SCHEME === 'https',
        enabledTransports: ['ws', 'wss'],
        authEndpoint: `${import.meta.env.VITE_API_URL || 'http://localhost:8000/api'}/broadcasting/auth`,
        auth: { headers: { Authorization: `Bearer ${token}` } },
      })
    })
  })
}
