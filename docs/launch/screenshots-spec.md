# 上架截圖規格 — 潘朵拉月曆 v1.0

> 📅 規格版本：2026-05-03
> 🎯 用途：design-visual-storyteller / ui-designer 製作 App Store / Play Store 截圖的工作清單
> 🛡️ 合規：所有 overlay 文字過 sanitizer · 所有 demo seed 用無 PII 假資料
> 🎨 視覺系統：peach 配色（沿用現行 frontend Tailwind theme）+ font-display 主標 + font-zen 內文

---

## 全套截圖規格

### 設備尺寸需求（必須各自輸出）

| 設備 | 解析度（px） | 數量 | 必交 |
|---|---|---|---|
| iPhone 6.7" (15 Pro Max) | 1290 × 2796 | 8 張 | ✅ |
| iPhone 6.1" (15) | 1179 × 2556 | 8 張 | ✅ |
| iPhone 5.5" (8 Plus) | 1242 × 2208 | 8 張 | ✅（Apple 仍要求） |
| iPad Pro 12.9" (6th gen) | 2048 × 2732 | 8 張 | ✅ |
| Android phone | 1080 × 1920 (min) | 8 張 | ✅ |
| Android 7-inch tablet | 1024 × 1600 | 4 張（精選） | ✅ |
| Android 10-inch tablet | 1280 × 1920 | 4 張（精選） | ✅ |

### 視覺系統 token

| 元素 | 規範 |
|---|---|
| 主色 | peach-400 `#F4A582` / peach-500 `#EC8B6A`（沿用 Tailwind config） |
| 副色 | sakura-300（重點圈選）/ cream-100（背景柔光） |
| Header text 字型 | `font-display`（思源宋 / Pretty 系字體） |
| Header text 字級 | 大字 56-72pt，行高 1.1 |
| Subtitle 字型 | `font-zen`（思源黑 / Inter） |
| Subtitle 字級 | 24-28pt，行高 1.4 |
| 朵朵 mascot | 沿用 Character.vue `species=dodo` `outfit=fp_crown` |
| 截圖邊距 | 上下保留 12-15% 給文案 overlay；中間放 device frame mockup |

### 通用設計指引

- ✅ 使用真實 view（不要 photoshop 假造）
- ✅ Demo seed 必須用「小敏」「雨晴」「阿伶」三個假帳號（CLAUDE.md §Demo 帳號）
- ❌ 截圖內不能出現任何用戶真實 email / 真實生理週期
- ✅ Status bar 統一：iOS 9:41 / 100% 電量 / 滿格訊號
- ✅ 系統色模式：light mode 為主（peach 在 light 較鮮）
- ✅ 截圖比例 9:16 直幅；橫式 banner 不需

---

## 8 張截圖逐張規格

### #1 — 隱私第一印象

**目的**：Flo 殷鑑後，用戶第一個怕的就是隱私。第一張就把這個 doubt 直接打掉。

| 欄位 | 值 |
|---|---|
| Screen 來源 | 開箱動畫 / Onboarding 第一頁（`onboarding/Welcome.vue` 或新建） |
| State | 初始狀態，朵朵居中、無資料 |
| Header overlay | **「妳的週期 只屬於妳」** |
| Subtitle overlay | 不放廣告 · 不賣資料 · 不追蹤 |
| Demo seed | 無（用初始狀態） |
| 設計指引 | 朵朵居中放大版（size 240+），背景柔光漸層；overlay 文字置上方 |
| 視覺重點 | 三個 ❌ icon 排成一列：❌ 廣告 ❌ 賣資料 ❌ 追蹤 |

---

### #2 — 月曆視覺化（核心功能）

**目的**：一眼看見產品做什麼。月曆是月經 App 的招牌畫面。

| 欄位 | 值 |
|---|---|
| Screen 來源 | `Calendar.vue` |
| State | 「小敏」demo seed，當前日期 day 14（排卵期），月份顯示 11 月 |
| Header overlay | **「下次經期 還有 8 天」** |
| Subtitle overlay | 朵朵幫妳記著，妳專心過日子 |
| Demo seed | demo-min@pandora-calendar.test（28 天週期） |
| 設計指引 | 紅色經期區塊 + 粉色排卵窗 + 黃體期淺粉色清楚分層；當前日期有「朵朵小頭像」浮在格子上 |
| 視覺重點 | 「下次經期」 countdown 大字浮在 calendar grid 上方 |

---

### #3 — 朵朵陪伴 / 每日 check-in

**目的**：傳達「不只是工具，是陪伴」這個情感差異化。

| 欄位 | 值 |
|---|---|
| Screen 來源 | `Dodo.vue`（朵朵首頁 / check-in） |
| State | 朵朵 mood=cheering，今日已 check-in，顯示朵朵悄悄話 |
| Header overlay | **「朵朵陪妳走 不催促」** |
| Subtitle overlay | 忘了一天？沒關係，朵朵會幫妳記著 |
| Demo seed | demo-yuching@pandora-calendar.test |
| 設計指引 | 朵朵中央站立 + outfit=fp_crown + halo + floaty 動畫 frame；下方一張朵朵悄悄話卡片 |
| 朵朵悄悄話文案 | 「今天的妳，看起來比昨天暖一點 🌸 朵朵替妳開心。」 |

---

### #4 — BBT 雙相偵測（差異化亮點）

**目的**：跟競品（Flo / Clue）拉差異 — BBT 是備孕 / 避孕族群最在意的功能。

| 欄位 | 值 |
|---|---|
| Screen 來源 | `BbtView.vue` |
| State | 已記錄 21 天 BBT，顯示低溫期 → 高溫期雙相曲線，排卵點被朵朵 marker 標出 |
| Header overlay | **「BBT 幫妳找到 排卵窗口」** |
| Subtitle overlay | 備孕、避孕都更心裡有底 |
| Demo seed | demo-aling@pandora-calendar.test（26 天週期） |
| 設計指引 | 折線圖 + 雙色區塊（低溫 cream / 高溫 peach）+ 排卵點放大圈選 + 朵朵小頭像指向排卵點 |
| 視覺重點 | 「排卵窗口 11/14 - 11/18」標籤 |

---

### #5 — 衛教深度（Premium 賣點 1）

**目的**：拉開與一般打卡 App 的差距 — 我們提供有深度的內容（朵朵衛教 / PMS 模式）。

| 欄位 | 值 |
|---|---|
| Screen 來源 | `JerosseDeep.vue` 或 `WeekReportView.vue` |
| State | 顯示一篇衛教 + 個人化週報 |
| Header overlay | **「不是泛泛的健康文 是今天的妳」** |
| Subtitle overlay | 貼合妳當下相位的內容 |
| Demo seed | demo-min@pandora-calendar.test |
| 設計指引 | 文章標題 + 朵朵 commentary 卡片 + 「為什麼朵朵推薦這篇給妳」貼合 phase 的解釋 |
| 範例文案 | 「妳這週是黃體期，朵朵想跟妳聊聊『情緒起伏為什麼跟相位有關』。」（過 sanitizer：不寫療效） |

---

### #6 — 一年回顧 Wrapped（情感記憶點）

**目的**：年底分享行為的核心動機 — Spotify Wrapped 的 women's health 版。

| 欄位 | 值 |
|---|---|
| Screen 來源 | `YearReviewView.vue` |
| State | 年末打開，已記錄 11 個週期 |
| Header overlay | **「2026 妳記錄了 11 個週期」** |
| Subtitle overlay | 朵朵幫妳寫了一封信 🌸 |
| Demo seed | demo-yuching@pandora-calendar.test |
| 設計指引 | sakura 漸層大背景 + 朵朵中央 + 數字大字統計卡片 stack |
| 統計示意 | 11 個週期 / 最常出現的情緒：平靜 / 最暖的一天：3 月 14 日 / 連續記錄 287 天 |

---

### #7 — 伴侶分享（Premium 賣點 2）

**目的**：差異化 — 這是個獨特功能，讓他懂妳，但不過界。

| 欄位 | 值 |
|---|---|
| Screen 來源 | `PartnerShareView.vue` |
| State | 已產生分享連結，顯示分享預覽（伴侶端會看到的） |
| Header overlay | **「讓他懂妳 不過界」** |
| Subtitle overlay | 只分享相位與下次經期，症狀不外流 |
| Demo seed | demo-min@pandora-calendar.test |
| 設計指引 | 左側手機畫面顯示自己的 App、右側「他的」預覽（縮小版）— 對比兩端看到的不一樣 |
| 視覺重點 | 一條紅色「不分享」清單：症狀、情緒、性行為記錄、體重 |

---

### #8 — Premium 訂閱（轉換頁）

**目的**：訂閱轉換最後一推。已經滑到第 8 張的人最有意願。

| 欄位 | 值 |
|---|---|
| Screen 來源 | `Paywall.vue` |
| State | 預設選年費（最受歡迎標籤亮著），5 大 benefits 全顯示 |
| Header overlay | **「朵朵陪妳 更深一點」** |
| Subtitle overlay | NT$75 / 月起 · 不放廣告 · 不賣資料 |
| Demo seed | 無（直接用 entitlements=free 狀態） |
| 設計指引 | 朵朵戴 fp_crown 居中 + 5 個 benefit 條列 + 年費卡片亮著（peach-400 border + ring） |
| 視覺重點 | 「最受歡迎 · 省 24%」徽章 + NT$899 / 年大字 |

---

## 排序邏輯

App Store / Play Store 預設展示前 3 張，後 5 張靠用戶主動滑。所以：

| Slot | 截圖 | 為什麼放這 |
|---|---|---|
| 1 | #1 隱私第一 | 一秒打掉「月經 App = 賣資料」doubt |
| 2 | #2 月曆視覺化 | 一秒看到產品做什麼 |
| 3 | #3 朵朵陪伴 | 一秒看到差異化（不只是工具） |
| 4 | #4 BBT | 備孕族群直接被打中 |
| 5 | #5 衛教深度 | 留住會看到第 5 張的高意願者 |
| 6 | #6 年度回顧 | 情感記憶點，引發分享念頭 |
| 7 | #7 伴侶分享 | 獨特功能 |
| 8 | #8 Premium | 訂閱轉換 closer |

---

## design-visual-storyteller 工作交接清單

當這份規格交給 ui-designer / design-visual-storyteller 時，他們需要的：

- [ ] 8 張截圖 × 7 種尺寸 = 56 個輸出檔
- [ ] 命名規則：`{slot}-{theme}-{device}-{locale}.png`，例：`01-privacy-iphone-67-zh.png`
- [ ] 中文版 + 英文版各一套（共 112 檔）
- [ ] 圖檔格式：PNG（透明區可選）；單檔 ≤ 5MB（集團硬規則 §9）
- [ ] 提交位置：`docs/launch/screenshots/`（待目錄建立）

---

## 製圖工具建議

- **Mockup 框架**：Figma + Apple Design Resources / Google Pixel Mockups
- **Demo seed 截圖**：在 dev 環境跑 `php artisan migrate:fresh --seed` 後用 demo 帳號登入
- **Overlay 排版**：Figma component 化，方便 A/B 改文案
- **批次輸出多尺寸**：Figma export at 1x / 2x / 3x，再 resize 對應各設備

---

> 📅 最後更新：2026-05-03
> 🎨 製圖 ownership：ui-designer + design-visual-storyteller（content-creator 不畫圖）
> 📝 維護者：content-creator（文案）/ ui-designer（視覺）
