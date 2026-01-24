import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import { createTestingPinia } from '@pinia/testing'
import ResetPassword from '@/pages/reset-password.vue'
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
        query: {
            email: 'test@example.com',
            token: 'valid-token'
        }
    }),
    RouterLink: { template: '<a><slot /></a>' }
}))

// Mock definePage macro
global.definePage = vi.fn()

describe('ResetPassword.vue', () => {
    let wrapper
    let authStore
    const vuetify = createVuetify({
        components,
        directives,
    })

    beforeEach(() => {
        vi.clearAllMocks()
        wrapper = mount(ResetPassword, {
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

    it('renders reset password form with prefilled email', () => {
        expect(wrapper.text()).toContain('Reset Password')
        const emailInput = wrapper.find('input[type="email"]')
        expect(emailInput.element.value).toBe('test@example.com')
        expect(emailInput.attributes('readonly')).toBeDefined()
        expect(wrapper.findAll('input[type="password"]').length).toBe(2)
    })

    it('handles password mismatch', async () => {
        const passwords = wrapper.findAll('input[type="password"]')
        await passwords[0].setValue('newpassword123')
        await passwords[1].setValue('mismatch')

        await wrapper.find('form').trigger('submit.prevent')

        await wrapper.vm.$nextTick()
        expect(wrapper.text()).toContain('Passwords do not match')
        expect(authStore.resetPassword).not.toHaveBeenCalled()
    })

    it('handles success flow', async () => {
        authStore.resetPassword.mockResolvedValue(true)
        vi.useFakeTimers()

        const passwords = wrapper.findAll('input[type="password"]')
        await passwords[0].setValue('newpassword123')
        await passwords[1].setValue('newpassword123')

        await wrapper.find('form').trigger('submit.prevent')

        expect(authStore.resetPassword).toHaveBeenCalledWith({
            token: 'valid-token',
            email: 'test@example.com',
            password: 'newpassword123',
            password_confirmation: 'newpassword123'
        })

        await wrapper.vm.$nextTick()
        expect(wrapper.text()).toContain('Password has been reset successfully!')

        vi.runAllTimers()
        await wrapper.vm.$nextTick() // Wait for timer callback
        expect(pushMock).toHaveBeenCalledWith('/login')

        vi.useRealTimers()
    })
})
