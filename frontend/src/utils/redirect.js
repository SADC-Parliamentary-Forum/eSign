/**
 * Prevent open redirect: allow only same-origin relative paths.
 * Rejects protocol-relative (//evil.com), javascript:, and absolute URLs.
 * @param {string} [url] - Candidate return URL from query
 * @param {string} [fallback] - Default when invalid
 * @returns {string} Safe path for router.push
 */
export function getSafeReturnUrl(url, fallback = '/') {
  if (url == null || typeof url !== 'string') return fallback
  const trimmed = url.trim()
  if (trimmed === '') return fallback
  // Must be a relative path: single leading slash, no protocol
  if (trimmed.startsWith('//') || trimmed.includes('://') || /^\s*javascript:/i.test(trimmed)) {
    return fallback
  }
  if (trimmed.startsWith('/')) return trimmed
  return fallback
}
