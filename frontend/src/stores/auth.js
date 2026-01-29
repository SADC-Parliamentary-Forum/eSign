import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { $api } from '@/utils/api'

export const useAuthStore = defineStore('auth', () => {
  const token = ref(localStorage.getItem('token'))
  const storedUser = localStorage.getItem('user')
  const user = ref(storedUser && storedUser !== 'undefined' ? JSON.parse(storedUser) : null)

  const isAuthenticated = computed(() => !!user.value)
  const role = computed(() => user.value?.role?.name)

  function setAuth(newToken, newUser) {
    token.value = newToken
    user.value = newUser

    if (newToken) {
      localStorage.setItem('token', newToken)
    }

    if (newUser) {
      localStorage.setItem('user', JSON.stringify(newUser))
    }
  }

  function clearAuth() {
    token.value = null
    user.value = null
    localStorage.removeItem('token')
    localStorage.removeItem('user')
  }

  async function login(email, password) {
    try {
      // 1. Get CSRF Cookie
      // Helper to handle relative path issue if API_URL includes /api
      const apiUrl = import.meta.env.VITE_API_URL || 'http://localhost:8000/api'
      const csrfUrl = apiUrl.endsWith('/api')
        ? apiUrl.replace('/api', '/sanctum/csrf-cookie')
        : `${apiUrl}/sanctum/csrf-cookie`

      await $api(csrfUrl, { method: 'GET' })

      // 2. Login using $api (triggers interceptors for Bot Protection)
      const data = await $api('/auth/login', {
        method: 'POST',
        body: { email, password },
      })

      console.log('Login Response:', data)

      if (!data.access_token && !data.token) {
        throw new Error('Login failed: No access token received from server.')
      }

      // Update State
      // Update State using the centralized helper
      setAuth(data.access_token || data.token, data.user)

      return true
    } catch (error) {
      console.error(error)
      throw error // Re-throw to handle UI feedback
    }
  }

  async function register(userData) {
    try {
      const data = await $api('/auth/register', {
        method: 'POST',
        body: userData,
      })

      setAuth(data.access_token, data.user)
      return true
    } catch (error) {
      console.error(error)
      throw error
    }
  }

  async function fetchUser() {
    try {
      const userData = await $api('/auth/me')

      // Update user state
      user.value = userData
      localStorage.setItem('user', JSON.stringify(userData))

      return userData
    } catch (error) {
      console.error('Failed to fetch user:', error)
      if (error.status === 401) {
        clearAuth()
      }
    }
  }

  async function forgotPassword(email) {
    try {
      await $api('/auth/forgot-password', {
        method: 'POST',
        body: { email },
      })
      return true
    } catch (error) {
      console.error(error)
      throw error
    }
  }

  async function resetPassword(payload) {
    try {
      await $api('/auth/reset-password', {
        method: 'POST',
        body: payload,
      })
      return true
    } catch (error) {
      console.error(error)
      throw error
    }
  }

  return { token, user, isAuthenticated, role, login, register, clearAuth, fetchUser, forgotPassword, resetPassword }
})
