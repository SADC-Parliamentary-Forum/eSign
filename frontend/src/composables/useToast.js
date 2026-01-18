import { reactive } from 'vue'

const state = reactive({
    show: false,
    message: '',
    color: 'success',
    timeout: 3000
})

export function useToast() {
    const showToast = (message, options = {}) => {
        state.message = message
        state.color = options.color || 'success'
        state.timeout = options.timeout || 3000
        state.show = true
    }

    const success = (msg, options = {}) => showToast(msg, { ...options, color: 'success' })
    const error = (msg, options = {}) => showToast(msg, { ...options, color: 'error' })
    const warning = (msg, options = {}) => showToast(msg, { ...options, color: 'warning' })
    const info = (msg, options = {}) => showToast(msg, { ...options, color: 'info' })

    return {
        state,
        success,
        error,
        warning,
        info
    }
}
