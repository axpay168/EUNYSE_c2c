import { test, expect } from '@playwright/test'

const ADMIN_ACCOUNT = process.env.EURNYSE_ADMIN_ACCOUNT
const ADMIN_PASSWORD = process.env.EURNYSE_ADMIN_PASSWORD
const adminTest = ADMIN_ACCOUNT && ADMIN_PASSWORD ? test : test.skip

/**
 * 手動回歸（超管、有效 API token）：
 * - 以超管登入，進入 #admin-users，確認列表與查詢/重置與後端一致。
 * - 切換數個側欄模組，確認表格由 API 載入且無「驗收原型」全域 toast。
 *
 * 需要環境變數：
 * - EURNYSE_ADMIN_ACCOUNT
 * - EURNYSE_ADMIN_PASSWORD
 */

test.use({
  baseURL: 'http://127.0.0.1:8095',
  viewport: { width: 1440, height: 900 }
})

test.describe('admin-web：登入與 hash', () => {
  adminTest('本地密碼登入後切換使用者與儀表板', async ({ page }) => {
    await page.goto('/index.html')
    await expect(page.locator('#admin-login-screen')).toBeVisible()
    await page.locator('#admin-login-account').fill(ADMIN_ACCOUNT as string)
    await page.locator('#admin-login-password').fill(ADMIN_PASSWORD as string)
    await page.locator('#admin-login-form').evaluate(f => (f as HTMLFormElement).requestSubmit())
    await expect(page.locator('body')).toHaveClass(/admin-auth-ready/)
    await expect(page.locator('#admin-app')).toBeVisible()
    await expect(page.locator('#admin-user-chip-label')).not.toHaveText('未登入')
    await expect(page.locator('#admin-user-chip-avatar')).not.toHaveText('—')

    await page.locator('[data-admin-view="users"]').scrollIntoViewIfNeeded()
    await page.locator('[data-admin-view="users"]').click({ force: true })
    await expect(page).toHaveURL(/#users/)
    await expect(page.locator('#view-users')).toHaveAttribute('aria-hidden', 'false')

    await page.locator('[data-admin-view="dashboard"]').scrollIntoViewIfNeeded()
    await page.locator('[data-admin-view="dashboard"]').click({ force: true })
    await expect(page).toHaveURL(/#dashboard/)
    await expect(page.locator('#view-dashboard')).toHaveAttribute('aria-hidden', 'false')

    await page.locator('#admin-logout-btn').click()
    await expect(page.locator('#admin-login-screen')).toBeVisible()
    await expect(page.locator('body')).not.toHaveClass(/admin-auth-ready/)
  })

  adminTest('登入後遍歷側欄模組視圖可切換且主視圖可見', async ({ page }) => {
    const moduleHashes = [
      'dashboard',
      'users',
      'wallets',
      'tiers',
      'deposits',
      'deposit-addresses',
      'withdrawals',
      'eur-swap-withdraw-config',
      'orders',
      'financial-products',
      'financial-orders',
      'listings',
      'trade-feed',
      'home-content',
      'kyc',
      'payout-methods',
      'auth-events',
      'system-config',
      'admin-users'
    ] as const

    await page.goto('/index.html')
    await page.locator('#admin-login-account').fill(ADMIN_ACCOUNT as string)
    await page.locator('#admin-login-password').fill(ADMIN_PASSWORD as string)
    await page.locator('#admin-login-form').evaluate(f => (f as HTMLFormElement).requestSubmit())
    await expect(page.locator('#admin-app')).toBeVisible()

    for (const h of moduleHashes) {
      const viewId = '#view-' + h
      const navBtn = page.locator(`button.admin-nav-item[data-admin-view="${h}"]`).first()
      await navBtn.scrollIntoViewIfNeeded()
      await navBtn.click({ force: true })
      await expect(page).toHaveURL(new RegExp(`#${h.replace(/-/g, '\\-')}$`))
      await expect(page.locator(viewId)).toHaveAttribute('aria-hidden', 'false')
    }
  })

  adminTest('預設關閉全域「驗收原型」攔截：儀表板匯出不應出現該文案', async ({ page }) => {
    await page.goto('/index.html')
    await page.locator('#admin-login-account').fill(ADMIN_ACCOUNT as string)
    await page.locator('#admin-login-password').fill(ADMIN_PASSWORD as string)
    await page.locator('#admin-login-form').evaluate(f => (f as HTMLFormElement).requestSubmit())
    await expect(page.locator('#admin-app')).toBeVisible()

    await page.locator('[data-admin-view="dashboard"]').scrollIntoViewIfNeeded()
    await page.locator('[data-admin-view="dashboard"]').click({ force: true })
    const exportBtn = page.locator('#dash-chart-export')
    await exportBtn.click()
    await expect(page.locator('body')).not.toContainText('驗收原型')
  })

  adminTest('後台管理員：管理員模版分頁每列有編輯與刪除；內建刪除為 disabled', async ({ page }) => {
    await page.goto('/index.html')
    await page.locator('#admin-login-account').fill(ADMIN_ACCOUNT as string)
    await page.locator('#admin-login-password').fill(ADMIN_PASSWORD as string)
    await page.locator('#admin-login-form').evaluate(f => (f as HTMLFormElement).requestSubmit())
    await expect(page.locator('#admin-app')).toBeVisible()

    await page.locator('[data-admin-view="admin-users"]').scrollIntoViewIfNeeded()
    await page.locator('[data-admin-view="admin-users"]').click({ force: true })
    await expect(page.locator('#view-admin-users')).toHaveAttribute('aria-hidden', 'false')
    await expect(page.locator('#view-admin-users-tbody tr').first()).not.toContainText('載入中')

    await page.locator('#admin-au-tab-templates').click()
    await expect(page.locator('#admin-au-panel-templates')).toBeVisible()
    const tbody = page.locator('#view-admin-role-templates-tbody')
    await expect(tbody.locator('tr').first()).not.toContainText('尚無模版')

    const financeRow = tbody.locator('tr').filter({ hasText: /^finance_ops/ })
    await expect(financeRow.locator('[data-admin-rt-edit]')).toBeVisible()
    await expect(financeRow.locator('[data-admin-rt-delete]')).toBeVisible()
    await expect(financeRow.locator('[data-admin-rt-delete]')).toBeDisabled()

    await financeRow.locator('[data-admin-rt-edit]').click()
    const modal = page.locator('#admin-modal-create-role-template')
    await expect(modal).toBeVisible()
    await expect(page.locator('#view-admin-rt-submit')).toBeDisabled()
    await expect(page.locator('#view-admin-rt-submit')).toHaveText('僅供檢視')
    await page.locator('#admin-modal-create-role-template .admin-modal__head [data-admin-modal-close]').click()
    await expect(modal).toBeHidden()
  })
})
