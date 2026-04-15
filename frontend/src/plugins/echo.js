import Echo from 'laravel-echo'
import Pusher from 'pusher-js'

window.Pusher = Pusher

// Export a function that takes the app instance (matching template's plugin loader)
export default function (app) {
  const isTLS = window.location.protocol === 'https:'
  const port = window.location.port
    ? Number(window.location.port)
    : (isTLS ? 443 : 80)

  const echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: window.location.hostname,
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
