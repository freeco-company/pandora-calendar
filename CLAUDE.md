# Pandora Calendar · CLAUDE.md（子專案憲法）

> 潘朵拉月曆 — 月經 / 孕期 / 身體節律追蹤 · AI 個人化建議 · **集團 bodyRhythm 資料中心**
>
> 衝突優先序：**本檔 > `pandora/CLAUDE.md`（集團）> `~/.claude/CLAUDE.md`（全域）**

---

## ⚠️ 開工前先讀

1. **集團憲法 [`pandora/CLAUDE.md`](../CLAUDE.md)**：所有硬規則（命名、tone-voice、Identity 不落地 PII、合規 sanitizer、frontend asset 5MB、e2e 必綠）都繼承
2. **集團 HANDOFF [`pandora/HANDOFF.md`](../HANDOFF.md)**：當前 in-flight 任務 / 卡點
3. **本專案最近 commit log**：理解上次做到哪
4. **未綁母艦用戶 zero 加盟 CTA + zero 商品 CTA** — 月曆是女性週期記錄 App，第一印象不能是「順便買保健品」

---

## 🎯 定位與商業模型

**潘朵拉月曆是集團「身體節律」資料層的擁有者**。其他集團 App（潘朵拉飲食 / 肌膚 / 學院）都讀月曆寫入的 `GroupUserProfile.bodyRhythm` 來做個人化建議。

**月曆自己不是流量主入口** — 它的雙重角色：
1. **獨立訂閱產品**：NT$99/月，TA 廣（25-40 歲女性 250 萬人）+ 客單低 → 走量策略
2. **集團 bodyRhythm publisher**：寫入 `phase / cycle_day / next_period_eta` → meal 黃體期推低 GI、肌膚推保水 / 控油

對應 `docs/products.html` 第 3 個產品段。

### 紅線（不可違反）

| # | 紅線 | 為什麼 |
|---|---|---|
| 1 | **未綁母艦用戶零商品 / 零加盟 CTA** | 月經 App 首印象不能是電商，信任崩盤 = 用戶流失 |
| 2 | **不做廣告** | 月經 App 廣告生態傷品牌（Flo 殷鑑） |
| 3 | **不賣資料** | 月經資料 sensitivity 最高，FTC 罰過 Flo |
| 4 | **PII 不存本地** | 對齊 ADR-007 §2.3，本機 DB 只存 uuid + 週期資料 |
| 5 | **朵朵建議 / 商品連結文案必過 sanitizer** | 食安 §28 / 健食法 §14；經期 + 健康食品 = 高風險區 |
| 6 | **「我的 → 婕樂纖會員」深層商品連結 gate**：母艦 ≥1 訂單 + 訂閱中 + 連用 ≥ 90 天才出現 | 對齊 meal 同模式 |

---

## 🛠️ 技術棧

| 層 | 選型 | 為什麼 |
|---|---|---|
| Backend | **Laravel 13 + MariaDB（prod）/ SQLite（dev）** | 對齊集團 |
| Frontend | **Vue 3 + Vite + TS + Tailwind + Capacitor** | 對齊 meal 路線（meal 目前是 vanilla JS，月曆刻意升級到 Vue 3 — 日曆 UI 複雜度需要 framework；CLAUDE.md `Capacitor + Vue` 之承諾在月曆首次落地） |
| 測試 | Pest 4（backend）+ Playwright（e2e） | 集團標準 |
| AI（P3+） | py-service 的 LLM 路徑 | 不在月曆自建 LLM |
| 上架 | iOS App Store + Google Play | **不上 web**（月經資料極敏感 + TA 是手機快速記錄場景） |

---

## 📁 目錄結構

```
pandora-calendar/
├── CLAUDE.md                           ← 你正在看
├── README.md
├── backend/                            ← Laravel 13
│   ├── app/
│   │   ├── Models/                     ← Cycle / CycleSymptom / DodoCheckin / User
│   │   ├── Services/
│   │   │   ├── Calendar/               ← CyclePredictor / BodyRhythmCalculator
│   │   │   └── Dodo/                   ← DodoCheckinResponder（hard-coded mood × phase）
│   │   └── Http/Controllers/Api/V1/    ← Cycle / Symptom / Dodo / BodyRhythm
│   ├── database/
│   │   ├── migrations/                 ← cycles / cycle_symptoms / dodo_checkins
│   │   └── seeders/DemoSeeder.php      ← 3 用戶 × 90 天歷史
│   └── tests/Feature/CycleApiTest.php  ← 8 個測試
├── frontend/                           ← Vue 3 + Vite + Capacitor
│   ├── src/views/                      ← Login / Calendar / Log / Dodo / Profile
│   ├── src/api.ts                      ← axios + token + types
│   └── capacitor.config.json           ← appId: com.jerosse.pandora.calendar
└── e2e/
    └── tests/happy-path.spec.ts        ← Playwright happy paths
```

---

## 🚀 一年路線圖（M1-M12，到能上架收費）

| Phase | 月份 | 範圍 | 退場條件 |
|---|---|---|---|
| **P0 Foundation** ✅ | M1-M2 | Laravel + Vue scaffold、經期記錄 / 預測 / 月曆 UI / 朵朵 check-in / demo seed / e2e 綠 / Capacitor config | iOS Sim + Android Emu 跑完 happy path |
| **P1 Identity + Alpha** | M3 | 接 Pandora Core IdentityClient（mock → real）、PII 不落地稽核、TestFlight / Play Internal 內測 50 人 | 50 人連用 14 天無 P0 bug |
| **P2 訂閱上架（變現起點）** | M4-M5 | IAP（StoreKit 2 + Google Billing）、ECPay 後台、NT$99/月 + NT$899/年、freemium gating、App Store / Play 審過 | **正式上架，開始收訂閱** |
| **P3 集團 bodyRhythm + ADR-009 全鏈路** ✅ 2026-05-03 | M6 | `GroupUserProfile.bodyRhythm` publisher、HttpGamificationPublisher 啟用、13 event_kind 發佈點 + idempotency、Webhook + HMAC + replay nonce + user XP mirror、`/me/dodo` `/me/pet` API；前端 sound.ts (17 SFX) + Character.vue (5 species + 12 outfit + 7 mood) + XpToast / LevelUpModal / AchievementToast 全棧打通 | 73/73 Pest + 5/5 e2e + build clean |
| **P4 AI 個人化 + 進階 paywall** | M7-M8 | py-service LLM 接通做 PMS 模式辨識 / 個人化文案、含金量問答、孕期模式 | 付費轉換率 ≥ 4% |
| **P5 健康整合 + 留存深化 + 婕樂纖商品連結點** | M9-M10 | HealthKit / Health Connect 讀基礎體溫 / 睡眠、進度照、week report、**深層婕樂纖商品連結**（gate: 母艦消費 + 訂閱 + 連用 90d） | 30 日留存 ≥ 35% |
| **P6 跨 App 聯動 + 加盟漏斗訊號** | M11-M12 | 潘朵拉肌膚讀 bodyRhythm、ADR-003 lifecycle 訊號 publisher（calendar_sustained_user → 母艦 lead pool，不在 App 內顯示） | ARR 站穩 NT$3M+ |

**Y1 保守目標**：M5 上架 → M12 月活 30K + 付費 1,500 + ARR ~NT$1.5M

---

## 🔌 集團整合 hook 全景（Phase 對應）

### A. 集團系統層（基礎建設）

| Hook | Phase | 說明 |
|---|---|---|
| Pandora Core Identity | P1 | 月曆登入 = 集團統一帳號；用過 meal / mother 的人秒登 |
| 共用寵物 + 朵朵 NPC | P1 | 寫經期記錄 → 餵食寵物；連勝 30 天 → outfit |
| ADR-009 集團 XP / 解成就 publisher | P3 | `pandora_calendar.cycle_logged` / `cycle_streak_3_months` 等 event → py-service catalog |
| `GroupUserProfile.bodyRhythm` 寫入 | P3 | **月曆是集團唯一寫入者**，meal / 肌膚 / 學院讀取 |

### B. 跨 App 內容互通

| Hook | Phase | 說明 |
|---|---|---|
| meal 讀 bodyRhythm | P3 | 黃體期 → meal 朵朵建議「今天可以準備一些黑巧克力（25g 內），陪伴情緒起伏的妳」|
| 跨 App 年度回顧 | P5 | 「妳今年記錄了 11 次週期 + 拍了 487 餐 + 步數 X 公里」 |
| 潘朵拉肌膚讀 bodyRhythm | P6+ | 排卵期推保水 / 黃體期推控油 |

### C. 婕樂纖商品連結（嚴守紅線）

僅對「**綁母艦 + 訂閱中 + 連用 ≥ 90 天**」用戶在「**我的 → 婕樂纖會員**」深層出現：

| 商品連結點 | 文案範例（必過 sanitizer） | Phase |
|---|---|---|
| 婕樂纖爆纖錠 | 黃體期紀錄到「腹脹」3 次 → 「妳這週期腹脹有點頻繁，婕樂纖爆纖錠是不少朋友的選擇 →」 | P5 |
| 高機能益生菌 | 經期紀錄到「腸胃不適」→ 同模式 | P5 |
| 水光錠 | 經前一週紀錄「皮膚乾」→ 同模式 | P5 |
| 厚焙奶茶 | 經前嗜甜 → 「想喝甜的時候，婕樂纖厚焙奶茶是不少朋友的點心選擇」（**禁用代餐 / 取代正餐 / 低 GI**） | P5 |

### D. FP 加盟商 hook（不在 App 內顯示）

- **加盟漏斗 qualified 訊號**：連用 6 個月 + 母艦消費 + 主動分享朵朵建議 → publisher 推進 lifecycle 到 `loyalist_high` → 母艦後台給加盟商 lead 訊號
- **App 內永遠 zero 加盟對話**

### E. 母艦數據回流

- 黃體期記錄水腫 → 母艦 admin 看「過去 30 天有 X 個用戶記錄水腫」→ 給商品 PM 排廣告優先序
- 商品評論增加「週期相位」維度 → SEO 素材

---

## 🧪 開發流程

### 啟動 dev 環境

```bash
# Backend
cd backend
composer install
cp .env.example .env && php artisan key:generate
php artisan migrate --seed
php artisan serve --host=127.0.0.1 --port=8000

# Frontend
cd frontend
npm install
npm run dev   # http://localhost:5174
```

### 跑測試

```bash
cd backend && php artisan test          # Pest 8/8 ✓
cd e2e && npx playwright test           # happy-path 2/2 ✓
```

### Demo 帳號（Phase 0）

| Name | Email | Cycle Length |
|---|---|---|
| 小敏 | demo-min@pandora-calendar.test | 28 天 |
| 雨晴 | demo-yuching@pandora-calendar.test | 30 天 |
| 阿伶 | demo-aling@pandora-calendar.test | 26 天 |

Password: `demo1234`（dev / testing 環境才開的 `/api/demo/login` endpoint）

---

## 🚫 子專案禁止事項

- ❌ 在 frontend 任何地方放「成為加盟夥伴」「開店賺錢」「婕樂纖事業夥伴」字眼（紅線 1）
- ❌ 在本機 DB 存 email / phone / address / password_hash / OAuth tokens（沿用集團硬規則）
- ❌ 在朵朵對白寫「改善經痛 / 緩解 PMS / 調理週期 / 排毒」療效詞（食安 / 健食法）
- ❌ 在 Phase 0-2 接 LLM / py-service（先把基礎留存做穩，AI 是 P4）
- ❌ 在月曆裡放婕樂纖商品 banner / icon / 推送，除了 P5+ 的「我的 → 婕樂纖會員」深層
- ❌ 上 web 版（月經資料 sensitivity + TA 是手機）

---

## 📋 commit / branch 規範

集團採 **branch + GitHub PR only，無 Jira**（沿用 [`auto-memory feedback_no_jira`](../.claude/projects/-Users-chris-freeco-pandora/memory/feedback_no_jira.md)）。

- branch：`feature/<slug>` / `bugfix/<slug>` / `task/<slug>`
- commit message：`<type>: <description>`（不加 PG-XXX）
- e2e + Pest 全綠才能 merge

---

## 🟢 現況（2026-05-02 更新）

### ✅ 已完成

| 區塊 | 狀態 |
|---|---|
| P0 Foundation scaffold | ✅ Vue 3 + Laravel 13 + Capacitor + demo seed + 5 happy-path e2e |
| P1-P6 backend 全 stack scaffold | ✅ Identity client / IAP / gamification / AI / health / commerce gate |
| P2 上架就緒 | ✅ Apple JWS verify + Google service-account exchange + py-service catalog 對齊 |
| P3 集團 bodyRhythm + ADR-009 publisher（backend） | ✅ outbox + flush schedule + 7 個 publish 點 + idempotency key |
| P5.1 Publisher 啟用 | ✅ config/gamification.php + AppServiceProvider binding + every-minute schedule |
| P5.2 7 個發佈點 | ✅ cycle_logged / symptom_logged / mood_logged / app_opened / dodo_checkin / track_7_days / full_cycle_tracked / insight_read（含 first_cycle / streak_3_months 等延伸） |
| P5.3 Webhook consumer | ✅ HMAC + replay nonce middleware + level_up / achievement_awarded / outfit_unlocked dispatch + Cache::pull pending endpoint |
| P5.4 朵朵 / 寵物 API | ✅ GET /me/dodo + GET /me/pet + GET /me/gamification/pending |
| Pest test suite | ✅ **73/73 綠**（baseline 46 → +27） |

### ⚠️ 卡點 / 待動

| 區塊 | 狀態 |
|---|---|
| py-service catalog 對齊 4 新 event_kind | ⚠️ catalog 需新增 `calendar.mood_logged / app_opened / full_cycle_tracked / insight_read`（calendar 端 publish 會被 py-service 422 直到 catalog 補完） |
| Pandora Core Identity 接通（P1） | ⚠️ IDENTITY_DRIVER=mock，prod 需切 http |
| Capacitor iOS bundle | ⚠️ 卡 Apple Developer Portal 註冊 |
| Real keys（Apple IAP / Google Play SA / ECPay） | ⚠️ 卡使用者手動設 |

> 📅 最後更新：2026-05-02（P5.1-P5.4 完成 — ADR-009 publisher + webhook 全鏈 backend live）
> 🌙 維護：sub-project owner 待定
