import { resolvePreviewScriptUrl } from './previewsAsset'

/**
 * 動態注入 static/previews 下的腳本（與舊 H5 預覽共用 back-nav、marble-bg 等）。
 * 路徑請傳寫在程式裡的「自站根寫法」/static/previews/...，會經 resolvePreviewScriptUrl 對齊 /h5/ 部署。
 */
export function loadSharedScript(src, marker) {
  if (typeof document === 'undefined') return
  if (marker && document.querySelector('script[data-' + marker + ']')) return
  var s = document.createElement('script')
  s.src = resolvePreviewScriptUrl(src)
  if (marker) s.setAttribute('data-' + marker, '1')
  s.async = false
  document.head.appendChild(s)
}
