import fs from 'node:fs'
import path from 'node:path'

/** Playwright 以 `frontend-uniapp` 為 cwd 執行時可解析 pages.json */
export function loadPageHashes(): string[] {
  const pagesPath = path.join(process.cwd(), 'pages.json')
  const raw = JSON.parse(fs.readFileSync(pagesPath, 'utf8')) as { pages: Array<{ path: string }> }
  return (raw.pages || []).map(p => {
    const rel = p.path.replace(/^pages\//, '')
    const base = `#/pages/${rel}`
    if (rel.includes('orderDetail')) return `${base}?id=C2C-240318`
    return base
  })
}

/** baseURL 預設為 origin（8094），實際入口為 /h5/index.html#... */
export function h5Url(hash: string): string {
  const h = hash.startsWith('#') ? hash : `#${hash}`
  return `/h5/index.html${h}`
}

export function escapeHashForUrlRegex(hash: string): string {
  const [pathPart, query] = hash.split('?')
  const esc = pathPart.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')
  if (!query) return esc
  return `${esc}\\?${query.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')}`
}

export function sessionInitScriptSource(): string {
  return `
    localStorage.setItem('eurforex_user_token', 'playwright-route-token');
    localStorage.setItem('eurforex_user_profile', JSON.stringify({
      display_code: 'ENPWTEST',
      email: 'playwright@test.local'
    }));
  `
}

/** 部分頁面 mounted 內會立即 location.href 轉址，斷言應以「最終 hash」為準 */
export function expectedHashAfterNavigation(hash: string): string {
  const pathOnly = hash.split('?')[0]
  if (pathOnly === '#/pages/setting/recharge') return '#/pages/setting/mixrecharge'
  if (pathOnly === '#/pages/setting/verificationCenter') return '#/pages/setting/securityCenter'
  return hash
}

/** 與 RootShell `MAIN_NAV_ROUTES` 一致：僅這些 path 顯示底欄 */
export function hasMainTabbar(pathWithOptionalQuery: string): boolean {
  const pathOnly = pathWithOptionalQuery.split('?')[0]
  const set = new Set([
    '#/pages/index/index',
    '#/pages/index/hall',
    '#/pages/index/more',
    '#/pages/setting/myTask',
    '#/pages/setting/user'
  ])
  return set.has(pathOnly)
}
