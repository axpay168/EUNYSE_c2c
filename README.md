# EURNYSE_C2C 部署最短路徑

目前專案正式環境只支援：

- `MySQL`
- `MariaDB`

專案結構：

- `frontend-uniapp/`：使用者前台 H5，基於 uni-app / Vue 2。
- `backend-api/`：PHP API，正式環境由 Nginx + PHP-FPM 執行。
- `admin-web/`：營運後台靜態站。

建議正式環境拆成 3 個站點：

1. 前台 H5：`https://c2c.example.com/h5/#/pages/common/login`
2. API：`https://api.example.com`
3. 後台：`https://admin.example.com/`

推薦部署目錄：

```text
/www/wwwroot/eurnyse_c2c
```

## 本機預設入口

```text
API: http://127.0.0.1:8000
前台: http://127.0.0.1:8094/h5/
後台: http://127.0.0.1:8095/
```

## 上線前記住 3 個配置檔

1. `backend-api/.env`
2. `frontend-uniapp/runtime-config.js`
3. `admin-web/runtime-config.js`

注意：本專案前台正式 API 位址不是靠 `frontend-uniapp/.env.production.local`，而是靠 `frontend-uniapp/runtime-config.js`。如果 API 位址改了，前台必須重新執行 `npm run build:h5`。

## 最短 6 步上線口令版

不要把「從 Git 拉下來就能直接開站」當成預設前提。此倉庫交付的是源碼、配置模板與建置腳本，前台 H5 需要在伺服器或 CI 現場建置。

### 首次上線照抄

```bash
cd /www/wwwroot
git clone <你的 Git 倉庫地址> eurnyse_c2c

cd /www/wwwroot/eurnyse_c2c/backend-api
cp .env.example .env

cd /www/wwwroot/eurnyse_c2c/frontend-uniapp
npm ci
npm run build:h5
```

然後只做 6 件事：

1. 建立 MySQL / MariaDB 資料庫。
2. 填好 `backend-api/.env`。
3. 填好 `frontend-uniapp/runtime-config.js`。
4. 檢查 `admin-web/runtime-config.js`。
5. 在 `frontend-uniapp/` 執行 `npm ci && npm run build:h5`。
6. 建好 Nginx / 寶塔三個站點，重啟 `nginx` 與 `php-fpm` 後驗活。

### 以後從 Git 更新照抄

```bash
cd /www/wwwroot/eurnyse_c2c
git pull

cd /www/wwwroot/eurnyse_c2c/frontend-uniapp
npm ci
npm run build:h5

systemctl restart php8.3-fpm 2>/dev/null || systemctl restart php-fpm 2>/dev/null || true
systemctl restart nginx
```

更新時至少確認 4 件事：

- 如果 API 域名改了，重新檢查 `frontend-uniapp/runtime-config.js`，並重新建置 H5。
- 如果前台或後台域名改了，重新檢查 `backend-api/.env` 的 `API_ALLOWED_ORIGINS`。
- 如果後台對外 API 位址改了，重新檢查 `admin-web/runtime-config.js`。
- 不要跳過 `npm run build:h5`，否則最容易出現「Git 更新了但頁面像沒變或損壞」。

## 伺服器需要裝什麼

- `git`
- `Nginx`
- `PHP 8.3`，最低需 PHP 8+
- `MySQL 8.0` 或 `MariaDB 10.11`
- `Node.js 18` 或 `20`

PHP 擴展至少開啟：

- `pdo_mysql`
- `mysqli`
- `curl`
- `mbstring`
- `fileinfo`
- `openssl`
- `json`

## 建立資料庫

建議：

```text
資料庫名：eurnyse_c2c
使用者名：eurnyse_c2c
密碼：正式伺服器自行設定強密碼
字符集：utf8mb4
```

說明：

- 首次請求時後端會自動建表與補欄位。
- 正式環境只保留 MySQL / MariaDB。
- 不需要導入 SQLite。
- 正式庫上線前要先備份，後續資料表變更仍建議先在 staging 演練。

## 後端配置

```bash
cd /www/wwwroot/eurnyse_c2c/backend-api
cp .env.example .env
```

重要：`cp .env.example .env` 只是在建立模板，不能直接拿模板上線。正式開站前必須把 `.env` 內所有占位資料換成正式資料，尤其是資料庫密碼、正式網域、後台 IP 白名單與安全開關。

編輯 `backend-api/.env`：

```dotenv
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=eurnyse_c2c
DB_USER=eurnyse_c2c
DB_PASSWORD=change_me_to_real_password

API_ALLOWED_ORIGINS=https://c2c.example.com,https://admin.example.com
FRONTEND_BASE_URLS=["https://c2c.example.com/h5"]
API_PUBLIC_BASE_URL=https://api.example.com/api
ADMIN_ALLOWED_IPS=你的固定辦公IP或VPN CIDR
UPLOAD_MAX_BYTES=5242880

ADMIN_LOGIN_MAX_ATTEMPTS=5
ADMIN_LOGIN_WINDOW_SECONDS=900
ADMIN_LOGIN_BLOCK_SECONDS=900

APP_ENABLE_DEMO_SEED_USERS=false
APP_DEMO_SEED_USER_PASSWORD=
APP_ENABLE_DEFAULT_ADMIN=false
APP_DEFAULT_ADMIN_ACCOUNT=
APP_DEFAULT_ADMIN_PASSWORD=
APP_ENABLE_RUNTIME_SUPER_ADMIN=false
APP_RUNTIME_SUPER_ADMIN_PASSWORD=
AUTH_DEBUG_VERIFICATION_CODE=false
```

注意：

- `DB_PASSWORD=change_me_to_real_password` 必須換成正式資料庫強密碼。
- `API_ALLOWED_ORIGINS` 必須換成正式前台與後台網域，不要保留 `127.0.0.1` 或測試域名。
- `FRONTEND_BASE_URLS` 必須換成正式前台 H5 網域。
- `API_PUBLIC_BASE_URL` 必須換成正式 API 網域。
- `ADMIN_ALLOWED_IPS` 必須依營運政策填固定辦公 IP 或 VPN CIDR；空白代表不限制後台 API 來源 IP。
- 固定使用 `DB_NAME / DB_USER`，不要改成 `DB_DATABASE / DB_USERNAME`。
- `API_ALLOWED_ORIGINS` 正式環境只填前台與後台正式網域。
- `FRONTEND_BASE_URLS / API_PUBLIC_BASE_URL` 要在新資料庫首次初始化前改成正式域名。
- `APP_ENABLE_DEMO_SEED_USERS` 生產環境必須保持 `false`，避免新資料庫自動建立 demo 使用者。
- `APP_ENABLE_DEFAULT_ADMIN` 生產環境預設保持 `false`；若首次部署需要由程式建立第一個管理員，必須臨時開啟並填入 `APP_DEFAULT_ADMIN_ACCOUNT / APP_DEFAULT_ADMIN_PASSWORD`，建立後再關閉。
- `APP_ENABLE_RUNTIME_SUPER_ADMIN` 生產環境必須保持 `false`。
- `AUTH_DEBUG_VERIFICATION_CODE` 生產環境必須保持 `false`，否則驗證碼會出現在 API response。

## 前台 API 位址

編輯：

```text
/www/wwwroot/eurnyse_c2c/frontend-uniapp/runtime-config.js
```

如果前台與 API 不同網域，正式部署時明確指定：

```javascript
window.__EURNYSE_RUNTIME_CONFIG__ = {
  apiBaseUrl: 'https://api.example.com',
  envName: 'production'
}
```

如果前台與 API 同源，可以保留自動偵測。但正式拆成 3 個站點時，通常需要明確填 `apiBaseUrl`。

改完後必須重新建置：

```bash
cd /www/wwwroot/eurnyse_c2c/frontend-uniapp
npm ci
npm run build:h5
```

前台最終站點目錄使用：

```text
/www/wwwroot/eurnyse_c2c/frontend-uniapp/dist/build
```

正式入口通常是：

```text
https://c2c.example.com/h5/#/pages/common/login
https://c2c.example.com/h5/#/pages/common/register
```

## 後台 API 位址

編輯：

```text
/www/wwwroot/eurnyse_c2c/admin-web/runtime-config.js
```

如果後台與 API 不同網域，正式部署時明確指定：

```javascript
window.__ADMIN_RUNTIME_CONFIG__ = {
  apiBaseUrl: 'https://api.example.com',
  envName: 'production',
  prototypeUi: false
}
```

如果後台與 API 同源，可以保留自動偵測。但正式拆成 3 個站點時，通常需要明確填 `apiBaseUrl`。

## Nginx / 寶塔站點

API 站點：

```text
域名：https://api.example.com
目錄：/www/wwwroot/eurnyse_c2c/backend-api/public
PHP：8.3
```

API 站點 Nginx 核心配置：

```nginx
location / {
    try_files $uri $uri/ /index.php?$query_string;
}

location ^~ /storage/uploads/ {
    alias /www/wwwroot/eurnyse_c2c/backend-api/storage/uploads/;
    access_log off;
    expires 7d;
    try_files $uri =404;
}

location ~* ^/storage/(?!uploads/) {
    deny all;
}

location ~* /storage/uploads/.*\.(php|phtml|phar|cgi|pl|py|sh|bash)$ {
    deny all;
}
```

前台站點：

```text
域名：https://c2c.example.com
目錄：/www/wwwroot/eurnyse_c2c/frontend-uniapp/dist/build
類型：靜態站
```

後台站點：

```text
域名：https://admin.example.com
目錄：/www/wwwroot/eurnyse_c2c/admin-web
類型：靜態站
```

三個站點正式上線時都應申請 SSL，並開啟強制 HTTPS。

## 後端權限

```bash
chown -R www:www /www/wwwroot/eurnyse_c2c/backend-api/storage
find /www/wwwroot/eurnyse_c2c/backend-api/storage -type d -exec chmod 750 {} \;
find /www/wwwroot/eurnyse_c2c/backend-api/storage -type f -exec chmod 640 {} \;
```

只公開 `/storage/uploads/`，不要把整個 `storage/` 目錄映射給外網。

## 上線前安全檢查

正式開站前逐項確認：

1. `backend-api/.env` 內 `DB_PASSWORD` 已換成正式強密碼。
2. `API_ALLOWED_ORIGINS` 只保留正式前台與後台網域。
3. `ADMIN_ALLOWED_IPS` 已依營運政策限制固定辦公 IP 或 VPN CIDR。
4. `APP_ENABLE_RUNTIME_SUPER_ADMIN=false`。
5. `AUTH_DEBUG_VERIFICATION_CODE=false`。
6. 前台 `frontend-uniapp/runtime-config.js` 指向正式 API。
7. 後台 `admin-web/runtime-config.js` 指向正式 API。
8. 首次初始化若產生預設後台帳號，必須立即改密碼，並停用不需要的初始化帳號。
9. API 站點 docroot 應指向 `backend-api/public`，不要把整個 `backend-api` 目錄直接暴露。
10. 若 API URL 實際出現 `/index.php/api/...`，需調整 Nginx rewrite；正式建議固定使用 `/api/...`。

## 重啟服務

```bash
systemctl restart mysql 2>/dev/null || systemctl restart mariadb 2>/dev/null || true
systemctl restart php8.3-fpm 2>/dev/null || systemctl restart php-fpm 2>/dev/null || true
systemctl restart nginx
```

## 首次驗活

先測 API：

```bash
curl -X POST "https://api.example.com/api/admin/auth/login" \
  -H "Content-Type: application/json" \
  -d '{"account":"你的後台帳號","password":"你的後台密碼"}'
```

看到 `ADMIN_LOGIN_SUCCESS` 代表 `Nginx`、`PHP-FPM`、`MySQL / MariaDB`、`backend-api/.env` 已基本聯通。

再打開：

```text
https://c2c.example.com/h5/#/pages/common/login
https://c2c.example.com/h5/#/pages/common/register
https://admin.example.com/
```

後台至少檢查全部側欄模組，尤其：

1. `儀表板`
2. `使用者管理`
3. `資金錢包`
4. `充值審核`
5. `充值地址管理`
6. `提現審核`
7. `訂單中心`
8. `商戶掛單`
9. `理財配置 / 理財訂單`
10. `KYC`
11. `操作日誌`
12. `系統配置`

## 建置與檢查指令

前台：

```bash
cd frontend-uniapp
npm ci
npm run build:h5
npm run audit:prod
```

後端：

```bash
cd backend-api
php -l public/index.php
npm audit --omit=dev
```

後台 JS 語法：

```bash
node --check admin-web/shared/admin-modals.js
node --check admin-web/shared/admin-view-loaders.js
```

## 目前已知上線注意事項

- 前台 `npm run audit:prod` 允許 Vue 2 的 low 風險例外，詳見 `frontend-uniapp/SECURITY-AUDIT.md`。
- 字體與圖示資源需一併提交；目前倉庫包含 `PNG/` 圖示素材與 `frontend-uniapp/static/previews/shared/fonts/google-cdn-mirror/FONT-SOURCE.txt` 來源說明，但尚未包含 `.woff/.woff2/.ttf/.otf` 字體檔。如需完全離線字體，需先補齊字體檔與授權來源再提交。
- 市場行情依賴外部 API 與快取，外部 API 不通時會走 fallback，正式環境仍建議監控。
- 驗證碼 API 目前會寫入資料庫，是否串接真實 SMTP / SMS 需依正式營運策略確認。
- 後台若使用 `debugApi=1` 可開 API debug 面板，正式營運不要把此模式當日常入口。

## 相關文件

- `安裝說明.md`：更完整的安裝與上線說明。
- `frontend-uniapp/SECURITY-AUDIT.md`：前台 production npm audit 策略。
- `admin-web/BUTTON-AUDIT.md`：後台按鈕行為盤點。
- `LOADING-SCENE-SPEC.md`：Loading 場景配置規範。
