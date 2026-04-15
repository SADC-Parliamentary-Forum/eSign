/**
 * Production-safe logger utility for the eSign frontend.
 *
 * - In PRODUCTION builds (`import.meta.env.PROD === true`): all methods are no-ops.
 *   Nothing is exposed to DevTools.
 * - In DEVELOPMENT: colour-coded, prefixed console output.
 *
 * Future APM integration point: replace `_report()` with a Sentry/Datadog call.
 */

const IS_PROD = import.meta.env.PROD === true

const STYLES = {
    debug: 'color: #9E9E9E; font-weight: bold',
    info: 'color: #2196F3; font-weight: bold',
    warn: 'color: #FF9800; font-weight: bold',
    error: 'color: #F44336; font-weight: bold',
}

const PREFIX = '[eSign]'

/**
 * Internal: forward errors to an APM service.
 * Currently a no-op; swap this body for `Sentry.captureException(error, { extra: context })` etc.
 */
function _report(error, context = {}) {
    if (window.Sentry) {
        window.Sentry.captureException(error, { extra: context })
    } else if (import.meta.env.VITE_SENTRY_DSN || window.__APP_CONFIG__?.sentryDsn) {
        // Fallback if Sentry is available via import but not window
        import('@sentry/vue').then(Sentry => {
            Sentry.captureException(error, { extra: context })
        }).catch(() => { })
    }
}

function _format(level, message) {
    return [`%c${PREFIX} [${level.toUpperCase()}] ${message}`, STYLES[level]]
}

const logger = IS_PROD
    ? {
        debug: () => { },
        log: () => { },
        info: () => { },
        warn: () => { },
        error: () => { },
        captureError: _report,
    }
    : {
        debug(message, context) {
            context !== undefined
                ? console.debug(..._format('debug', message), context)
                : console.debug(..._format('debug', message))
        },
        log(message, context) {
            context !== undefined
                ? console.log(..._format('info', message), context)
                : console.log(..._format('info', message))
        },
        info(message, context) {
            context !== undefined
                ? console.info(..._format('info', message), context)
                : console.info(..._format('info', message))
        },
        warn(message, context) {
            context !== undefined
                ? console.warn(..._format('warn', message), context)
                : console.warn(..._format('warn', message))
        },
        error(message, context) {
            context !== undefined
                ? console.error(..._format('error', message), context)
                : console.error(..._format('error', message))
        },
        captureError(error, context = {}) {
            console.error(..._format('error', error?.message || String(error)), context)
            _report(error, context)
        },
    }

export { logger }
