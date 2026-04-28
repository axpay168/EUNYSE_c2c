import { test, expect } from '@playwright/test'
import {
  loadPageHashes,
  h5Url,
  escapeHashForUrlRegex,
  sessionInitScriptSource,
  expectedHashAfterNavigation,
  hasMainTabbar
} from './helpers'

test.describe('H5：已登入狀態下全 pages.json 路由', () => {
  test.beforeEach(async ({ context }) => {
    await context.addInitScript(sessionInitScriptSource())
  })

  for (const hash of loadPageHashes()) {
    test(`hash 可載入 ${hash}`, async ({ page }) => {
      await page.goto(h5Url(hash))
      await page.waitForLoadState('domcontentloaded')
      const pathOnly = hash.split('?')[0]
      if (pathOnly === '#/pages/common/login' || pathOnly === '#/pages/common/register' || pathOnly === '#/pages/common/resetpwd') {
        await expect(page).toHaveURL(new RegExp(escapeHashForUrlRegex(pathOnly)))
        return
      }
      await page.waitForFunction(() => document.querySelector('.eurnyse-shell'))
      const expectHash = expectedHashAfterNavigation(hash)
      await expect(page).toHaveURL(new RegExp(escapeHashForUrlRegex(expectHash)))
      await expect(page.locator('.eurnyse-shell')).toBeVisible()
      if (hasMainTabbar(expectHash)) {
        await expect(page.locator('.eurnyse-shell-tabbar')).toBeVisible()
      }
    })
  }
})

test.describe('H5：底欄四入口點擊與 URL', () => {
  test.beforeEach(async ({ context }) => {
    await context.addInitScript(sessionInitScriptSource())
  })

  test('主頁 → 大廳 → 訂單 → 個人 → 主頁', async ({ page }) => {
    await page.goto(h5Url('#/pages/index/index'))
    await page.waitForSelector('.eurnyse-shell-tabbar')

    await page.getByRole('navigation', { name: '主頁底部導航' }).getByRole('link', { name: '交易大廳' }).click()
    await expect(page).toHaveURL(/#\/pages\/index\/hall/)

    await page.getByRole('navigation', { name: '主頁底部導航' }).getByRole('link', { name: '訂單' }).click()
    await expect(page).toHaveURL(/#\/pages\/setting\/myTask/)

    await page.getByRole('navigation', { name: '主頁底部導航' }).getByRole('link', { name: '個人中心' }).click()
    await expect(page).toHaveURL(/#\/pages\/setting\/user/)

    await page.getByRole('navigation', { name: '主頁底部導航' }).getByRole('link', { name: '主頁' }).click()
    await expect(page).toHaveURL(/#\/pages\/index\/index/)
  })
})
