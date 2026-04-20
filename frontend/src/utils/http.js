import { config } from '@/config'

function isAbsoluteUrl(value = '') {
  return /^https?:\/\//i.test(value)
}

export function resolveApiUrl(path = '') {
  const apiBaseUrl = config.api.baseUrl || '/api'
  if (isAbsoluteUrl(path)) {
    return path
  }

  const normalizedPath = path.startsWith('/') ? path : `/${path}`
  const resolvedApiBase = new URL(apiBaseUrl, window.location.origin)
  const normalizedOrigin = resolvedApiBase.hostname === 'localhost' && window.location.hostname === '127.0.0.1'
    ? `${resolvedApiBase.protocol}//127.0.0.1${resolvedApiBase.port ? `:${resolvedApiBase.port}` : ''}`
    : resolvedApiBase.origin

  return new URL(`${resolvedApiBase.pathname.replace(/\/+$/, '')}${normalizedPath}`, normalizedOrigin).toString()
}

export function getCookieValue(name) {
  const cookie = document.cookie
    .split('; ')
    .find(row => row.startsWith(`${name}=`))

  if (!cookie) return null

  const [, value] = cookie.split('=')
  return value ?? null
}

export function getXsrfTokenFromCookie() {
  const token = getCookieValue('XSRF-TOKEN')
  return token ? decodeURIComponent(token) : null
}

export async function apiFetch(path, options = {}) {
  const url = resolveApiUrl(path)
  const xsrfToken = getXsrfTokenFromCookie()
  const headers = new Headers(options.headers || {})

  if (!headers.has('Accept')) {
    headers.set('Accept', 'application/json')
  }

  if (xsrfToken && !headers.has('X-XSRF-TOKEN')) {
    headers.set('X-XSRF-TOKEN', xsrfToken)
  }

  return fetch(url, {
    ...options,
    headers,
    credentials: options.credentials || 'include',
  })
}
