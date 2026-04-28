import puppeteer from 'puppeteer'

const baseUrl = process.env.EURNYSE_SMOKE_BASE_URL || 'http://127.0.0.1:8097/h5/index.html'

const routes = [
  '#/pages/index/index',
  '#/pages/index/hall',
  '#/pages/setting/myTask',
  '#/pages/setting/user'
]

const viewports = [
  { width: 430, height: 932 },
  { width: 430, height: 700 },
  { width: 430, height: 560 },
  { width: 390, height: 480 }
]

const browser = await puppeteer.launch({
  headless: true,
  args: ['--no-sandbox', '--window-size=430,932']
})

try {
  for (const viewport of viewports) {
    for (const route of routes) {
      const page = await browser.newPage()
      await page.setViewport({ ...viewport, deviceScaleFactor: 2 })
      await page.evaluateOnNewDocument(() => {
        localStorage.setItem('eurnyse_user_token', 'smoke-test-token')
        localStorage.setItem(
          'eurnyse_user_profile',
          JSON.stringify({ display_code: 'ENSMOKE', email: 'smoke@test.local' })
        )
      })

      await page.goto(`${baseUrl}${route}`, { waitUntil: 'networkidle0', timeout: 90000 })
      await page.waitForSelector('.eurnyse-shell-tabbar', { timeout: 30000 })

      const result = await page.evaluate(() => {
      const nav = document.querySelector('.eurnyse-shell-tabbar')
      const oldNavs = document.querySelectorAll('nav.eurnyse-home-bottom-nav:not(.eurnyse-shell-tabbar)')
      if (!nav) return { ok: false, reason: 'missing nav' }
      const rect = nav.getBoundingClientRect()
      const style = window.getComputedStyle(nav)
      const oldNavVisibleCount = Array.from(oldNavs).filter(el => {
        const s = window.getComputedStyle(el)
        return s.display !== 'none' && s.visibility !== 'hidden' && Number(s.opacity) !== 0
      }).length
      return {
        ok:
          rect.bottom > window.innerHeight - 2 &&
          rect.top < window.innerHeight &&
          style.position === 'fixed' &&
          style.display !== 'none' &&
          oldNavVisibleCount === 0,
        rect: { top: rect.top, bottom: rect.bottom, height: rect.height, width: rect.width },
        position: style.position,
        display: style.display,
        zIndex: style.zIndex,
        oldNavVisibleCount,
        text: nav.innerText
      }
      })

      await page.close()

      if (!result.ok) {
        console.error(`[main-nav-smoke] failed on ${route} @ ${viewport.width}x${viewport.height}`)
        console.error(JSON.stringify(result, null, 2))
        process.exit(1)
      }

      console.log(`[main-nav-smoke] ok ${route} @ ${viewport.width}x${viewport.height}`, JSON.stringify(result.rect))
    }
  }
} finally {
  await browser.close()
}
