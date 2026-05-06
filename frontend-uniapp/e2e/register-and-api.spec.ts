import { test, expect } from '@playwright/test'
import { h5Url } from './helpers'

const API_BASE = (process.env.EURFOREX_API_BASE || 'http://127.0.0.1:8000').replace(/\/$/, '')
const INVITE = (process.env.EURFOREX_E2E_INVITE || 'ADM001').toUpperCase()

async function apiLoginReachable(request: import('@playwright/test').APIRequestContext): Promise<boolean> {
  try {
    const res = await request.post(`${API_BASE}/api/user/login`, {
      data: { account: '__e2e_probe__', password: 'x', lang: 'zh-Hant' },
      failOnStatusCode: false
    })
    return res.status() >= 200 && res.status() < 500
  } catch {
    return false
  }
}

test.describe('註冊起 E2E + 自動登入／me', () => {
  test('註冊成功後自動登入、刷新保持登入且登出後受保護', async ({ page, request }) => {
    const ok = await apiLoginReachable(request)
    test.skip(!ok, `後端不可連 ${API_BASE}，略過（啟動 API 後重跑）`)

    const email = `e2e.${Date.now()}@test.local`
    const password = 'E2eTest!99'

    await page.goto(h5Url('#/pages/common/register'))
    await page.waitForSelector('#registerForm')
    await page.locator('#email').fill(email)
    await page.locator('#password').fill(password)
    await page.locator('#confirmPassword').fill(password)
    await page.locator('#inviteCode').fill(INVITE)
    await page.locator('#agreement').check()
    await page.locator('#registerForm').evaluate(form => (form as HTMLFormElement).requestSubmit())

    await page.waitForSelector('#postRegisterModal.open', { timeout: 30_000 })
    await page.locator('#bindLaterBtn').click()

    await expect(page).toHaveURL(/#\/pages\/index\/index/, { timeout: 15_000 })
    const browserSession = await page.evaluate(() => ({
      token: window.localStorage.getItem('eurforex_user_token') || '',
      profile: window.localStorage.getItem('eurforex_user_profile') || ''
    }))
    expect(browserSession.token).toBeTruthy()
    expect(browserSession.profile).toBeTruthy()

    const profile = JSON.parse(browserSession.profile) as { email?: string }
    expect(profile.email).toBe(email.toLowerCase())

    await page.reload()
    await expect(page).toHaveURL(/#\/pages\/index\/index/)

    const meRes = await request.get(`${API_BASE}/api/user/me`, {
      headers: { Authorization: `Bearer ${browserSession.token}` }
    })
    expect(meRes.ok()).toBeTruthy()
    const meJson = await meRes.json()
    expect(Number(meJson.code)).toBe(1)
    expect(meJson.data?.user?.email).toBe(email.toLowerCase())

    await page.goto(h5Url('#/pages/setting/user'))
    await page.locator('#signOutButton').click()
    await expect(page).toHaveURL(/#\/pages\/common\/login/)
    await page.goto(h5Url('#/pages/index/index'))
    await expect(page).toHaveURL(/#\/pages\/common\/login/)
  })
})
