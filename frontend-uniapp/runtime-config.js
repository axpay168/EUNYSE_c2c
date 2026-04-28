;(function (window) {
  var preset = window.__EURNYSE_RUNTIME_CONFIG__ || {}

  function resolveDefaultApiBase() {
    var location = window.location || {}
    var host = String(location.hostname || '')
    var port = String(location.port || '')
    var origin = String(location.origin || '')

    if (/^(localhost|127\.0\.0\.1)$/i.test(host) && port !== '8000') {
      return 'http://127.0.0.1:8000'
    }
    if (/^https?:\/\//i.test(origin)) return origin.replace(/\/$/, '')
    return 'http://127.0.0.1:8000'
  }

  var apiBaseUrl =
    preset.apiBaseUrl != null && String(preset.apiBaseUrl).trim() !== ''
      ? String(preset.apiBaseUrl).trim().replace(/\/$/, '')
      : resolveDefaultApiBase()

  window.__EURNYSE_RUNTIME_CONFIG__ = Object.assign({}, preset, {
    apiBaseUrl: apiBaseUrl,
    envName:
      preset.envName ||
      (/^(localhost|127\.0\.0\.1)$/i.test(String((window.location && window.location.hostname) || ''))
        ? 'local'
        : 'production')
  })
})(window)
