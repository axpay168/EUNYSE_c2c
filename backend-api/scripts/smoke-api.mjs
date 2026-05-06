#!/usr/bin/env node
/**
 * 輕量 API 煙霧：連線 + 種子帳號登入 + /api/user/me。
 * 用法：在 backend-api 目錄啟動 PHP 後執行
 *   node scripts/smoke-api.mjs
 * 環境變數：API_BASE（預設 http://127.0.0.1:8000）、EURFOREX_SMOKE_ACCOUNT、EURFOREX_SMOKE_PASSWORD
 */
const base = (process.env.API_BASE || 'http://127.0.0.1:8000').replace(/\/$/, '')
const account = process.env.EURFOREX_SMOKE_ACCOUNT || ''
const password = process.env.EURFOREX_SMOKE_PASSWORD || ''

if (!account || !password) {
  console.error('[smoke-api] EURFOREX_SMOKE_ACCOUNT and EURFOREX_SMOKE_PASSWORD are required.')
  process.exit(2)
}

async function jsonFetch(url, opts = {}) {
  const res = await fetch(url, {
    ...opts,
    headers: {
      'Content-Type': 'application/json',
      ...(opts.headers || {})
    }
  })
  const text = await res.text()
  let body
  try {
    body = JSON.parse(text)
  } catch {
    body = { raw: text }
  }
  return { res, body }
}

async function main() {
  const loginUrl = `${base}/api/user/login`
  const { res, body } = await jsonFetch(loginUrl, {
    method: 'POST',
    body: JSON.stringify({ account, password, lang: 'zh-Hant' })
  })

  if (!res.ok) {
    console.error('[smoke-api] login HTTP', res.status, body)
    process.exit(1)
  }
  if (Number(body.code) !== 1) {
    console.error('[smoke-api] login failed payload', body)
    process.exit(1)
  }
  const token = body.data && body.data.userinfo && body.data.userinfo.token
  if (!token) {
    console.error('[smoke-api] missing token in response', body)
    process.exit(1)
  }

  const meUrl = `${base}/api/user/me`
  const me = await jsonFetch(meUrl, {
    headers: { Authorization: `Bearer ${token}` }
  })
  if (!me.res.ok || Number(me.body.code) !== 1) {
    console.error('[smoke-api] me failed', me.res.status, me.body)
    process.exit(1)
  }

  const user = me.body.data && me.body.data.user
  console.log('[smoke-api] ok', { account, userId: user && user.id, username: user && user.username })
}

main().catch(err => {
  console.error('[smoke-api] unreachable or error:', err.message)
  process.exit(1)
})
