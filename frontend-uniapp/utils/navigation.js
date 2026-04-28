export function getUniApi() {
  if (typeof uni !== 'undefined') return uni
  if (typeof window !== 'undefined' && window.uni) return window.uni
  return null
}

function toHash(url) {
  if (!url) return '#/pages/common/article'
  if (url.indexOf('#/') === 0) return url
  if (url.charAt(0) === '/') return '#' + url
  return '#/' + url.replace(/^\/?/, '')
}

export function openPage(url, method = 'navigateTo') {
  const uniApi = getUniApi()
  if (uniApi && typeof uniApi[method] === 'function') {
    uniApi[method]({ url })
    return
  }

  if (typeof window !== 'undefined') {
    const hash = toHash(url)
    if (method === 'redirectTo' || method === 'reLaunch' || method === 'switchTab') {
      const base = window.location.pathname + window.location.search
      window.history.replaceState(null, '', base + hash)
      window.dispatchEvent(new HashChangeEvent('hashchange'))
      return
    }
    window.location.hash = hash
  }
}

export function goBack(fallback = '/pages/common/article') {
  const uniApi = getUniApi()
  if (uniApi && typeof uniApi.navigateBack === 'function') {
    uniApi.navigateBack({ delta: 1 })
    return
  }

  if (typeof window !== 'undefined' && window.history.length > 1) {
    window.history.back()
    return
  }

  openPage(fallback, 'reLaunch')
}
