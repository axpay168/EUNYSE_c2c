/**
 * CoinGecko 公開 API（無需 API Key，Access-Control-Allow-Origin: *）
 * 用於首頁即時行情：價格、24h 漲跌、成交量、走勢採樣。
 * 註：部分地區對 api.binance.com 可能 451，故採用 CoinGecko。
 */

const CG_BASE = 'https://api.coingecko.com/api/v3'

function downsample(arr, targetLen) {
  if (!arr || arr.length === 0) return []
  if (arr.length <= targetLen) return arr.slice()
  const out = []
  const step = (arr.length - 1) / (targetLen - 1)
  for (var i = 0; i < targetLen; i++) {
    var idx = Math.round(i * step)
    if (idx >= arr.length) idx = arr.length - 1
    out.push(arr[idx])
  }
  return out
}

/**
 * @param {number[]} prices
 * @param {number} w viewBox width
 * @param {number} h viewBox height
 * @param {number} pad inset so stroke stays inside clip
 * @returns {string} SVG path d (M/L polyline)
 */
export function pricesToSparkPathD(prices, w, h, pad) {
  w = w || 100
  h = h || 30
  pad = pad == null ? 4 : pad
  if (!prices || prices.length < 2) return ''
  var min = Math.min.apply(null, prices)
  var max = Math.max.apply(null, prices)
  if (!isFinite(min) || !isFinite(max)) return ''
  if (max === min) {
    min -= Math.abs(min) * 0.001 + 0.01
    max += Math.abs(max) * 0.001 + 0.01
  }
  var innerW = w - 2 * pad
  var innerH = h - 2 * pad
  var n = prices.length
  var parts = []
  for (var i = 0; i < n; i++) {
    var x = pad + (n === 1 ? innerW / 2 : (i / (n - 1)) * innerW)
    var y = pad + innerH * (1 - (prices[i] - min) / (max - min))
    parts.push((i === 0 ? 'M' : 'L') + x.toFixed(2) + ',' + y.toFixed(2))
  }
  return parts.join('')
}

export function formatUsdPrice(price) {
  var p = Number(price)
  if (!isFinite(p)) return '—'
  if (p >= 1000) return p.toLocaleString('en-US', { maximumFractionDigits: 0, minimumFractionDigits: 0 })
  if (p >= 1) return p.toLocaleString('en-US', { maximumFractionDigits: 2, minimumFractionDigits: 2 })
  return p.toLocaleString('en-US', { maximumFractionDigits: 4, minimumFractionDigits: 2 })
}

export function formatVolShort(usdVol) {
  var v = Number(usdVol)
  if (!isFinite(v) || v < 0) return '—'
  if (v >= 1e9) return (v / 1e9).toFixed(2) + 'B'
  if (v >= 1e6) return (v / 1e6).toFixed(1) + 'M'
  if (v >= 1e3) return (v / 1e3).toFixed(1) + 'K'
  return String(Math.round(v))
}

export function formatPctChange(change) {
  var c = Number(change)
  if (!isFinite(c)) return '—'
  var sign = c > 0 ? '+' : ''
  return sign + c.toFixed(2) + '%'
}

/**
 * @param {string[]} coinIds e.g. ['bitcoin','ethereum','ripple']
 */
export function fetchSimpleMulti(coinIds) {
  var url =
    CG_BASE +
    '/simple/price?ids=' +
    encodeURIComponent(coinIds.join(',')) +
    '&vs_currencies=usd&include_24hr_vol=true&include_24hr_change=true'
  return fetch(url).then(function (r) {
    if (!r.ok) throw new Error('coingecko_simple_' + r.status)
    return r.json()
  })
}

/**
 * 近 24h 價格採樣（用於走勢線）
 * @param {string} coinId
 * @param {number} points 採樣點數
 */
export function fetchDaySparkPrices(coinId, points) {
  points = points || 40
  var url =
    CG_BASE +
    '/coins/' +
    encodeURIComponent(coinId) +
    '/market_chart?vs_currency=usd&days=1'
  return fetch(url).then(function (r) {
    if (!r.ok) throw new Error('coingecko_chart_' + r.status)
    return r.json()
  }).then(function (j) {
    var raw = (j.prices || []).map(function (pair) {
      return pair[1]
    })
    return downsample(raw, points)
  })
}
