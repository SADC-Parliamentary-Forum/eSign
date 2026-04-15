import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { $api } from '@/utils/api'
import { config } from '@/config'
import * as Sentry from '@sentry/vue'
import { logger } from '@/utils/logger'

export const useAuthStore = defineStore('auth', () => {
  const token = ref(localStorage.getItem('token'))
  const storedUser = localStorage.getItem('user')
  const user = ref(storedUser && storedUser !== 'undefined' ? JSON.parse(storedUser) : null)

  const isAuthenticated = computed(() => !!user.value)
  const role = computed(() => user.value?.role?.name ?? user.value?.role?.display_name)
  const userLoading = ref(false)

  function setAuth(newToken, newUser) {
    token.value = newToken
    user.value = newUser

    if (newToken) {
      localStorage.setItem('token', newToken)
    }

    if (newUser) {
      localStorage.setItem('user', JSON.stringify(newUser))

      if (Sentry.getCurrentHub && Sentry.getCurrentHub().getClient()) {
        Sentry.setUser({
          id: newUser.id,
          email: newUser.email,
          ip_address: '{{auto}}' // Let Sentry/GlitchTip capture from server IP
        })
      }
    }
  }

  function clearAuth() {
    token.value = null
    user.value = null
    localStorage.removeItem('token')
    localStorage.removeItem('user')

    if (Sentry.getCurrentHub && Sentry.getCurrentHub().getClient()) {
      Sentry.setUser(null)
    }
  }

  async function login(email, password) {
    try {
      // 1. Get CSRF Cookie
      const apiBaseUrl = config.api.baseUrl || '/api'
      const normalizedApiBaseUrl = apiBaseUrl.replace(/\/+$/, '')
      const csrfUrl = normalizedApiBaseUrl.endsWith('/api')
        ? `${normalizedApiBaseUrl.slice(0, -4)}/sanctum/csrf-cookie`
        : `${normalizedApiBaseUrl}/sanctum/csrf-cookie`

      const csrfResponse = await fetch(csrfUrl, {
        method: 'GET',
        credentials: 'include',
        headers: {
          Accept: 'application/json',
        },
      })

      if (!csrfResponse.ok) {
        throw new Error(`CSRF bootstrap failed with status ${csrfResponse.status}.`)
      }

      // 2. Login using $api (triggers interceptors for Bot Protection)
      const data = await $api('/auth/login', {
        method: 'POST',
        body: { email, password },
      })

      if (!data.access_token && !data.token) {
        throw new Error('Login failed: No access token received from server.')
      }

      // Update State
      // Update State using the centralized helper
      setAuth(data.access_token || data.token, data.user)

      return true
    } catch (error) {
      logger.captureError(error, { context: 'login' })
      const status = error?.status || error?.response?.status
      if (status >= 500 && status < 600) {
        throw new Error('Server is temporarily unavailable (error ' + status + '). Please try again in a few minutes.')
      }
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
      logger.captureError(error, { context: 'register' })
      throw error
    }
  }

  async function fetchUser() {
    userLoading.value = true
    try {
      const userData = await $api('/auth/me')

      // Update user state (ensure we have full user + role for UI)
      user.value = userData
      localStorage.setItem('user', JSON.stringify(userData))

      return userData
    } catch (error) {
      logger.captureError(error, { context: 'fetchUser' })
      const status = error?.status || error?.response?.status
      if (status >= 500 && status < 600) {
        throw new Error('Server is temporarily unavailable. Please try again later.')
      }
      if (error.status === 401) {
        clearAuth()
      }
    } finally {
      userLoading.value = false
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
      logger.captureError(error, { context: 'forgotPassword' })
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
      logger.captureError(error, { context: 'resetPassword' })
      throw error
    }
  }

  return { token, user, isAuthenticated, role, userLoading, login, register, clearAuth, fetchUser, forgotPassword, resetPassword }
})
