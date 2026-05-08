# EURFOREX_C2C 專案說明

這份 `README.md` 給下一位 Agent 或工程師快速接手：先看專案是什麼、用什麼技術、部署在哪裡、產品功能有哪些、最近更新改了什麼。安裝與寶塔部署細節請看 `安装说明.md`。

## 專案定位

`EURFOREX_C2C` 是一套 C2C 交易與資金管理系統，包含：

- 使用者前台 H5：註冊、登入、錢包、充值、提款、C2C 買賣、訂單、理財、KYC、邀請與客服入口。
- 後端 API：使用者 API、後台管理 API、資料表初始化、錢包資金流、審核與訂單流程。
- 營運後台：管理員登入、使用者管理、錢包調整、充值審核、提款審核、訂單中心、商戶掛單、理財配置、KYC、系統配置與操作紀錄。

## 技術與語言

- 前台：`uni-app`、`Vue 2.6`、JavaScript、H5 build。
- 後台：原生 HTML/CSS/JavaScript 靜態站，透過 `fetch` 呼叫後端 API。
- 後端：PHP 8+ 單入口 API，主要入口 `backend-api/public/index.php`。
- 資料庫：正式環境使用 `MySQL` 或 `MariaDB`。
- 部署：寶塔面板 / Nginx / PHP-FPM / 靜態 H5。
- 建置：Node.js 18 或 20，前台使用 `frontend-uniapp/scripts/build-h5.mjs`。

## 開發與正式環境

本機常用入口：

```text
API: http://127.0.0.1:8000
前台: http://127.0.0.1:8094/h5/
後台: http://127.0.0.1:8095/
```

正式環境建議三站點：

```text
前台 H5: https://c2c.example.com/h5/#/pages/common/login
API:     https://api.example.com
後台:    https://admin.example.com/
```

寶塔/伺服器常用路徑：

```text
/www/wwwroot/EURFOREX_c2c
```

正式站點根目錄：

```text
前台: frontend-uniapp/dist/build
API:  backend-api/public
後台: admin-web
```

## 重要配置

上線、換域名、換 API 時一定核對：

1. `backend-api/.env`
2. `frontend-uniapp/runtime-config.js`
3. `admin-web/runtime-config.js`

注意：前台 API 位址由 `frontend-uniapp/runtime-config.js` 控制，不是 `.env.production.local`。只要前台程式或 API 位址改了，都要重新打包 H5。

## 常用命令

前台建置：

```bash
cd /www/wwwroot/EURFOREX_c2c/frontend-uniapp
npm ci
npm run build:h5
```

如果系統 `npm` 壞掉但 `node_modules` 已存在，可直接跑：

```bash
cd /www/wwwroot/EURFOREX_c2c/frontend-uniapp
node scripts/build-h5.mjs
```

後端語法檢查：

```bash
cd /www/wwwroot/EURFOREX_c2c
php -l backend-api/public/index.php
```

後台 JS 檢查：

```bash
node --check admin-web/shared/admin-api-fetch.js
node --check admin-web/shared/admin-api-client.js
node --check admin-web/shared/admin-view-loaders.js
node --check admin-web/shared/admin-modals.js
```

## 產品功能

前台 H5：

- 註冊、登入、重設密碼、多語系切換。
- 首頁行情、交易大廳、買入 USDT、出售 USDT。
- 訂單列表與訂單詳情。
- 錢包資產、資金紀錄、充值詳情、提款詳情。
- USDT 充值、EUR 法幣入金、USDT 提現、EUR 法幣提現。
- 提款地址管理、銀行資料綁定。
- KYC 實名驗證、邀請團隊、理財產品與持倉。
- 客服中心、公告與教學頁。

後台管理：

- 管理員登入與權限模組。
- 使用者管理、邀請管理、使用者來源與分組。
- 錢包資金調整、帳本查看。
- 充值審核、充值地址管理。
- 提款審核、提款設定。
- C2C 訂單中心、訂單狀態處理、證據/補充資料。
- 商戶掛單管理與成交播報。
- 理財產品與理財訂單管理。
- KYC 審核、收款方式審核。
- 首頁內容、系統配置、操作日誌。

後端 API：

- 使用者認證、Token session、驗證碼。
- 自動建表與資料欄位補齊。
- 錢包餘額與資金流水。
- 充值、提款、C2C 訂單、理財、KYC、上傳。
- 後台權限、審核與營運管理 API。

## 時間與時區約定

- 後端寫入時間使用 UTC ISO。
- 使用者前台 API 請求會自動帶 `X-Timezone`，來源是使用者設備的 `Intl.DateTimeFormat().resolvedOptions().timeZone`。
- 後台 API 請求也會自動帶 `X-Timezone`，來源是登入後台管理員自己的設備時區。
- 使用者端與後台的充值、提款、C2C 訂單等時間會依請求者設備時區本地化顯示，並保留部分 `*_utc` 原始欄位供追查。

## 重要維護規則

- 不要只改源碼就認為線上已更新；前台 H5 要重新執行 `npm run build:h5` 或 `node scripts/build-h5.mjs`。
- 不要把 `backend-api` 整個目錄暴露給 Web，只能把 API 站點指向 `backend-api/public`。
- 不要把 `.env`、資料庫密碼、寶塔面板密碼提交到 Git。
- 不要刪除 `frontend-uniapp/pages.json` 或 `RootShell.vue` 引用的頁面檔，否則 H5 build 會失敗。
- 若發現檔案顯示 `D`，先用 `git status --short` 確認是否為工作區誤刪，再決定是否 `git restore`。
- 修改 `backend-api/public/index.php` 後至少跑 `php -l backend-api/public/index.php`。

## 最近更新紀錄

每次 Agent 改完專案，都要在這裡新增一條，讓下一位 Agent 不用猜上下文。

### 2026-05-08

- 註冊成功倒數彈窗補上語言規則：繁中/簡中顯示中文，其餘所有語言統一顯示英文 `Registration successful / Logging in`。已重新打包 H5。
- 調整註冊成功流程：移除註冊後的生物識別啟用詢問彈窗，改為畫面中央顯示「註冊成功 / 正在登入中」倒數 2、1 秒後自動進入前台。已重新打包 H5。
- 修復後台對部分新用戶調帳出現 `Wallet not found`：原因是用戶預設初始化函式缺失，導致 `EN601269` 等新用戶沒有 `cash_usdt`、`eur`、`eur_reserved`、`cny` 錢包與等級資料。已補回 `initialize_user_defaults()`、啟動時補齊缺失資料，並將當前缺資料用戶全部補齊。
- 同步美化銀行綁定頁：已綁定銀行卡片改為清楚分列顯示「戶名 / 銀行名 / 銀行帳號」，新增銀行彈窗表單也改為卡片化輸入區塊。已重新打包 H5。
- 美化法幣提現的「選擇收款銀行」彈窗：單筆銀行資料改為獨立卡片框線，明確顯示「戶名 / 銀行名 / 銀行帳號」，並同步美化已選擇銀行卡片。已重新打包 H5。
- 銀行綁定改為免審核：使用者新增銀行收款方式後後端直接寫入 `approved`，前台銀行綁定頁移除「狀態：審核中」顯示，既有 pending 銀行資料會自動轉為 approved。後台「收款方詳情」卡片新增可編輯欄位，管理員保存後會更新同一筆 `user_payout_methods`，使用者端重新讀取即同步。
- 修復前台賣出交易無法下單：線上 API 驗證錯誤碼為 `AUTH_TIER_DAILY_SELL_LIMIT_REACHED`，原因是等級 1 每日賣單限制把已取消訂單也計入。後端改為只計算非 `cancelled` 賣單，並補上前端多語錯誤文案。已用測試帳號建立訂單成功後立即取消驗證，並重新打包 H5。
- 修復全站時間顯示：前台使用者 API 與後台管理 API 都會帶 `X-Timezone`，後端依登入者/使用者設備時區回傳訂單、充值、提款等時間。
- 涉及檔案：`frontend-uniapp/utils/api.js`、`admin-web/shared/admin-api-fetch.js`、`admin-web/shared/admin-api-client.js`、`backend-api/public/index.php`。
- 恢復一批曾在工作區顯示刪除的前台必要檔案：`main.js`、`myTask.vue`、`financialHoldings.vue`、`orderDetail.vue`、`fundRecord.vue`、`rechargeDetail.vue`、`withdrawDetailFiat.vue`、`utils/datetime.js`。全站掃描確認不是換目錄，是工作區刪除狀態；終端紀錄沒有找到明確 `rm` 線索。
- 重新打包 H5 成功，輸出在 `frontend-uniapp/dist/build`。
- 重整文件：將安裝部署內容集中到 `安装说明.md`，`README.md` 改為專案總覽與更新紀錄。

## 相關文件

- `安装说明.md`：完整安裝、寶塔部署、更新、驗活與安全檢查。
- `docs/首次上线文档.md`：首次上線核對表。
- `frontend-uniapp/SECURITY-AUDIT.md`：前台 production npm audit 策略。
- `admin-web/BUTTON-AUDIT.md`：後台按鈕行為盤點。
- `LOADING-SCENE-SPEC.md`：Loading 場景配置規範。
