import { defineStore } from 'pinia'
import { ref, computed } from 'vue'

export const useAuthStore = defineStore('auth', () => {
    const token = ref(localStorage.getItem('token'))
    const user = ref(JSON.parse(localStorage.getItem('user') || 'null'))

    const isAuthenticated = computed(() => !!token.value)
    const role = computed(() => user.value?.role?.name)

    function setAuth(newToken, newUser) {
        token.value = newToken
        user.value = newUser
        localStorage.setItem('token', newToken)
        localStorage.setItem('user', JSON.stringify(newUser))
    }

    function clearAuth() {
        token.value = null
        user.value = null
        localStorage.removeItem('token')
        localStorage.removeItem('user')
    }

    async function login(email, password) {
        try {
            const response = await fetch(`${import.meta.env.VITE_API_URL || 'http://localhost:8000/api'}/auth/login`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ email, password })
            })

            const data = await response.json()

            if (response.ok) {
                setAuth(data.access_token, data.user)
                return true
            } else {
                throw new Error(data.message || 'Login failed')
            }
        } catch (error) {
            console.error(error)
            return false
        }
    }

    async function fetchUser() {
        try {
            const response = await fetch(`${import.meta.env.VITE_API_URL || 'http://localhost:8000/api'}/auth/me`, {
                headers: {
                    'Authorization': `Bearer ${token.value}`,
                    'Accept': 'application/json'
                }
            })
            if (response.ok) {
                const userData = await response.json()
                // Update user state without changing token
                user.value = userData
                localStorage.setItem('user', JSON.stringify(userData))
                return userData
            }
        } catch (error) {
            console.error('Failed to fetch user:', error)
        }
    }

    return { token, user, isAuthenticated, role, login, clearAuth, fetchUser }
})
