import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import { createTestingPinia } from '@pinia/testing'
import Login from '@/pages/login.vue'
import { useAuthStore } from '@/stores/auth'
import { createVuetify } from 'vuetify'
import * as components from 'vuetify/components'
import * as directives from 'vuetify/directives'

// Mock vue-router
const pushMock = vi.fn()
vi.mock('vue-router', () => ({
    useRouter: () => ({
        push: pushMock
    }),
    useRoute: () => ({
        query: {}
    })
}))

// Mock definePage macro
global.definePage = vi.fn()

describe('Login.vue', () => {
    let wrapper
    let authStore
    const vuetify = createVuetify({
        components,
        directives,
    })

    beforeEach(() => {
        wrapper = mount(Login, {
            global: {
                plugins: [
                    createTestingPinia({
                        createSpy: vi.fn,
                    }),
                    vuetify
                ],
                stubs: {
                    RouterLink: true
                }
            }
        })
        authStore = useAuthStore()
    })

    it('renders login form', () => {
        expect(wrapper.text()).toContain('Welcome to SADC PF eSign!')
        expect(wrapper.find('input[type="email"]').exists()).toBe(true)
        expect(wrapper.find('input[type="password"]').exists()).toBe(true)
    })

    it('handles successful login', async () => {
        // Mock login success
        authStore.login.mockResolvedValue(true)

        // Fill form
        await wrapper.find('input[type="email"]').setValue('test@example.com')
        await wrapper.find('input[type="password"]').setValue('password')

        // Submit
        await wrapper.find('form').trigger('submit.prevent')

        // Assert
        expect(authStore.login).toHaveBeenCalledWith('test@example.com', 'password')
        // Flush promises to allow async push to be called
        await new Promise(resolve => setTimeout(resolve, 0))
        expect(pushMock).toHaveBeenCalledWith('/')
    })

    it('handles failed login', async () => {
        // Mock login failure
        authStore.login.mockResolvedValue(false)

        // Fill form
        await wrapper.find('input[type="email"]').setValue('test@example.com')
        await wrapper.find('input[type="password"]').setValue('wrongpassword')

        // Submit
        await wrapper.find('form').trigger('submit.prevent')

        // Assert
        expect(authStore.login).toHaveBeenCalled()

        // Wait for UI update
        await wrapper.vm.$nextTick()

        expect(wrapper.text()).toContain('Invalid email or password')
        expect(pushMock).not.toHaveBeenCalled()
    })
})
