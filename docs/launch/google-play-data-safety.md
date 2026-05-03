# Google Play Data Safety Form — 潘朵拉月曆 v1.0

> 📅 提交版本：2026-05-03
> 🎯 用途：Google Play Console → App content → Data safety 表單（Google 必填，2024+ 規定）
> 🛡️ 原則：與 Apple Privacy Nutrition Label 一致；Data sharing 一律 No
> 📚 參考：[Google Play Data Safety](https://support.google.com/googleplay/android-developer/answer/10787469)

---

## TL;DR

| 項目 | 答案 |
|---|---|
| Does your app collect or share any of the required user data types? | **Yes**（最少必要） |
| Is all of the user data collected by your app encrypted in transit? | **Yes**（HTTPS / TLS 1.3） |
| Do you provide a way for users to request that their data be deleted? | **Yes**（App 內「我的 → 刪除帳號」+ 客服 email） |
| Has your app been independently validated against a global security standard? | **No**（v1.0 尚未做 SOC 2 / ISO 27001 認證；P5+ roadmap） |

---

## Section 1 — Data Collection & Sharing Summary

| Data Category | Collected | Shared | Optional / Required | Encrypted in Transit |
|---|---|---|---|---|
| Personal info | ✅ | ❌ | Required（Email） | ✅ |
| Financial info | ❌ | ❌ | — | — |
| Health & fitness | ✅ | ❌ | Optional（用戶不記就不收） | ✅ |
| Messages | ❌ | ❌ | — | — |
| Photos and videos | ❌（v1.0） | ❌ | — | — |
| Audio files | ❌ | ❌ | — | — |
| Files and docs | ❌ | ❌ | — | — |
| Calendar | ❌ | ❌ | — | — |
| Contacts | ❌ | ❌ | — | — |
| App activity | ✅ | ❌ | Required | ✅ |
| Web browsing | ❌ | ❌ | — | — |
| App info & performance | ✅ | ❌ | Required | ✅ |
| Device or other IDs | ❌ | ❌ | — | — |

> ✅ **Data sharing 一律 No** — 集團硬規則：不賣資料、不分享給廣告商、不傳給第三方分析平台跨 App 追蹤。

---

## Section 2 — Data Types Collected（細項）

### 2.1 Personal info

| Data type | Collected | Shared | Optional / Required | Purpose |
|---|---|---|---|---|
| Name | ❌ | — | — | — |
| Email address | ✅ | ❌ | Required | Account management（登入帳號）/ App functionality（密碼重設）/ Customer support |
| User IDs | ✅ | ❌ | Required | App functionality（Pandora Core UUID） |
| Address | ❌ | — | — | — |
| Phone number | ❌ | — | — | — |
| Race and ethnicity | ❌ | — | — | — |
| Political or religious beliefs | ❌ | — | — | — |
| Sexual orientation | ❌ | — | — | — |
| Other info | ❌ | — | — | — |

### 2.2 Financial info

| Data type | Collected |
|---|---|
| 全部 | ❌（透過 Google Play Billing 處理，App 不接觸） |

### 2.3 Health and fitness（核心類別）

| Data type | Collected | Shared | Optional / Required | Purpose |
|---|---|---|---|---|
| Health info | ✅ | ❌ | Optional | App functionality（經期 / BBT / 症狀 / 情緒 / 睡眠 / 疼痛紀錄為核心功能；用戶不記錄則不收） |
| Fitness info | ✅（P5+ Health Connect 接通後） | ❌ | Optional | App functionality（步數推算能量趨勢） |

> ⚠️ Google 將「menstrual cycle / sexual & reproductive health」歸 Health info；Data Safety 必須勾且明確聲明 Optional。

### 2.4 Messages

全部 ❌

### 2.5 Photos and videos

| Data type | Collected | Notes |
|---|---|---|
| Photos | ❌（v1.0） | P5+ 進度照功能上線後再申報 |
| Videos | ❌ | — |

### 2.6 Audio files

全部 ❌

### 2.7 Files and docs

全部 ❌

### 2.8 Calendar

全部 ❌

### 2.9 Contacts

全部 ❌

### 2.10 App activity

| Data type | Collected | Shared | Optional / Required | Purpose |
|---|---|---|---|---|
| App interactions | ✅ | ❌ | Required | Analytics（朵朵建議命中率）+ App functionality（成就 / XP / streak） |
| In-app search history | ❌ | — | — | — |
| Installed apps | ❌ | — | — | — |
| Other user-generated content | ✅（朵朵建議回饋 / 客服訊息） | ❌ | Optional | Customer support |
| Other actions | ❌ | — | — | — |

### 2.11 Web browsing

全部 ❌

### 2.12 App info and performance

| Data type | Collected | Shared | Optional / Required | Purpose |
|---|---|---|---|---|
| Crash logs | ✅ | ❌ | Required | App functionality（修 bug） |
| Diagnostics | ✅ | ❌ | Required | App functionality（效能優化） |
| Other app performance data | ❌ | — | — | — |

### 2.13 Device or other IDs

| Data type | Collected | Notes |
|---|---|---|
| Device or other IDs | ❌ | 不收 GAID / Android ID / IMEI；Crash logs 用匿名 hash |

---

## Section 3 — Purpose 全集

對齊 Google 定義之 Purpose 類別：

| Purpose | 本 App 是否使用 | 說明 |
|---|---|---|
| App functionality | ✅ | 核心功能必要 |
| Analytics | ✅ | App interactions 用於朵朵建議命中率分析 |
| Developer communications | ❌ | 不主動推播行銷訊息（推播僅週期提醒，不是行銷） |
| Fraud prevention, security and compliance | ✅ | Crash logs / Diagnostics 用於異常偵測 |
| Advertising or marketing | ❌ | **永不** |
| Personalization | ✅ | 朵朵建議基於 user 自己的資料個人化（非跨用戶 profile） |
| Account management | ✅ | Email 用於登入 / 密碼重設 |

---

## Section 4 — Security Practices

### 4.1 Encryption

- **Encrypted in transit**: ✅ Yes
  - 全部 API 走 HTTPS（TLS 1.2+）
  - Capacitor App 強制 ATS（App Transport Security）
- **Encrypted at rest**: ✅ Yes
  - Server side：MariaDB 走 cloud-managed encryption at rest
  - Client side：iOS Keychain / Android Keystore 存 token

### 4.2 User data deletion

- ✅ **Yes, users can request data deletion**
- 路徑 1：App 內「我的 → 刪除帳號」一鍵刪除（含集團 Pandora Core）
- 路徑 2：Email 至 `support@pandora-calendar.app`（待 ops 設定）
- 處理時限：72 小時內完成 + email 確認

### 4.3 Data deletion URL（Google 必填）

```
https://pandora-calendar.app/account-deletion
```

> 📌 此 URL 必須在不需登入下解釋刪除流程；待 ops 與 marketing-site 配合上線。

### 4.4 Independent security review

- v1.0：❌ No（尚未做 SOC 2 / ISO 27001 / OWASP MASVS 認證）
- P5+ Roadmap：考慮做 OWASP MASVS L1 自評（mobile app 標準）

### 4.5 Data minimization

我們的最小化承諾：

1. 月曆本機 DB **不存** email / phone / address / password_hash（依集團 ADR-007）
2. PII 透過 Pandora Core API 即時取得，不本地快取
3. Crash logs 不含 user UUID（純匿名）
4. 推播 token 與 device fingerprint 不關聯到 user 行為記錄

---

## Section 5 — Children & Sensitive Categories

### 5.1 Target age groups

- ✅ Adults（18+）為主
- ⚠️ Teens（13-17）：可使用，但需家長同意；經期追蹤對青春期女性有幫助
- ❌ Children（< 13）：不適用，註冊時擋

### 5.2 Health & wellness category compliance

- ✅ App 不提供醫療診斷或處方建議
- ✅ Description 與 App 內均明確聲明「不能取代醫師診斷」
- ✅ 經期 / BBT 屬 sensitive health data，已遵守 Google Play 健康類規範

### 5.3 Reproductive health considerations

- ✅ 不導向墮胎服務 / 避孕藥推銷 / 醫療轉介
- ✅ 不對使用者做生育計畫的價值判斷
- ✅ 跨地區 ToC 需考量 GDPR / HIPAA / 當地隱私法（v1.0 主台灣，國際版另議）

---

## Section 6 — 提交前 checklist

- [ ] Data deletion URL 已上線並可訪問
- [ ] Privacy Policy URL 已上線（與 Apple 同一份）
- [ ] Data Safety 表單與 Privacy Policy 內容 100% 一致
- [ ] App 實際蒐集行為與表單聲明一致（避免 audit 不符被下架）
- [ ] 所有第三方 SDK（Capacitor plugin）已 audit 其 data collection
- [ ] 客服 email `support@pandora-calendar.app` 可收信並有 SLA
- [ ] 法務最後 sign-off

---

## Section 7 — Privacy Policy 對應段落（精簡版）

> 完整 Privacy Policy 另寫，下列段落須與本 Data Safety 表單一致：

```
我們收集什麼資料

· 帳號資料：Email（用於登入 / 密碼重設 / 客服）
· 健康資料：妳記錄的經期、BBT、症狀、情緒、睡眠、疼痛資料（用戶選擇性記錄）
· 使用資料：妳在 App 內的點擊、停留、朵朵建議互動（用於優化體驗）
· 技術資料：崩潰記錄、效能數據（匿名）

我們不做的事

❌ 不賣妳的資料給任何第三方
❌ 不用妳的資料做廣告
❌ 不在 App 內放廣告
❌ 不追蹤妳到其他 App 或網站
❌ 不分享妳的資料給保險公司、醫療機構、研究單位（除非妳明確同意）

妳的權利

✅ 隨時匯出妳所有的資料
✅ 隨時刪除妳的帳號（72 小時內處理完畢）
✅ 隨時關閉個別資料蒐集（健康資料 / 使用資料分析）
```

---

> 📅 最後更新：2026-05-03
> 🛡️ Data sharing 永遠 No；Data deletion 永遠 Yes
> 📝 維護者：content-creator + 法務 review
