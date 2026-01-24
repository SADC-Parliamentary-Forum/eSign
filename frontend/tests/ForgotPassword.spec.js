import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import { createTestingPinia } from '@pinia/testing'
import ForgotPassword from '@/pages/forgot-password.vue'
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
    }),
    RouterLink: { template: '<a><slot /></a>' }
}))

// Mock definePage macro
global.definePage = vi.fn()

describe('ForgotPassword.vue', () => {
    let wrapper
    let authStore
    const vuetify = createVuetify({
        components,
        directives,
    })

    beforeEach(() => {
        vi.clearAllMocks()
        wrapper = mount(ForgotPassword, {
            global: {
                plugins: [
                    createTestingPinia({
                        createSpy: vi.fn,
                    }),
                    vuetify
                ],
            }
        })
        authStore = useAuthStore()
    })

    it('renders forgot password form', () => {
        expect(wrapper.text()).toContain('Forgot Password?')
        expect(wrapper.find('input[type="email"]').exists()).toBe(true)
        expect(wrapper.text()).toContain('Send Reset Link')
        expect(wrapper.text()).toContain('Back to login')
    })

    it('handles success flow', async () => {
        authStore.forgotPassword.mockResolvedValue(true)

        await wrapper.find('input[type="email"]').setValue('test@example.com')
        await wrapper.find('form').trigger('submit.prevent')

        expect(authStore.forgotPassword).toHaveBeenCalledWith('test@example.com')

        await wrapper.vm.$nextTick()
        expect(wrapper.text()).toContain('We have emailed your password reset link!')
    })

    it('handles failure flow', async () => {
        authStore.forgotPassword.mockRejectedValue(new Error('User not found'))

        await wrapper.find('input[type="email"]').setValue('unknown@example.com')
        await wrapper.find('form').trigger('submit.prevent')

        expect(authStore.forgotPassword).toHaveBeenCalledWith('unknown@example.com')

        await wrapper.vm.$nextTick()
        expect(wrapper.text()).toContain('User not found')
    })
})
