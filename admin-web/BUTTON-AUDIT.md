# admin-web 按鈕與行為盤點（維護用）

本表描述各視圖主要 `button.admin-btn` 的現況。原型占位與不可用匯出入口已移除；若新增按鈕，必須具備 `id`、`data-*` 行為或明確 disabled。

## 側欄 hash 與列表載入

| hash | 列表 / 摘要載入 | 實作檔 |
|------|-----------------|--------|
| dashboard | `GET /api/admin/dashboard-summary`、`GET /api/admin/auth/events` | `admin-view-loaders.js` |
| users | `GET /api/admin/users`、`PATCH /api/admin/users/batch` | `admin-modals.js` |
| wallets | `GET /api/admin/users/:id/wallets` | `admin-view-loaders.js` |
| tiers | `GET /api/admin/tier-templates`、`GET/PATCH /api/admin/users/:id/tier-profile` | 同上 |
| deposits / deposit-addresses / withdrawals / orders | 對應 `GET/PATCH /api/admin/...` | 同上 |
| eur-swap | `GET/PATCH /api/admin/withdrawal-settings/eur-swap`、`copy`、`history` | 同上 |
| admin-users | `GET /api/admin/admin-users`（超管） | 同上 |

## 依視圖：工具列與列操作（摘要）

| 視圖 | 已接線 / 可點 | 說明 |
|------|---------------|------|
| dashboard | 區間套用、最近操作載入 | 圖表與待辦占位已刪除 |
| users | 查詢、重置、刷新、新增、詳情、餘額、等級、批量鎖定/解禁/風控 | CSV 匯出入口已刪除 |
| tiers | 用戶等級載入/刷新、模板查詢/刷新/編輯 | 子列刷新已有 id |
| deposits / withdrawals / KYC | 查詢、重置、刷新、列審核、批量通過/駁回 | 批量流程逐筆呼叫真實 API |
| eur-swap | 保存提領配置、規則複製、版本紀錄 | 版本紀錄讀審計日誌 |
| orders / financial / listings / trade-feed | 列表、詳情、狀態/返還/上下架等已接線操作 | 幽靈彈窗已刪除 |
| home-content | 公告保存、橫幅/教學列表、新增/編輯/刪除、刷新 | 子列刷新已有 id |
| auth-events / system-config / admin-users | 查詢、詳情、配置編輯、管理員權限與密碼流程 | 不可用匯出入口已刪除 |

## 後台管理員（重點）

- **角色模板**：進入 `#admin-users` 時即填入 fallback；`GET` 成功後以 `role_templates` 覆蓋「新增 / 編輯」下拉。
- **權限／檢視權限**：可開 `#admin-modal-admin-user-permissions`。非超管目標依 `catalog` 與 `module_access` 編輯，儲存為 `PATCH` + `role_template: custom`。邀請管理已納入 catalog。

更新本表時請一併調整 [`frontend-uniapp/e2e/admin-console.spec.ts`](../frontend-uniapp/e2e/admin-console.spec.ts) 若新增煙霧覆蓋。
