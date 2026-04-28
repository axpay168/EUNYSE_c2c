import { test, expect } from '@playwright/test'
import { h5Url, sessionInitScriptSource } from './helpers'

test.describe('使用者前台多語切換', () => {
  test.beforeEach(async ({ page }) => {
    await page.addInitScript(sessionInitScriptSource())
  })

  test('登入、註冊與個人中心可切換語言並保存 localStorage.lang', async ({ page }) => {
    await page.goto(h5Url('#/pages/common/login'))
    await expect(page.locator('#loginLanguageButton')).toBeVisible()
    await page.locator('#loginLanguageButton').click()
    await page.locator('[data-lang-option="eng"]').click()
    await expect(page.locator('body')).toContainText('Sign In')
    await expect(page.locator('html')).toHaveAttribute('lang', 'en')
    await expect.poll(() => page.evaluate(() => localStorage.getItem('lang'))).toBe('eng')

    await page.goto(h5Url('#/pages/common/register'))
    await expect(page.locator('#registerLanguageButton')).toBeVisible()
    await page.locator('#registerLanguageButton').click()
    await page.locator('[data-lang-option="zh-Hans"]').click()
    await expect(page.locator('body')).toContainText('创建账户')
    await expect(page.locator('html')).toHaveAttribute('lang', 'zh-Hans')

    await page.goto(h5Url('#/pages/setting/user'))
    await expect(page.locator('#userLanguageButton')).toBeVisible()
    await page.locator('#userLanguageButton').click()
    await page.locator('[data-lang-option="jp"]').click()
    await expect(page.locator('body')).toContainText('マイページ')
    await expect(page.locator('body')).toContainText('言語')
    await expect(page.locator('html')).toHaveAttribute('lang', 'ja')
    await expect.poll(() => page.evaluate(() => localStorage.getItem('lang'))).toBe('jp')
  })
})
