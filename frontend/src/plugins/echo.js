import Echo from 'laravel-echo'
import Pusher from 'pusher-js'
import { resolveApiUrl } from '@/utils/http'
import { logger } from '@/utils/logger'

window.Pusher = Pusher

// Export a function that takes the app instance (matching template's plugin loader)
export default function (app) {
  const scheme = (import.meta.env.VITE_REVERB_SCHEME || 'http').toLowerCase()
  const isTLS = scheme === 'https'
  const host = import.meta.env.VITE_REVERB_HOST || window.location.hostname || 'localhost'
  const configuredPort = Number(import.meta.env.VITE_REVERB_PORT)
  const sameHostAsPage = host === window.location.hostname
  const browserOverHttps = window.location.protocol === 'https:'

  // When the app is served over HTTPS on the same host, WebSocket should go through
  // the public reverse proxy port instead of internal container ports like 8080.
  const port = (browserOverHttps && sameHostAsPage)
    ? 443
    : (
        Number.isFinite(configuredPort) && configuredPort > 0
          ? configuredPort
          : (isTLS ? 443 : 8080)
      )

  const echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    authEndpoint: resolveApiUrl('/broadcasting/auth'),
    auth: {
      headers: {
        Authorization: `Bearer ${localStorage.getItem('token') || ''}`,
      },
    },
    wsHost: host,
    wsPort: port,
    wssPort: port,
    forceTLS: isTLS,
    enabledTransports: isTLS ? ['wss'] : ['ws'],
    disableStats: true,
  })

  const pusherConnection = echo?.connector?.pusher?.connection
  if (pusherConnection) {
    pusherConnection.bind('error', error => {
      logger.warn('Realtime socket unavailable. Falling back to polling flows.', {
        code: error?.error?.data?.code || error?.error?.type || 'unknown',
      })
    })
  }

  app.config.globalProperties.$echo = echo
  app.provide('echo', echo)

  // Also attach to window for easy access if needed
  window.Echo = echo
}
