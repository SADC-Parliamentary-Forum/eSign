import { createApp } from 'vue'
import App from '@/App.vue'
import { registerPlugins } from '@core/utils/plugins'

// Styles
import '@core/scss/template/index.scss'
import '@styles/styles.scss'

// Create vue app
const app = createApp(App)


import { config } from '@/config'

// ...

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
