const DEFAULT_API_BASE = 'http://127.0.0.1:8000'

function resolveDefaultApiBase() {
  if (typeof window === 'undefined') return DEFAULT_API_BASE
  const { hostname = '', port = '', origin = '' } = window.location || {}
  if (/^(localhost|127\.0\.0\.1)$/i.test(hostname) && port !== '8000') return DEFAULT_API_BASE
  if (/^https?:\/\//i.test(origin)) return origin.replace(/\/$/, '')
  return DEFAULT_API_BASE
}

export function getApiBaseUrl() {
  if (typeof window !== 'undefined') {
    const config = window.__EURFOREX_RUNTIME_CONFIG__ || {}
    return String(config.apiBaseUrl || resolveDefaultApiBase()).replace(/\/$/, '')
  }
  return DEFAULT_API_BASE
}
