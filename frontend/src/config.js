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
    const normalizeLocalhostUrl = (value) => {
        if (typeof value !== 'string' || !value) return value;
        // Force IPv4 loopback for local dev to avoid localhost/IPv6 resets.
        return value.replace('://localhost', '://127.0.0.1');
    };

    return {
        api: {
            baseUrl: normalizeLocalhostUrl(runtimeConfig.apiBaseUrl || import.meta.env.VITE_API_URL || '/api'),
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
