import { test, expect } from '@playwright/test'
import { h5Url } from './helpers'

const API_BASE = (process.env.EURNYSE_API_BASE || 'http://127.0.0.1:8000').replace(/\/$/, '')
const INVITE = (process.env.EURNYSE_E2E_INVITE || 'ADM001').toUpperCase()

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

test.describe('註冊起 E2E + 後端登入／me', () => {
  test('註冊成功後可登入且 me 有使用者', async ({ page, request }) => {
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

    await expect(page).toHaveURL(/#\/pages\/common\/login/, { timeout: 15_000 })

    const loginRes = await request.post(`${API_BASE}/api/user/login`, {
      data: { account: email, password, lang: 'zh-Hant' }
    })
    expect(loginRes.ok()).toBeTruthy()
    const loginJson = await loginRes.json()
    expect(Number(loginJson.code)).toBe(1)
    const token = loginJson.data?.userinfo?.token as string | undefined
    expect(token).toBeTruthy()

    const meRes = await request.get(`${API_BASE}/api/user/me`, {
      headers: { Authorization: `Bearer ${token}` }
    })
    expect(meRes.ok()).toBeTruthy()
    const meJson = await meRes.json()
    expect(Number(meJson.code)).toBe(1)
    expect(meJson.data?.user?.email).toBe(email.toLowerCase())
  })
})
