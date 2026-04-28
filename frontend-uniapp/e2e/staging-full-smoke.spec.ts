import { test, expect, type APIRequestContext, type Page } from '@playwright/test'

declare const process: { env: Record<string, string | undefined> }

type ApiPayload<T = Record<string, unknown>> = {
  code?: number | string
  msg?: string
  error_code?: string
  data?: T
}

type Wallet = {
  wallet_code: string
  available_balance: string
  reserved_balance: string
}

const API_BASE = (process.env.EURNYSE_API_BASE || 'http://127.0.0.1:8000').replace(/\/$/, '')
const H5_BASE = (process.env.EURNYSE_FRONTEND_BASE || 'http://127.0.0.1:8094').replace(/\/$/, '')
const ADMIN_BASE = (process.env.EURNYSE_ADMIN_BASE || 'http://127.0.0.1:8095').replace(/\/$/, '')
const INVITE = (process.env.EURNYSE_E2E_INVITE || '847392').toUpperCase()
const ADMIN_ACCOUNT = process.env.EURNYSE_ADMIN_ACCOUNT
const ADMIN_PASSWORD = process.env.EURNYSE_ADMIN_PASSWORD
const TEST_RUN = `smoke-${Date.now()}`
const TEST_EMAIL = `e2e.${TEST_RUN}@test.local`
const TEST_PASSWORD = 'E2eSmoke!99'
const PROOF_URL = `https://example.test/${TEST_RUN}/proof.png`

function h5Url(hash: string): string {
  return `${H5_BASE}/h5/index.html${hash.startsWith('#') ? hash : `#${hash}`}`
}

function adminUrl(hash = 'dashboard', debugApi = false): string {
  const query = debugApi ? '?debugApi=1' : ''
  return `${ADMIN_BASE}/index.html${query}#${hash}`
}

function asNumber(value: string | number | undefined): number {
  return Number(value || 0)
}

async function api<T = Record<string, unknown>>(
  request: APIRequestContext,
  method: 'GET' | 'POST' | 'PATCH' | 'DELETE',
  path: string,
  options: { token?: string, data?: Record<string, unknown>, allowFailure?: boolean } = {}
): Promise<ApiPayload<T>> {
  const headers: Record<string, string> = {}
  if (options.token) headers.Authorization = `Bearer ${options.token}`
  const res = await request.fetch(`${API_BASE}${path}`, {
    method,
    headers,
    data: options.data,
    failOnStatusCode: false
  })
  const payload = await res.json().catch(() => ({})) as ApiPayload<T>
  if (!options.allowFailure && (!res.ok() || Number(payload.code) !== 1)) {
    throw new Error(`${method} ${path} failed (${res.status()}): ${payload.error_code || payload.msg || 'unknown error'}`)
  }
  return payload
}

async function loginUser(request: APIRequestContext, account: string, password: string): Promise<string> {
  const payload = await api<{ userinfo?: { token?: string } }>(request, 'POST', '/api/user/login', {
    data: { account, password, lang: 'zh-Hant' }
  })
  const token = payload.data?.userinfo?.token
  expect(token, 'user login should return token').toBeTruthy()
  return token as string
}

async function loginAdmin(request: APIRequestContext): Promise<string> {
  expect(ADMIN_ACCOUNT, 'EURNYSE_ADMIN_ACCOUNT is required for admin smoke tests').toBeTruthy()
  expect(ADMIN_PASSWORD, 'EURNYSE_ADMIN_PASSWORD is required for admin smoke tests').toBeTruthy()
  const payload = await api<{ token?: string, admin?: { password_must_change?: boolean } }>(request, 'POST', '/api/admin/auth/login', {
    data: { account: ADMIN_ACCOUNT as string, password: ADMIN_PASSWORD as string }
  })
  expect(payload.data?.admin?.password_must_change, 'admin account must not be forced to change password for write smoke tests').not.toBeTruthy()
  const token = payload.data?.token
  expect(token, 'admin login should return token').toBeTruthy()
  return token as string
}

async function loginAdminInBrowser(page: Page): Promise<void> {
  await page.goto(adminUrl('dashboard'))
  await expect(page.locator('#admin-login-screen')).toBeVisible()
  await page.locator('#admin-login-account').fill(ADMIN_ACCOUNT)
  await page.locator('#admin-login-password').fill(ADMIN_PASSWORD)
  await page.locator('#admin-login-form').evaluate(form => (form as HTMLFormElement).requestSubmit())
  await expect(page.locator('#admin-app')).toBeVisible()
}

async function loadUserSession(page: Page, token: string, email: string): Promise<void> {
  await page.goto(h5Url('#/pages/index/index'))
  await page.evaluate(([savedToken, savedEmail]) => {
    window.localStorage.setItem('eurnyse_user_token', String(savedToken))
    window.localStorage.setItem('eurnyse_user_profile', JSON.stringify({ email: savedEmail }))
  }, [token, email])
}

async function overview(request: APIRequestContext, token: string): Promise<{ user: { id?: number, user_id?: number, email?: string }, wallets: Wallet[], tier_profile?: { is_verified?: boolean } }> {
  const payload = await api<{ user?: { id?: number, user_id?: number, email?: string }, wallets?: Wallet[], tier_profile?: { is_verified?: boolean } }>(request, 'GET', '/api/user/overview', { token })
  return {
    user: payload.data?.user || {},
    wallets: payload.data?.wallets || [],
    tier_profile: payload.data?.tier_profile
  }
}

function wallet(items: Wallet[], code: string): Wallet {
  const hit = items.find(item => item.wallet_code === code)
  expect(hit, `${code} wallet should exist`).toBeTruthy()
  return hit as Wallet
}

async function adjustWallet(request: APIRequestContext, adminToken: string, userId: number, walletCode: string, operation: string, amount: string): Promise<void> {
  await api(request, 'PATCH', `/api/admin/users/${userId}/wallets/${walletCode}`, {
    token: adminToken,
    data: { operation, amount, reason: `${TEST_RUN} staging smoke funding` }
  })
}

test.describe('staging 真實互動冒煙測試', () => {
  test('註冊、KYC、充值、提現、C2C 賣單與後台審批資料同步', async ({ page, request }) => {
    await test.step('前台註冊表單錯誤提示與註冊成功', async () => {
      await page.goto(h5Url('#/pages/common/register'))
      await page.waitForSelector('#registerForm')

      await page.locator('#registerForm').evaluate(form => (form as HTMLFormElement).requestSubmit())
      await expect(page.locator('#errorBox')).toContainText('請輸入郵箱')

      await page.locator('#email').fill(TEST_EMAIL)
      await page.locator('#password').fill(TEST_PASSWORD)
      await page.locator('#confirmPassword').fill(`${TEST_PASSWORD}x`)
      await page.locator('#registerForm').evaluate(form => (form as HTMLFormElement).requestSubmit())
      await expect(page.locator('#errorBox')).toContainText('兩次輸入的密碼不一致')

      await page.locator('#confirmPassword').fill(TEST_PASSWORD)
      await page.locator('#inviteCode').fill('BADCODE')
      await page.locator('#agreement').check()
      await page.locator('#registerForm').evaluate(form => (form as HTMLFormElement).requestSubmit())
      await expect(page.locator('#errorBox')).toContainText('邀請碼錯誤或不存在')

      await page.locator('#inviteCode').fill(INVITE)
      await page.locator('#registerForm').evaluate(form => (form as HTMLFormElement).requestSubmit())
      await expect(page.locator('#postRegisterModal')).toHaveClass(/open/)
      await page.locator('#bindLaterBtn').click()
      await expect(page).toHaveURL(/#\/pages\/common\/login/)
    })

    await test.step('前台登入與後端使用者資料驗證', async () => {
      await page.locator('#tabAccount').click()
      await page.locator('#account').fill(TEST_EMAIL)
      await page.locator('#password').fill(TEST_PASSWORD)
      await page.locator('#loginForm').evaluate(form => (form as HTMLFormElement).requestSubmit())
      await expect(page).toHaveURL(/#\/pages\/index\/index/)
    })

    const userToken = await loginUser(request, TEST_EMAIL, TEST_PASSWORD)
    const adminToken = await loginAdmin(request)
    const userOverview = await overview(request, userToken)
    const userId = Number(userOverview.user.id || userOverview.user.user_id)
    expect(userId, 'newly registered user id').toBeGreaterThan(0)
    expect(userOverview.user.email).toBe(TEST_EMAIL.toLowerCase())

    await test.step('後台瀏覽器登入與主要功能入口可見', async () => {
      await page.setViewportSize({ width: 1440, height: 900 })
      await loginAdminInBrowser(page)
      for (const view of ['users', 'kyc', 'deposits', 'withdrawals', 'orders', 'listings']) {
        await page.locator(`[data-admin-view="${view}"]`).scrollIntoViewIfNeeded()
        await page.locator(`[data-admin-view="${view}"]`).click({ force: true })
        await expect(page).toHaveURL(new RegExp(`#${view}$`))
        await expect(page.locator(`#view-${view}`)).toHaveAttribute('aria-hidden', 'false')
      }
    })

    await test.step('KYC 提交、後台駁回、補傳後通過，API 狀態同步', async () => {
      await loadUserSession(page, userToken, TEST_EMAIL)
      await page.setViewportSize({ width: 430, height: 900 })
      await page.goto(h5Url('#/pages/setting/kycVerification'))
      await expect(page.locator('body')).toContainText('身份認證')
      await page.locator('#kycSubmitBtn').click()
      await expect(page.locator('body')).toContainText('請完整填寫')

      await page.locator('#kycLegalName').fill(`Smoke User ${TEST_RUN}`)
      await page.locator('#kycIdNumber').fill('AA123456789')
      await page.locator('#kycFrontUrl').fill(PROOF_URL)
      await page.locator('#kycBackUrl').fill(PROOF_URL)
      await page.locator('#kycSubmitBtn').click()
      await expect(page.locator('body')).toContainText('資料已提交')
      let latest = await api<{ application?: { id?: number, status?: string, review_note?: string } }>(request, 'GET', '/api/user/kyc-applications/latest', { token: userToken })
      const kycId = Number(latest.data?.application?.id)
      expect(latest.data?.application?.status).toBe('pending')

      await api(request, 'PATCH', `/api/admin/kyc-applications/${kycId}`, {
        token: adminToken,
        data: { action: 'reject', reason: `${TEST_RUN} reject once` }
      })
      latest = await api<{ application?: { status?: string, review_note?: string } }>(request, 'GET', '/api/user/kyc-applications/latest', { token: userToken })
      expect(latest.data?.application?.status).toBe('rejected')
      expect(latest.data?.application?.review_note).toContain(TEST_RUN)

      await page.goto(h5Url('#/pages/setting/kycVerification'))
      await expect(page.locator('body')).toContainText(`${TEST_RUN} reject once`)
      await page.locator('#kycLegalName').fill(`Smoke User ${TEST_RUN} Resubmitted`)
      await page.locator('#kycIdNumber').fill('AA123456789')
      await page.locator('#kycFrontUrl').fill(`${PROOF_URL}?front=resubmit`)
      await page.locator('#kycBackUrl').fill(`${PROOF_URL}?back=resubmit`)
      await page.locator('#kycSubmitBtn').click()
      await expect(page.locator('body')).toContainText('資料已提交')
      latest = await api<{ application?: { id?: number, status?: string } }>(request, 'GET', '/api/user/kyc-applications/latest', { token: userToken })
      expect(latest.data?.application?.status).toBe('pending')
      await api(request, 'PATCH', `/api/admin/kyc-applications/${Number(latest.data?.application?.id)}`, {
        token: adminToken,
        data: { action: 'approve', reason: `${TEST_RUN} approved` }
      })
      const verifiedOverview = await overview(request, userToken)
      expect(verifiedOverview.tier_profile?.is_verified).toBeTruthy()
      await api(request, 'PATCH', `/api/admin/users/${userId}/tier-profile`, {
        token: adminToken,
        data: {
          level: 3,
          group_code: 'merchant',
          merchant_enabled: true,
          is_verified: true,
          reason: `${TEST_RUN} allow multiple sell smoke orders`
        }
      })

      await page.goto(h5Url('#/pages/setting/kycVerification'))
      await expect(page.locator('body')).toContainText('已認證')
    })

    await test.step('充值駁回與通過後餘額、帳本同步', async () => {
      const before = wallet((await overview(request, userToken)).wallets, 'cash_usdt')

      const rejectedDeposit = await api<{ request_id?: number }>(request, 'POST', '/api/user/deposit-requests', {
        token: userToken,
        data: { amount: '3.25', asset_code: 'USDT', network: 'TRC20', proof_url: PROOF_URL, reference: `${TEST_RUN} reject deposit` }
      })
      await api(request, 'PATCH', `/api/admin/deposit-requests/${Number(rejectedDeposit.data?.request_id)}`, {
        token: adminToken,
        data: { action: 'reject', reason: `${TEST_RUN} deposit reject` }
      })
      const rejectedDepositDetail = await api<{ request?: { status?: string } }>(request, 'GET', `/api/user/deposit-requests/${Number(rejectedDeposit.data?.request_id)}`, { token: userToken })
      expect(rejectedDepositDetail.data?.request?.status).toBe('rejected')

      const approvedDeposit = await api<{ request_id?: number }>(request, 'POST', '/api/user/deposit-requests', {
        token: userToken,
        data: { amount: '5.50', asset_code: 'USDT', network: 'TRC20', proof_url: `${PROOF_URL}?approved=1`, reference: `${TEST_RUN} approve deposit` }
      })
      await api(request, 'PATCH', `/api/admin/deposit-requests/${Number(approvedDeposit.data?.request_id)}`, {
        token: adminToken,
        data: { action: 'approve', reason: `${TEST_RUN} deposit approve` }
      })
      const after = wallet((await overview(request, userToken)).wallets, 'cash_usdt')
      expect(asNumber(after.available_balance) - asNumber(before.available_balance)).toBeCloseTo(5.5, 6)
      await page.goto(h5Url(`#/pages/setting/rechargeDetail?request_id=${Number(approvedDeposit.data?.request_id)}`))
      await expect(page.locator('body')).toContainText('已完成')
      await page.goto(h5Url('#/pages/setting/wallet'))
      await expect(page.locator('#wallet-usdt-available')).toContainText(Number(after.available_balance).toLocaleString('en-GB', { minimumFractionDigits: 2, maximumFractionDigits: 2 }))
    })

    await test.step('提現提交凍結、駁回退回、通過扣款同步', async () => {
      await adjustWallet(request, adminToken, userId, 'cash_usdt', 'increase_available', '10')
      const beforeReject = wallet((await overview(request, userToken)).wallets, 'cash_usdt')
      const rejectedWithdrawal = await api<{ request_id?: number }>(request, 'POST', '/api/user/withdrawal-requests', {
        token: userToken,
        data: {
          amount: '2.00',
          channel_type: 'usdt',
          asset_code: 'USDT',
          source_wallet_code: 'cash_usdt',
          payout_address: `T${TEST_RUN.replace(/[^A-Za-z0-9]/g, '').padEnd(33, 'x').slice(0, 33)}`
        }
      })
      let afterSubmit = wallet((await overview(request, userToken)).wallets, 'cash_usdt')
      expect(asNumber(beforeReject.available_balance) - asNumber(afterSubmit.available_balance)).toBeCloseTo(2, 6)
      expect(asNumber(afterSubmit.reserved_balance) - asNumber(beforeReject.reserved_balance)).toBeCloseTo(2, 6)
      await api(request, 'PATCH', `/api/admin/withdrawal-requests/${Number(rejectedWithdrawal.data?.request_id)}`, {
        token: adminToken,
        data: { action: 'reject', reason: `${TEST_RUN} withdrawal reject` }
      })
      const afterReject = wallet((await overview(request, userToken)).wallets, 'cash_usdt')
      expect(asNumber(afterReject.available_balance)).toBeCloseTo(asNumber(beforeReject.available_balance), 6)
      expect(asNumber(afterReject.reserved_balance)).toBeCloseTo(asNumber(beforeReject.reserved_balance), 6)

      const approvedWithdrawal = await api<{ request_id?: number }>(request, 'POST', '/api/user/withdrawal-requests', {
        token: userToken,
        data: {
          amount: '1.25',
          channel_type: 'usdt',
          asset_code: 'USDT',
          source_wallet_code: 'cash_usdt',
          payout_address: `T${TEST_RUN.replace(/[^A-Za-z0-9]/g, '').padEnd(33, 'y').slice(0, 33)}`
        }
      })
      afterSubmit = wallet((await overview(request, userToken)).wallets, 'cash_usdt')
      await api(request, 'PATCH', `/api/admin/withdrawal-requests/${Number(approvedWithdrawal.data?.request_id)}`, {
        token: adminToken,
        data: { action: 'approve', reason: `${TEST_RUN} withdrawal approve` }
      })
      const afterApprove = wallet((await overview(request, userToken)).wallets, 'cash_usdt')
      expect(asNumber(afterApprove.available_balance)).toBeCloseTo(asNumber(afterReject.available_balance) - 1.25, 6)
      expect(asNumber(afterApprove.reserved_balance)).toBeCloseTo(asNumber(afterReject.reserved_balance), 6)
      const approvedDetail = await api<{ request?: { status?: string } }>(request, 'GET', `/api/user/withdrawal-requests/${Number(approvedWithdrawal.data?.request_id)}`, { token: userToken })
      expect(approvedDetail.data?.request?.status).toBe('approved')
      await page.goto(h5Url(`#/pages/setting/withdrawDetail?request_id=${Number(approvedWithdrawal.data?.request_id)}`))
      await expect(page.locator('body')).toContainText('已完成')
    })

    await test.step('C2C 賣出下單凍結 USDT，取消退回，放行後入 EUR', async () => {
      await adjustWallet(request, adminToken, userId, 'cash_usdt', 'increase_available', '8')
      const sellListing = await api<{ listing_id?: number }>(request, 'POST', '/api/admin/listings', {
        token: adminToken,
        data: {
          owner_user_id: 0,
          nickname: `${TEST_RUN} buyer`,
          side: 'sell',
          price: '1.20',
          min_amount: '1.00',
          max_amount: '4.00',
          available_amount: '4.00',
          payment_method_summary: 'SEPA',
          completion_rate: '100%',
          status: 'active'
        }
      })
      const listingId = Number(sellListing.data?.listing_id)

      await page.goto(h5Url('#/pages/index/hall'))
      await expect(page.locator(`#lobby-offers-sell article:has-text("#${listingId}")`)).toBeVisible()

      const beforeCancel = wallet((await overview(request, userToken)).wallets, 'cash_usdt')
      await page.goto(h5Url(`#/pages/index/sell?listing_id=${listingId}`))
      await expect(page.locator('#sell-usdt-balance')).toBeVisible()
      await page.locator('#sell-amount-input').fill('1.00')
      await page.locator('#sell-submit-btn').click()
      await expect(page).toHaveURL(/orderDetail\?id=\d+/)
      const cancelOrderId = Number(new URLSearchParams((page.url().split('?')[1] || '')).get('id'))
      const afterCancelOrderCreate = wallet((await overview(request, userToken)).wallets, 'cash_usdt')
      expect(asNumber(beforeCancel.available_balance) - asNumber(afterCancelOrderCreate.available_balance)).toBeCloseTo(1, 6)
      expect(asNumber(afterCancelOrderCreate.reserved_balance) - asNumber(beforeCancel.reserved_balance)).toBeCloseTo(1, 6)
      await api(request, 'PATCH', `/api/admin/orders/${cancelOrderId}/status`, {
        token: adminToken,
        data: { status: 'cancelled', reason: `${TEST_RUN} cancel order` }
      })
      const afterCancel = wallet((await overview(request, userToken)).wallets, 'cash_usdt')
      expect(asNumber(afterCancel.available_balance)).toBeCloseTo(asNumber(beforeCancel.available_balance), 6)
      expect(asNumber(afterCancel.reserved_balance)).toBeCloseTo(asNumber(beforeCancel.reserved_balance), 6)

      const beforeCompleteOverview = await overview(request, userToken)
      const beforeCompleteUsdt = wallet(beforeCompleteOverview.wallets, 'cash_usdt')
      const beforeCompleteEur = wallet(beforeCompleteOverview.wallets, 'eur')
      await page.goto(h5Url(`#/pages/index/sell?listing_id=${listingId}`))
      await page.locator('#sell-amount-input').fill('1.10')
      await page.locator('#sell-submit-btn').click()
      await expect(page).toHaveURL(/orderDetail\?id=\d+/)
      const completeSellOrderId = Number(new URLSearchParams((page.url().split('?')[1] || '')).get('id'))
      await api(request, 'POST', `/api/admin/orders/${completeSellOrderId}/release`, {
        token: adminToken,
        data: { reason: `${TEST_RUN} release order` }
      })
      const afterCompleteOverview = await overview(request, userToken)
      const afterCompleteUsdt = wallet(afterCompleteOverview.wallets, 'cash_usdt')
      const afterCompleteEur = wallet(afterCompleteOverview.wallets, 'eur')
      expect(asNumber(beforeCompleteUsdt.available_balance) - asNumber(afterCompleteUsdt.available_balance)).toBeCloseTo(1.1, 6)
      expect(asNumber(afterCompleteUsdt.reserved_balance)).toBeCloseTo(asNumber(beforeCompleteUsdt.reserved_balance), 6)
      expect(asNumber(afterCompleteEur.available_balance) - asNumber(beforeCompleteEur.available_balance)).toBeCloseTo(1.32, 6)
      const orderDetail = await api<{ order?: { status?: string } }>(request, 'GET', `/api/user/orders/${completeSellOrderId}`, { token: userToken })
      expect(orderDetail.data?.order?.status).toBe('completed')
      await page.goto(h5Url(`#/pages/setting/orderDetail?id=${completeSellOrderId}`))
      await expect(page.locator('body')).toContainText('已完成')
    })

    await test.step('C2C 買入下單凍結 EUR，取消退回，放行後收到 USDT', async () => {
      await adjustWallet(request, adminToken, userId, 'eur', 'increase_available', '10')
      const buyListing = await api<{ listing_id?: number }>(request, 'POST', '/api/admin/listings', {
        token: adminToken,
        data: {
          owner_user_id: 0,
          nickname: `${TEST_RUN} seller`,
          side: 'buy',
          price: '1.30',
          min_amount: '1.00',
          max_amount: '4.00',
          available_amount: '4.00',
          payment_method_summary: 'SEPA',
          completion_rate: '100%',
          status: 'active'
        }
      })
      const buyListingId = Number(buyListing.data?.listing_id)
      await page.goto(h5Url(`#/pages/index/buy?listing_id=${buyListingId}`))
      await expect(page.locator('#buy-eur-balance')).toBeVisible()

      const beforeCancelOverview = await overview(request, userToken)
      const beforeCancelEur = wallet(beforeCancelOverview.wallets, 'eur')
      await page.locator('#buy-amount-input').fill('1.30')
      await page.locator('#buy-submit-btn').click()
      await expect(page).toHaveURL(/orderDetail\?id=\d+/)
      const cancelOrderId = Number(new URLSearchParams((page.url().split('?')[1] || '')).get('id'))
      const afterBuyCreate = wallet((await overview(request, userToken)).wallets, 'eur')
      expect(asNumber(beforeCancelEur.available_balance) - asNumber(afterBuyCreate.available_balance)).toBeCloseTo(1.3, 6)
      expect(asNumber(afterBuyCreate.reserved_balance) - asNumber(beforeCancelEur.reserved_balance)).toBeCloseTo(1.3, 6)
      await api(request, 'PATCH', `/api/admin/orders/${cancelOrderId}/status`, {
        token: adminToken,
        data: { status: 'cancelled', reason: `${TEST_RUN} cancel buy order` }
      })
      const afterBuyCancel = wallet((await overview(request, userToken)).wallets, 'eur')
      expect(asNumber(afterBuyCancel.available_balance)).toBeCloseTo(asNumber(beforeCancelEur.available_balance), 6)
      expect(asNumber(afterBuyCancel.reserved_balance)).toBeCloseTo(asNumber(beforeCancelEur.reserved_balance), 6)

      const beforeCompleteOverview = await overview(request, userToken)
      const beforeCompleteEur = wallet(beforeCompleteOverview.wallets, 'eur')
      const beforeCompleteUsdt = wallet(beforeCompleteOverview.wallets, 'cash_usdt')
      await page.goto(h5Url(`#/pages/index/buy?listing_id=${buyListingId}`))
      await page.locator('#buy-amount-input').fill('2.60')
      await page.locator('#buy-submit-btn').click()
      await expect(page).toHaveURL(/orderDetail\?id=\d+/)
      const completeBuyOrderId = Number(new URLSearchParams((page.url().split('?')[1] || '')).get('id'))
      await api(request, 'POST', `/api/admin/orders/${completeBuyOrderId}/release`, {
        token: adminToken,
        data: { reason: `${TEST_RUN} release buy order` }
      })
      const afterCompleteOverview = await overview(request, userToken)
      const afterCompleteEur = wallet(afterCompleteOverview.wallets, 'eur')
      const afterCompleteUsdt = wallet(afterCompleteOverview.wallets, 'cash_usdt')
      expect(asNumber(beforeCompleteEur.available_balance) - asNumber(afterCompleteEur.available_balance)).toBeCloseTo(2.6, 6)
      expect(asNumber(afterCompleteEur.reserved_balance)).toBeCloseTo(asNumber(beforeCompleteEur.reserved_balance), 6)
      expect(asNumber(afterCompleteUsdt.available_balance) - asNumber(beforeCompleteUsdt.available_balance)).toBeCloseTo(2, 6)
      const buyOrderDetail = await api<{ order?: { status?: string } }>(request, 'GET', `/api/user/orders/${completeBuyOrderId}`, { token: userToken })
      expect(buyOrderDetail.data?.order?.status).toBe('completed')
    })

    await test.step('資金紀錄與後台帳本可查', async () => {
      const records = await api<{ items?: Array<{ status?: string, type?: string }> }>(request, 'GET', '/api/user/fund-records', { token: userToken })
      expect(records.data?.items?.length || 0).toBeGreaterThan(0)
      const ledger = await api<{ items?: Array<Record<string, unknown>> }>(request, 'GET', `/api/admin/users/${userId}/wallets/ledger?page=1&page_size=20`, { token: adminToken })
      expect(ledger.data?.items?.length || 0).toBeGreaterThan(0)
    })
  })
})
