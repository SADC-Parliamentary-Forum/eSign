import Echo from 'laravel-echo'
import Pusher from 'pusher-js'
import { resolveApiUrl } from '@/utils/http'

window.Pusher = Pusher

// Export a function that takes the app instance (matching template's plugin loader)
export default function (app) {
  const scheme = (import.meta.env.VITE_REVERB_SCHEME || 'http').toLowerCase()
  const isTLS = scheme === 'https'
  const host = import.meta.env.VITE_REVERB_HOST || window.location.hostname || 'localhost'
  const configuredPort = Number(import.meta.env.VITE_REVERB_PORT)
  const port = Number.isFinite(configuredPort) && configuredPort > 0
    ? configuredPort
    : (isTLS ? 443 : 8080)

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
  })

  app.config.globalProperties.$echo = echo
  app.provide('echo', echo)

  // Also attach to window for easy access if needed
  window.Echo = echo
}
