<script>
import { defineComponent, h } from 'vue'

export default defineComponent({
  name: 'ErrorBoundary',
  props: {
    fallback: {
      type: Function,
      default: null,
    },
  },
  data() {
    return {
      error: null,
      errorInfo: null,
    }
  },
  errorCaptured(err, instance, info) {
    this.error = err
    this.errorInfo = info

    // Log to console in development
    if (import.meta.env.DEV) {
      console.error('ErrorBoundary caught error:', err)
      console.error('Component info:', info)
    }

    // Send to error tracking service (e.g., Sentry)
    if (import.meta.env.PROD) {
      // window.Sentry?.captureException(err, { extra: { componentInfo: info } })
    }

    // Prevent error from propagating
    return false
  },
  methods: {
    resetError() {
      this.error = null
      this.errorInfo = null
    },
  },
  render() {
    if (this.error) {
      // Use custom fallback if provided
      if (this.fallback) {
        return this.fallback({
          error: this.error,
          errorInfo: this.errorInfo,
          resetError: this.resetError,
        })
      }

      // Default error UI
      return h('div', { class: 'error-boundary' }, [
        h('v-card', { class: 'ma-4' }, [
          h('v-card-title', { class: 'd-flex align-center' }, [
            h('v-icon', { icon: 'mdi-alert-circle', color: 'error', class: 'mr-2' }),
            'Something went wrong',
          ]),
          h('v-card-text', [
            h('v-alert', { type: 'error', variant: 'tonal', class: 'mb-4' }, [
              import.meta.env.DEV
                ? this.error.message
                : 'An unexpected error occurred. Please try again.',
            ]),
            import.meta.env.DEV && h('details', { class: 'text-caption' }, [
              h('summary', 'Error Details'),
              h('pre', { class: 'mt-2 pa-2 bg-grey-lighten-4' }, this.error.stack),
            ]),
          ]),
          h('v-card-actions', [
            h('v-spacer'),
            h('v-btn', {
              color: 'primary',
              onClick: () => {
                this.resetError()
                this.$router.push('/')
              },
            }, 'Go to Dashboard'),
            h('v-btn', {
              variant: 'text',
              onClick: this.resetError,
            }, 'Try Again'),
          ]),
        ]),
      ])
    }

    // Render children normally
    return this.$slots.default?.()
  },
})
</script>

<style scoped>
.error-boundary {
  min-height: 400px;
  display: flex;
  align-items: center;
  justify-content: center;
}
</style>
