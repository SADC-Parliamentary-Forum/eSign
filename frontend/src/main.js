import { createApp } from 'vue'
import App from '@/App.vue'
import { registerPlugins } from '@core/utils/plugins'

// Styles
import '@core/scss/template/index.scss'
import '@styles/styles.scss'

// Create vue app
const app = createApp(App)


import { VueRecaptchaV3Plugin } from 'vue-recaptcha-v3'
import { config } from '@/config'

// ...

// Register plugins
registerPlugins(app)

if (config.botProtection.siteKey) {
    app.use(VueRecaptchaV3Plugin, {
        siteKey: config.botProtection.siteKey,
        loaderOptions: {
            autoHideBadge: true
        }
    })
}

// Mount vue app
app.mount('#app')
