import { createApp } from 'vue'
import App from '@/App.vue'
import { registerPlugins } from '@core/utils/plugins'
import * as Sentry from '@sentry/vue'
import { logger } from '@/utils/logger'

// Styles
import '@core/scss/template/index.scss'
import '@styles/styles.scss'
import '@mdi/font/css/materialdesignicons.min.css'
import '@/plugins/pdfjs'

// Create vue app
const app = createApp(App)

import { config } from '@/config'

if (import.meta.env.VITE_SENTRY_DSN || window.__APP_CONFIG__?.sentryDsn) {
    Sentry.init({
        app,
        dsn: import.meta.env.VITE_SENTRY_DSN || window.__APP_CONFIG__?.sentryDsn,
        trackComponents: true,
        environment: import.meta.env.MODE || 'production',
    })
}
// Global Vue error handler — catches errors thrown inside component setup / lifecycle hooks
app.config.errorHandler = (err, instance, info) => {
    logger.captureError(err, { component: instance?.$options?.name, info })
}

// Register plugins
registerPlugins(app)

// Manual reCAPTCHA v3 Injection (Fixes plugin issues)
if (config.botProtection.siteKey) {
    const script = document.createElement('script')
    script.src = `https://www.google.com/recaptcha/api.js?render=${config.botProtection.siteKey}`
    script.async = true
    script.defer = true
    document.head.appendChild(script)
}

// Mount vue app
app.mount('#app')

// Global uncaught JS error handler
window.onerror = (message, source, lineno, colno, error) => {
    logger.captureError(error || new Error(String(message)), { source, lineno, colno })
    return true // prevents default browser error logging
}

// Global unhandled promise rejection handler
window.addEventListener('unhandledrejection', event => {
    logger.captureError(
        event.reason instanceof Error ? event.reason : new Error(String(event.reason)),
        { type: 'unhandledrejection' },
    )
    event.preventDefault()
})

