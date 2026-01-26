/**
 * Runtime Configuration Loader
 *
 * This module handles loading configuration from:
 * 1. Runtime window object (injected via config.json or environment)
 * 2. Build-time environment variables (import.meta.env)
 * 3. Default fallbacks
 */

const getRuntimeConfig = () => {
    // Check for runtime config injected via window
    const runtimeConfig = window.__APP_CONFIG__ || {};

    return {
        api: {
            baseUrl: runtimeConfig.apiBaseUrl || import.meta.env.VITE_API_URL || '/api',
            timeout: runtimeConfig.apiTimeout || 30000,
        },
        features: {
            ...runtimeConfig.features,
        },
        ui: {
            ...runtimeConfig.ui,
        },
        botProtection: {
            enabled: runtimeConfig.botProtection?.enabled ?? true,
            siteKey: runtimeConfig.botProtection?.siteKey || import.meta.env.VITE_RECAPTCHA_SITE_KEY || '',
        },
    };
};

export const config = getRuntimeConfig();
