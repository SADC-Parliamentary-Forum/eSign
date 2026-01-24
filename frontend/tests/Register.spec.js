import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import { createTestingPinia } from '@pinia/testing'
import Register from '@/pages/register.vue'
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

describe('Register.vue', () => {
    let wrapper
    let authStore
    const vuetify = createVuetify({
        components,
        directives,
    })

    beforeEach(() => {
        vi.clearAllMocks()
        wrapper = mount(Register, {
            global: {
                plugins: [
                    createTestingPinia({
                        createSpy: vi.fn,
                    }),
                    vuetify
                ],
                stubs: {
                    RouterLink: { template: '<a><slot /></a>' }
                }
            }
        })
        authStore = useAuthStore()
    })

    it('renders register form', () => {
        expect(wrapper.text()).toContain('Create an Account')
        expect(wrapper.find('input[placeholder="John Doe"]').exists()).toBe(true) // Name
        expect(wrapper.find('input[type="email"]').exists()).toBe(true)
        expect(wrapper.findAll('input[type="password"]').length).toBe(2) // Password and Confirm
    })

    it('handles password mismatch', async () => {
        await wrapper.find('input[type="email"]').setValue('test@example.com')
        const passwords = wrapper.findAll('input[type="password"]')
        await passwords[0].setValue('password123')
        await passwords[1].setValue('password456') // Mismatch

        await wrapper.find('form').trigger('submit.prevent')

        await wrapper.vm.$nextTick()
        expect(wrapper.text()).toContain('Passwords do not match')
        expect(authStore.register).not.toHaveBeenCalled()
    })

    it('handles successful registration', async () => {
        authStore.register.mockResolvedValue(true)

        await wrapper.find('input[placeholder="John Doe"]').setValue('John Doe')
        await wrapper.find('input[type="email"]').setValue('test@example.com')
        const passwords = wrapper.findAll('input[type="password"]')
        await passwords[0].setValue('password123')
        await passwords[1].setValue('password123')

        await wrapper.find('form').trigger('submit.prevent')

        expect(authStore.register).toHaveBeenCalledWith({
            name: 'John Doe',
            email: 'test@example.com',
            password: 'password123',
            password_confirmation: 'password123'
        })

        await new Promise(resolve => setTimeout(resolve, 0))
        expect(pushMock).toHaveBeenCalledWith('/')
    })
})
