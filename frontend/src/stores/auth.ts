import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { api } from '@/services/api'

interface User {
  id: number
  name: string
  email: string
  portfolio_slug: string | null
  portfolio_enabled: boolean
}

export const useAuthStore = defineStore('auth', () => {
  const user = ref<User | null>(null)
  const token = ref<string | null>(localStorage.getItem('token'))

  const isAuthenticated = computed(() => !!token.value && !!user.value)

  async function login(email: string, password: string) {
    const { data } = await api.post('/auth/login', { email, password })
    token.value = data.token
    user.value = data.user
    localStorage.setItem('token', data.token)
    api.defaults.headers.common['Authorization'] = `Bearer ${data.token}`
  }

  async function register(name: string, email: string, password: string, password_confirmation: string) {
    const { data } = await api.post('/auth/register', { name, email, password, password_confirmation })
    token.value = data.token
    user.value = data.user
    localStorage.setItem('token', data.token)
    api.defaults.headers.common['Authorization'] = `Bearer ${data.token}`
  }

  async function fetchMe() {
    if (!token.value) return
    api.defaults.headers.common['Authorization'] = `Bearer ${token.value}`
    try {
      const { data } = await api.get('/auth/me')
      user.value = data
    } catch {
      logout()
    }
  }

  async function refreshToken() {
    try {
      const { data } = await api.post('/auth/refresh')
      token.value = data.token
      localStorage.setItem('token', data.token)
      api.defaults.headers.common['Authorization'] = `Bearer ${data.token}`
    } catch {
      logout()
    }
  }

  function logout() {
    api.post('/auth/logout').catch(() => {})
    token.value = null
    user.value = null
    localStorage.removeItem('token')
    delete api.defaults.headers.common['Authorization']
  }

  // Auto-initialize
  if (token.value) {
    api.defaults.headers.common['Authorization'] = `Bearer ${token.value}`
  }

  return { user, token, isAuthenticated, login, register, fetchMe, refreshToken, logout }
}, {
  persist: false,
})
