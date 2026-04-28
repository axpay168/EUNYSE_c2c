# Loading 場景配置規範（不含標題區）

本規範把 Loading 示範頁拆成可重用能力，不允許整頁直接套用示範 HTML。

## 1) 原語定義（Primitives）

### A. Spinner（圓環）
- 使用時機：單點操作、短等待、無法量化進度。
- 建議位置：按鈕內、表格列操作按鈕旁、卡片局部。
- 禁止：用全頁遮罩取代列表骨架。
- 結束條件：請求回應（成功/失敗）或超時後進入錯誤提示。

### B. Progress（進度條）
- 使用時機：批次操作、分步流程、可計算 `current/total`。
- 建議呈現：百分比 + 已完成數 + 階段文案（如 `1/2 上傳中`）。
- 禁止：可量化流程只顯示 spinner。
- 結束條件：100% 且流程完成；中途失敗需保留重試入口。

### C. Skeleton（骨架）
- 使用時機：列表、卡片、表格的首屏載入與重載。
- 建議呈現：與最終內容結構對齊（欄位、列高、間距）。
- 禁止：資料返回後仍保留骨架或與空態混用。
- 結束條件：有資料進入成功態；無資料進入空態。

## 2) 全域決策規則

1. 若可計算進度 -> 使用 `Progress`。  
2. 若為內容載入（表格/列表/卡片）且預期超過 300ms -> 使用 `Skeleton`。  
3. 其餘短操作 -> 使用 `Spinner`。  
4. 同頁可混用：頁面區塊載入用 `Skeleton`，行內動作用 `Spinner`。  
5. 禁止把示範 Loading 頁當作共用覆蓋層直接套在業務頁。  

## 3) 前台場景映射（frontend-uniapp）

### `pages/setting/myTask.vue`
- 訂單列表初載、篩選重載：`Skeleton`
- 單筆行為（如取消訂單送出）：按鈕 `Spinner`

### `pages/setting/fundRecord.vue`
- 流水列表載入：`Skeleton`
- 篩選切換後刷新：局部 `Skeleton`，避免整頁閃爍

### `pages/setting/mixrecharge.vue`
- 上傳憑證 + 送出申請：`Progress`（分步）
- 送出按鈕：按鈕 `Spinner`

### `pages/setting/eurDeposit.vue`
- 上傳 + 申請流程：`Progress`（分步）
- 送出按鈕：按鈕 `Spinner`

### `pages/setting/orderDetail.vue`
- 詳情首次拉取：優先內容區 `Skeleton`，次選輕量區塊 `Spinner`
- 取消訂單操作：按鈕 `Spinner`

### `pages/index/index.vue`
- 行情卡片初始資料：卡片 `Skeleton`
- 行情/交易動態模塊刷新：模塊級 `Spinner` 或骨架行（依資料結構）

## 4) 後台場景映射（admin-web）

### `shared/admin-view-loaders.js`
- 入金/提現/訂單/KYC 列表：表格 `Skeleton`
- 單筆審核按鈕：按鈕 `Spinner`
- 批次審核流程：`Progress`（進度條 + 百分比 + 完成數）

### `shared/admin-modals.js`
- 使用者批次操作：`Progress`
- 單筆狀態切換：`Spinner`

### `index.html`
- 各模塊容器預留 loading 插槽，避免載入切換造成跳動

## 5) 狀態機規範

每個業務區塊至少支援 3 態，可選第 4 態：
- `loading`
- `success`
- `error`
- `empty`（僅資料拉取場景）

轉移規則：
- `loading -> success`：成功取得資料或操作成功
- `loading -> empty`：成功但資料為空
- `loading -> error`：請求失敗，顯示錯誤與重試入口

## 6) UX 細節約束

- Spinner 最小顯示 300ms，避免閃爍。
- Skeleton 建議最小顯示 400ms，避免內容抖動。
- Progress 必須有階段訊息，不只顯示百分比。
- 錯誤後保留輸入上下文，不清空表單。

## 7) 落地順序與回歸清單

### 落地順序
1. 固化命名與判斷規則（不調整視覺）
2. 前台列表與提交流程套用
3. 後台列表、單筆與批次套用
4. 以關鍵路徑回歸驗證

### 回歸驗證（最小集合）
- 前台：`myTask`、`fundRecord`、`mixrecharge/eurDeposit`、`orderDetail`、首頁行情模塊
- 後台：入金、提現、訂單、KYC 列表；單筆審核；批次審核
- 檢查重點：列表不再用全頁 spinner；批次具體進度可視；錯誤可重試且不丟失上下文

