/**
 * 靜態預覽腳本路徑：manifest h5 router.base 為 h5/，實際資源在 /h5/static/...。
 * 使用 /static/previews/... 會變成向網站根目錄要檔，部署在子路徑時 404（如 back-nav.js）。
 */

function getPreviewsBase() {
  if (typeof document === 'undefined' || !document.getElementsByTagName) {
    return '/h5/static/previews'
  }
  var scripts = document.getElementsByTagName('script')
  var j, s, ix
  for (j = 0; j < scripts.length; j++) {
    s = scripts[j].src
    if (!s) continue
    ix = s.indexOf('/static/js/')
    if (ix !== -1) {
      return s.slice(0, ix) + '/static/previews'
    }
  }
  if (window.location && window.location.pathname && window.location.pathname.indexOf('/h5') !== -1) {
    return window.location.origin.replace(/\/$/, '') + '/h5/static/previews'
  }
  return '/h5/static/previews'
}

/**
 * @param {string} src 例如 "/static/previews/shared/back-nav.js" 或 "/h5/static/previews/..."
 * @returns {string} 與當前頁面載入的 chunk 同前綴的完整 URL
 */
export function resolvePreviewScriptUrl(src) {
  if (src == null || src === '') return src
  if (/^https?:\/\//i.test(src)) return src
  if (src.indexOf('/h5/static/previews/') === 0) return src
  var rel = src
  if (rel.indexOf('/static/previews/') === 0) {
    rel = rel.replace(/^\/static\/previews\//, '')
  } else if (rel.indexOf('static/previews/') === 0) {
    rel = rel.replace(/^static\/previews\//, '')
  }
  rel = rel.replace(/^\/+/, '')
  var base = getPreviewsBase()
  if (base.charAt(base.length - 1) === '/') return base + rel
  return base + '/' + rel
}
