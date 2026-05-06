# 測試/預備環境冒煙測試報告

日期：2026-04-27

## 結論

- 完整前台 + 後台 + API + 餘額帳本冒煙測試已通過。
- 已修復先前缺口：KYC 前台提交/補傳/狀態展示、錢包真實餘額展示、C2C 買入 EUR 凍結/退回/成交入帳、C2C 賣出頁真實下單、交易/充值/提現詳情頁真實資料。
- `eurforex_c2c` 資料庫已改用 `DB_USER=eurforex_c2c`，並新增 `backend-api/database/eurforex_c2c` 作為資料庫資產資料夾，避免與 `new_c2c` 混用。
- 新增 Playwright 規格：`frontend-uniapp/e2e/staging-full-smoke.spec.ts`。
- 後續補強：後台寫入 smoke 已擴充首頁內容、橫幅、換匯提現設定、交易動態、理財產品；另新增 staging 測試資料清理腳本。

## 本次覆蓋

- 前台註冊：空欄位提示、密碼不一致提示、錯誤邀請碼提示、有效邀請碼註冊成功、登入成功、`/api/user/overview` 使用者資料一致。
- 後台入口：登入後逐一切換 `users`、`kyc`、`deposits`、`withdrawals`、`orders`、`listings`，確認主要視圖可見。
- KYC：前台資料缺漏提示、前台提交待審、後台駁回、前台/API 可看到駁回原因、前台補傳重送、後台通過、前台顯示已認證、`tier_profile.is_verified` 同步為已驗證。
- 充值：提交待審、後台駁回後狀態同步、再次提交並通過後 `cash_usdt.available_balance` 增加，錢包頁同步真實餘額，後台帳本可查。
- 提現：提交時 `cash_usdt.available_balance` 扣減且 `reserved_balance` 增加；後台駁回後餘額退回；再次提交並通過後狀態為 `approved` 且資金扣除同步。
- C2C 賣出：後台建立測試掛單；前台交易大廳可見；前台賣出頁讀取掛單與 USDT 餘額；下單後 USDT 凍結；後台取消後 USDT 退回；再次下單後後台放行，USDT 扣除、EUR 入帳、訂單狀態與詳情頁為 `completed`。
- C2C 買入：前台買入頁讀取掛單與 EUR 餘額；提交後 EUR 凍結；後台取消後 EUR 退回；再次買入後後台放行，EUR 扣除、USDT 入帳、訂單狀態為 `completed`。
- 詳情頁：`orderDetail`、`rechargeDetail`、`withdrawDetail`、`withdrawDetailFiat` 已改讀真實使用者 API，避免展示原型資料。
- 後台寫入 smoke：覆蓋首頁公告更新/還原、首頁 banner 新增/更新/刪除、EUR 換 USDT 提現設定更新/還原、交易動態新增/更新/刪除、理財產品新增/更新。
- 清理腳本：`backend-api/scripts/cleanup-staging-smoke.php` 可按測試帳號、測試掛單與 smoke 理財產品前綴清理 staging smoke 資料。
- 資金紀錄與帳本：`/api/user/fund-records` 有紀錄，`/api/admin/users/{id}/wallets/ledger` 有帳本資料。

## 執行結果

- `SMOKE_ADMIN_ACCOUNT=<staging-admin> SMOKE_ADMIN_PASSWORD=<staging-password> npm test`：通過。
- `EURFOREX_E2E_INVITE=<valid-invite-code> npx playwright test register-and-api.spec.ts --reporter=list`：通過。
- `EURFOREX_E2E_INVITE=<valid-invite-code> EURFOREX_ADMIN_ACCOUNT=<staging-admin> EURFOREX_ADMIN_PASSWORD=<staging-password> npx playwright test staging-full-smoke.spec.ts --reporter=list`：通過。
- `EURFOREX_E2E_INVITE=<valid-invite-code> EURFOREX_ADMIN_ACCOUNT=<staging-admin> EURFOREX_ADMIN_PASSWORD=<staging-password> npx playwright test staging-full-smoke.spec.ts register-and-api.spec.ts admin-console.spec.ts --reporter=list`：6 passed。
- `npm run build:h5`：通過，僅保留既有大型靜態資產警告。
- `php -l scripts/smoke.php && php -l scripts/cleanup-staging-smoke.php && php -l public/index.php`：通過。

## 發現與注意事項

- 後端 smoke 不再內建管理員測試帳密，需由環境變數提供 staging 管理員帳密。
- `register-and-api.spec.ts` 需由環境變數提供有效邀請碼。
- C2C 完整測試仍會把測試用戶提升到可跑多筆賣單的等級，否則會被每日賣出限制擋住；這是業務限制，不是錯誤。
- 專案內已不再以 `new_c2c` 作為後端 DB_USER；本機已建立 `eurforex_c2c` MySQL 使用者並授權 `eurforex_c2c` 資料庫。

## 重跑方式

```bash
cd "/home/openclaw/桌面/Project /EURFOREX_C2C/frontend-uniapp"
EURFOREX_E2E_INVITE=<valid-invite-code> \
EURFOREX_ADMIN_ACCOUNT=<staging-admin> \
EURFOREX_ADMIN_PASSWORD=<staging-password> \
npx playwright test staging-full-smoke.spec.ts --reporter=list
```

若要改成外部 staging，請額外帶入：

```bash
EURFOREX_API_BASE=https://api.example.test \
EURFOREX_FRONTEND_BASE=https://h5.example.test \
EURFOREX_ADMIN_BASE=https://admin.example.test
```

清理 staging smoke 測試資料：

```bash
cd "/home/openclaw/桌面/Project /EURFOREX_C2C/backend-api"
SMOKE_EMAIL_LIKE='e2e.smoke-%@test.local' \
SMOKE_LISTING_LIKE='smoke-%' \
SMOKE_FINANCIAL_PRODUCT_LIKE='smoke_%' \
npm run smoke:cleanup:staging
```
