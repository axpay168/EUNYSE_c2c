# New_C2C 定稿 HTML 架構（`docs/previews/nnn`）

## 原則

- **視覺不可變**：定稿頁依賴凍結樣式表 `css/draft-pixel-frozen.css`（內容與歷史 `../shared/eurnyse.utilities.css` 同位元組級一致），包含原子 class 規則（如 `flex`、`text-on-surface` 等）。**不再使用 Tailwind CLI 或建置流程**；此檔為唯一「像素鎖」來源。
- **語義掛鉤**：每頁 `<body>` 帶 `nc2c-page nc2c-page--<檔名>`，便於腳本／截圖／後續重構對照，**不加上任何覆寫型規則**（見 `css/nc2c-shell.css`）。
- **SCSS**：`scss/_tokens.scss` 備妥色票變數，與主題對照檔一致；若日後要改為「純 SCSS 輸出 CSS」，須另行建立編譯流程並做像素迴歸。

## 檔案

| 路徑 | 說明 |
| --- | --- |
| `css/preview-entry.css` | `nnn/*.html` 統一入口，內含本地字體、凍結樣式與頁殼樣式 |
| `css/preview-engineered.css` | SCSS 工程層輸出的共用樣式，承接已抽離的重複規則 |
| `css/page-login.css` | `nnn/login.html` 專用頁面樣式（由原頁內 style 抽離） |
| `css/page-register.css` | `nnn/register.html` 專用頁面樣式（由原頁內 style 抽離） |
| `css/draft-pixel-frozen.css` | 凍結設計樣式（禁止手改規則；改動須視覺迴歸） |
| `css/nc2c-shell.css` | 僅頁殼語義，無視覺覆寫 |
| `scss/_tokens.scss` | 設計權杖 |
| `scss/_mixins.scss` | 共用 mixin |
| `scss/base/*` | 頁面基底規則 |
| `scss/components/*` | 共用元件樣式來源 |
| `scss/pages/*` | 頁面級補丁與過渡樣式來源 |
| `scss/preview-engineered.scss` | SCSS 工程總入口 |

## HTML 引用範例

```html
<link rel="stylesheet" href="../nc2c/css/preview-entry.css"/>
```

## 分層原則

- `draft-pixel-frozen.css`：像素鎖定層，避免直接為了工程化而大改。
- `preview-engineered.css`：工程承接層，優先吸收可安全抽離的多頁重複樣式。
- `scss/*`：後續轉 uni-app 與正式前端樣式的來源層。
