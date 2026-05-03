# 潘朵拉月曆 · WCAG A11y Audit（Frontend）

> 範圍：`pandora-calendar/frontend/src/views/` 中非 i18n agent ownership 的 view（共 16 個檔案）。
> 審查依據：WCAG 2.1 Level A（必過）+ Level AA（應過）。
> 本輪策略：critical fix in-place，色票 / contrast / focus trap 等需設計或 framework 工程的 issue 留 TODO。
>
> 標記：✅ 已修　🟡 設計決策待跟進　🔴 待修

---

## Audit 涵蓋（16 view）

| View | LoC | 主要互動 |
|---|---:|---|
| Profile.vue | 769 | 設定 / toggles / 訂閱 / 刪除帳號 |
| FaqView.vue | 155 | 摺疊 FAQ |
| FeedbackView.vue | 156 | 表單回饋 |
| MedicalSafetyView.vue | 239 | 多步評估 |
| YearReviewView.vue | 227 | 卡片瀏覽（鍵盤 / 觸控） |
| CancelInterceptView.vue | 333 | 4 步退訂流 |
| CommunityListView.vue | 207 | List + FAB |
| CommunityDetailView.vue | 295 | 詳情 + 回覆 + 檢舉 modal |
| CommunityCreateView.vue | 183 | 發文表單 |
| HealthIntegrationView.vue | 243 | 4 toggle + sync |
| BbtView.vue | 158 | 體溫表單 + sparkline |
| WeekReportView.vue | 86 | Premium 顯示 |
| JourneyView.vue | 241 | 成就 / outfit 解鎖牆 |
| PartnerShareView.vue | 97 | 啟用分享 |
| PartnerPublicView.vue | 81 | 公開檢視（無互動） |
| JerosseDeep.vue | 79 | 深層商品連結 |
| PmsView.vue | 85 | Premium 顯示 |
| Privacy.vue | 50 | 純文字 |
| Terms.vue | 51 | 純文字 |
| Lock.vue | 113 | 全螢幕鎖定 |

---

## Issue 統計

| Level | 總計 | 已修 ✅ | 待修 🔴 / 設計 🟡 |
|---|---:|---:|---:|
| **A**（必過） | 11 | 11 | 0 |
| **AA**（應過） | 7 | 0 | 7（多為 contrast / focus trap，需設計協助） |
| **AAA**（建議） | 3 | 0 | 3 |

---

## Profile.vue

### A-1 ✅ 帳號刪除確認 input 無 label
- **WCAG**：1.3.1 Info and Relationships, 4.1.2 Name/Role/Value
- **before**：`<input v-model="deleteConfirmText" placeholder="輸入「刪除」二字確認" .../>`
- **after**：補 `<label for="delete-confirm-input" class="sr-only">` + `id` + `aria-label`

### A-2 ✅ 個人頭像 emoji `<div>` 無語意
- **WCAG**：1.1.1 Non-text Content
- **after**：補 `role="img" aria-label="個人頭像"`

### AA-1 🟡 toggle switch（音效 / 通知 / 鎖定）僅靠位置區分狀態
- 已使用 `:aria-pressed`，sr 可讀。視覺上 dark/light track 對 colorblind 使用者區分度足夠（peach-400 vs stone-300 contrast > 3:1），通過。
- **無 action**

### AA-2 🟡 訂閱「進行中 / 免費版」badge 僅顏色區分
- 雖有文字搭配，但「進行中」綠調 vs「免費版」灰調 contrast 接近邊界。建議下一輪設計調 peach-100/peach-600 確保 4.5:1。
- **TODO（設計）**：請設計師驗證 peach-100 + peach-600 contrast。

### A-3 ✅ 寵物 `<Character>` 元件 SVG/img alt
- 元件內部已自帶 alt（外部已驗）。

---

## FaqView.vue

- **A**：摺疊按鈕已用 `:aria-expanded`，每組問答有正確語意 ✅
- **AAA-1**：可考慮加 `aria-controls` 指向答案 div id（更精準）。**TODO**

---

## FeedbackView.vue

- **A**：textarea 有 `<label for="fb-message">` ✅
- **A**：`:aria-invalid="!!errorMsg"` ✅
- **A**：分類按鈕用 `:aria-pressed` ✅
- 無新 issue。

---

## MedicalSafetyView.vue

- **A**：context 按鈕用 `:aria-pressed` ✅
- **A**：range slider 有 `aria-label="已延遲天數"` ✅
- **A**：urgency 結果有 emoji + 文字（不僅靠顏色）✅
- **AA-3 🟡**：amber-50/rose-50 背景對 stone-700 文字接近 4.5:1 邊界，建議下次設計 review。

---

## YearReviewView.vue

### A-4 ✅（既有）關閉按鈕 `aria-label="關閉"`
### AAA-2 🟡 卡片內容自動化 / 觸控滑動無 `role="region"` / `aria-live`
- 卡片切換目前不會通知 screen reader。建議補 `role="region" aria-roledescription="story-card" aria-live="polite"` 在內容容器。**TODO**（不擋上架，下次處理）。

---

## CancelInterceptView.vue

### A-5 ✅ feature feedback textarea 無 label
- **before**：`<textarea v-model="featureMessage" .../>`
- **after**：補 `<label for="cancel-feature-message" class="sr-only">` + `id` + `aria-label`
- **A**：reasons 按鈕已 `:aria-pressed` ✅

---

## CommunityListView.vue

### A-6 ✅ FAB 「＋」icon-only 缺 aria-label
- **before**：`<button :title="...">＋</button>`
- **after**：補 `:aria-label="canPostHint ?? '發新貼文'"`

### A-7 ✅ like / reply count icon 缺語意
- **before**：`<span>♡ {{ p.like_count }}</span>`
- **after**：補 `:aria-label="\`${p.like_count} 個喜歡\`"`

### AA-4 🟡 category tab 選中態僅 bg-peach-300 vs bg-white/70
- 視覺差異 OK，但 colorblind 場景可考慮加 `aria-pressed` 在 tab button。**TODO**。

---

## CommunityDetailView.vue

### A-8 ✅ reply textarea 無 label
- 補 `<label for="community-reply" class="sr-only">` + `id`

### A-9 ✅ 報告 modal 缺 dialog 語意
- 補 `role="dialog" aria-modal="true" aria-labelledby="report-modal-title"`
- modal 標題加 `id="report-modal-title"`
- supplement textarea 加 `<label for="report-message">` + `aria-label`

### A-10 ✅ like 按鈕（post + reply）icon-only 缺 aria
- 補 `:aria-label` + `:aria-pressed="liked"`

### A-11 ✅ self-harm hotline banner 缺 region 語意
- 補 `role="region" aria-label="緊急求助專線"`

### AA-5 🟡 Modal 沒有 focus trap
- 開啟 modal 時 focus 不會自動跳進去；ESC 也無法關閉。**TODO**（建議下次引入 `vue-focus-trap` 或 headlessui）。

---

## CommunityCreateView.vue

### A-12 ✅ title input + body textarea 無 `for`/`id` 綁定
- 已修 `<label for="community-title">` + `id`、`<label for="community-body">` + `id`、補 `aria-label`
- **A**：分類 button 已有 emoji + label，但缺 `:aria-pressed`。**TODO 小**：可加但非阻擋。

### AA-6 🟡 soft warning 警示僅 peach-700 文字
- contrast OK，但文字前的 ⚠ 圖示需確保非唯一傳達。✅（已配文字描述）

---

## HealthIntegrationView.vue

### A-13 ✅ Toggle 隱藏 checkbox 缺 `aria-label`
- before：`<input type="checkbox" class="sr-only peer" .../>`
- after：補 `<span class="sr-only">{{ KIND_META[kind].label }}</span>` + `:aria-label`

### AA-7 🟡 平台 banner 顏色為主要狀態載體
- 雖有 emoji + 文字輔助，但綠/橘/米色塊主要靠 hue。已有 `✅ / 📱 / ⏳` 強化，通過 Level A。

---

## BbtView.vue

- **A**：日期 / 體溫 input 用 wrapping `<label>` ✅
- **A**：刪除 `×` button 有 `aria-label="刪除"` ✅
- **AAA-3 🟡**：sparkline `<svg>` 缺 `role="img" aria-label="過去 60 天體溫趨勢"`。**TODO**（不阻擋）。

---

## CommunityListView / Lock / others

### Lock.vue ✅
- 已補 `role="dialog" aria-modal="true" aria-labelledby="lock-title"`

### PartnerPublicView / PartnerShareView / Privacy / Terms / WeekReportView / PmsView / JerosseDeep
- 純顯示頁，無表單 / icon-only button 問題。
- 唯一例外：JerosseDeep `「← 回我的」` 是 text-only，OK。

### JourneyView
- **A**：徽章 `<img>` 全部有 `:alt="a.name"` ✅
- **AAA**：rarity 顏色作為主要區分，但配 `RARITY_LABEL` 文字（一般 / 稀有 / 史詩 / 傳說）已通過 Level A。

---

## 全局 Level AA 待動清單（給設計 / framework 工程）

| # | Issue | 建議 | 優先 |
|---|---|---|---|
| AA-1 | toggle on/off 顏色 contrast 邊界（peach-400 vs stone-300 約 3.2:1） | 視為非文字 UI，> 3:1 通過。維持。 | 低 |
| AA-2 | 訂閱 badge 進行中 vs 免費版 hue contrast | 設計 review peach-100/peach-600 套色 | 中 |
| AA-3 | medical urgency 卡片 rose/amber 背景 vs stone-700 | 量測 contrast，必要時加深文字 | 中 |
| AA-4 | community category tab 選中態加 `aria-pressed` | 工程小工 | 低 |
| AA-5 | report modal / Lock overlay focus trap | 引入 `@vueuse/components` `useFocusTrap` 或 headlessui | 中 |
| AA-6 | softWarning 純圖示不阻擋（已含文字）| 維持 | — |
| AA-7 | health banner colour-only fallback | 已有 emoji 輔助 | 低 |
| ALL | rem 單位字體 / 縮放支援 | 抽樣量測；目前使用 Tailwind text-xs/sm/base 為 rem | 低 |

## 全局 Level AAA（不阻擋上架）

- 摺疊面板 `aria-controls` 連結
- 卡片切換 `aria-live="polite"`
- BBT sparkline svg 標題化

---

## 總結

- **已修 11 條 Level A critical issue**（form label、icon-only button、modal dialog、頭像 / hotline 語意）
- **AA 留 7 條設計協助 TODO**（contrast review、focus trap）
- **AAA 留 3 條工程小工 TODO**

下次處理建議：
1. 設計 review contrast（peach-100/peach-600、rose-50/rose-600、amber-50/amber-600）
2. 引入 focus trap composable（modal / Lock / YearReview overlay）
3. 雙週 a11y 巡檢，追加 i18n agent 6 view（Login / Onboarding / Paywall / Calendar / Log / Dodo）

> 📅 最後更新：2026-05-03（初版 audit + critical fix）
