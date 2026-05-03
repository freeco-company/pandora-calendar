# Apple App Privacy Nutrition Label — 潘朵拉月曆 v1.0

> 📅 提交版本：2026-05-03
> 🎯 用途：App Store Connect → App Privacy 區設定（Apple 必填，2026 規定）
> 🛡️ 原則：寧可少報不要多報；Used for Tracking 一律 ❌；不分享給第三方
> 📚 參考：[Apple App Privacy Details](https://developer.apple.com/app-store/app-privacy-details/)

---

## TL;DR — Apple 三大隱私問題回答

| 問題 | 答案 |
|---|---|
| Do you or your third-party partners collect data from this app? | **Yes**（最少必要：登入信箱 / 健康記錄 / 使用統計 / 崩潰記錄） |
| Is data collected from this app linked to the user's identity? | **Yes**（Email / Health 與 user UUID 綁定） |
| Is data collected from this app used to track the user? | **No**（永不跨 App 追蹤、永不賣資料、永不做廣告 ID 配對） |

---

## 14 類 Data Type 分類表

> 對齊 Apple 14 類分類；每項標：**Linked to User**（是否與身份綁定）/ **Used for Tracking**（是否用於跨 App / 跨網站追蹤）/ **Purpose**

### 1. Contact Info（聯絡資訊）

| Data Type | Collected | Linked to User | Used for Tracking | Purpose |
|---|---|---|---|---|
| Email Address | ✅ Yes | ✅ Yes | ❌ No | App Functionality（帳號登入 / 客服） |
| Name | ❌ No | — | — | — |
| Phone Number | ❌ No | — | — | — |
| Physical Address | ❌ No | — | — | — |
| Other User Contact Info | ❌ No | — | — | — |

> 💡 **PII 邊界**：依 ADR-007 §2.3，潘朵拉月曆**本機 DB 不儲存 email**；Email 透過 Pandora Core Identity Service API 取得。Apple 規定看的是「資料是否被 collect」而非儲存位置 → 仍需勾 ✅。

### 2. Health & Fitness（健康與健身）

| Data Type | Collected | Linked to User | Used for Tracking | Purpose |
|---|---|---|---|---|
| Health | ✅ Yes | ✅ Yes | ❌ No | App Functionality（經期、症狀、情緒、睡眠記錄） |
| Fitness | ✅ Yes（P5+ HealthKit 接通後） | ✅ Yes | ❌ No | App Functionality（步數推算黃體期能量） |

> ⚠️ 經期資料屬 Apple 定義之 **Sensitive Health Data**，必須在 Privacy Manifest 註明且絕對不能用於 Tracking / 廣告。

### 3. Financial Info（金融資訊）

| Data Type | Collected | Linked to User | Used for Tracking | Purpose |
|---|---|---|---|---|
| Payment Info | ❌ No（透過 StoreKit / ECPay，App 本身不接觸卡號） | — | — | — |
| Credit Info | ❌ No | — | — | — |
| Other Financial Info | ❌ No | — | — | — |

### 4. Location（位置）

| Data Type | Collected | Linked to User | Used for Tracking | Purpose |
|---|---|---|---|---|
| Precise Location | ❌ No | — | — | — |
| Coarse Location | ❌ No | — | — | — |

### 5. Sensitive Info（敏感資訊）

| Data Type | Collected | Linked to User | Used for Tracking | Purpose |
|---|---|---|---|---|
| Sensitive Info | ❌ No | — | — | — |

> 📌 **註**：Apple Sensitive Info 不含經期 / BBT（這些屬 Health 類別）；本欄指種族、性取向、政治、宗教、生物特徵等，本 App 不收集。

### 6. Contacts（通訊錄）

| Data Type | Collected | Linked to User | Used for Tracking | Purpose |
|---|---|---|---|---|
| Contacts | ❌ No | — | — | — |

### 7. User Content（使用者內容）

| Data Type | Collected | Linked to User | Used for Tracking | Purpose |
|---|---|---|---|---|
| Emails or Text Messages | ❌ No | — | — | — |
| Photos or Videos | ❌ No（P5+ 進度照功能上線後再申報） | — | — | — |
| Audio Data | ❌ No | — | — | — |
| Gameplay Content | ❌ No | — | — | — |
| Customer Support | ✅ Yes | ✅ Yes | ❌ No | App Functionality（朵朵建議回饋、支援對話） |
| Other User Content | ❌ No | — | — | — |

### 8. Browsing History（瀏覽紀錄）

| Data Type | Collected | Linked to User | Used for Tracking | Purpose |
|---|---|---|---|---|
| Browsing History | ❌ No | — | — | — |

### 9. Search History（搜尋紀錄）

| Data Type | Collected | Linked to User | Used for Tracking | Purpose |
|---|---|---|---|---|
| Search History | ❌ No | — | — | — |

### 10. Identifiers（識別碼）

| Data Type | Collected | Linked to User | Used for Tracking | Purpose |
|---|---|---|---|---|
| User ID | ✅ Yes | ✅ Yes | ❌ No | App Functionality（集團 Pandora Core UUID，跨潘朵拉系列共用） |
| Device ID | ❌ No（不收 IDFA / IDFV） | — | — | — |

### 11. Purchases（購買紀錄）

| Data Type | Collected | Linked to User | Used for Tracking | Purpose |
|---|---|---|---|---|
| Purchase History | ✅ Yes | ✅ Yes | ❌ No | App Functionality（訂閱狀態、entitlement、Restore Purchase） |

### 12. Usage Data（使用資料）

| Data Type | Collected | Linked to User | Used for Tracking | Purpose |
|---|---|---|---|---|
| Product Interaction | ✅ Yes | ✅ Yes | ❌ No | Analytics（朵朵建議命中率 / 留存）+ App Functionality（成就 / XP 系統） |
| Advertising Data | ❌ No | — | — | — |
| Other Usage Data | ❌ No | — | — | — |

### 13. Diagnostics（診斷資料）

| Data Type | Collected | Linked to User | Used for Tracking | Purpose |
|---|---|---|---|---|
| Crash Data | ✅ Yes | ❌ No（匿名） | ❌ No | App Functionality（修 bug） |
| Performance Data | ✅ Yes | ❌ No（匿名） | ❌ No | App Functionality（優化效能） |
| Other Diagnostic Data | ❌ No | — | — | — |

### 14. Other Data（其他）

| Data Type | Collected | Linked to User | Used for Tracking | Purpose |
|---|---|---|---|---|
| Other Data Types | ❌ No | — | — | — |

---

## Apple Submission JSON 範本

> 本 JSON 為 Apple App Store Connect privacy questionnaire 的對應結構（Apple 沒有官方公開 schema，下列為依現行表單推導之 internal 紀錄格式，非 API spec）。

```json
{
  "app": {
    "bundle_id": "com.jerosse.pandora.calendar",
    "version": "1.0.0",
    "submitted_at": "2026-05-03"
  },
  "tracking": {
    "uses_tracking": false,
    "third_party_partners_track": false
  },
  "data_collected": [
    {
      "type": "contact_info.email_address",
      "linked_to_user": true,
      "used_for_tracking": false,
      "purposes": ["app_functionality"]
    },
    {
      "type": "health_and_fitness.health",
      "linked_to_user": true,
      "used_for_tracking": false,
      "purposes": ["app_functionality"],
      "notes": "Cycle / period / symptoms / mood / BBT / sleep / pain. Sensitive health data. Never sold, never used for ads."
    },
    {
      "type": "health_and_fitness.fitness",
      "linked_to_user": true,
      "used_for_tracking": false,
      "purposes": ["app_functionality"],
      "notes": "HealthKit step count read after P5; user explicit consent required."
    },
    {
      "type": "user_content.customer_support",
      "linked_to_user": true,
      "used_for_tracking": false,
      "purposes": ["app_functionality"]
    },
    {
      "type": "identifiers.user_id",
      "linked_to_user": true,
      "used_for_tracking": false,
      "purposes": ["app_functionality"],
      "notes": "Pandora Core UUID, no PII embedded."
    },
    {
      "type": "purchases.purchase_history",
      "linked_to_user": true,
      "used_for_tracking": false,
      "purposes": ["app_functionality"]
    },
    {
      "type": "usage_data.product_interaction",
      "linked_to_user": true,
      "used_for_tracking": false,
      "purposes": ["app_functionality", "analytics"]
    },
    {
      "type": "diagnostics.crash_data",
      "linked_to_user": false,
      "used_for_tracking": false,
      "purposes": ["app_functionality"]
    },
    {
      "type": "diagnostics.performance_data",
      "linked_to_user": false,
      "used_for_tracking": false,
      "purposes": ["app_functionality"]
    }
  ],
  "data_not_collected": [
    "contact_info.name",
    "contact_info.phone_number",
    "contact_info.physical_address",
    "financial_info.payment_info",
    "location.precise",
    "location.coarse",
    "sensitive_info",
    "contacts",
    "user_content.photos_or_videos",
    "browsing_history",
    "search_history",
    "identifiers.device_id",
    "usage_data.advertising_data"
  ]
}
```

---

## App Store Connect 填表時逐欄參考

### Q1: Do you or your third-party partners collect data from this app?
→ **Yes, we collect data**

### Q2: 勾選類別（依上表 ✅ 項）
- ☑ Contact Info → Email Address
- ☑ Health & Fitness → Health, Fitness
- ☑ User Content → Customer Support
- ☑ Identifiers → User ID
- ☑ Purchases → Purchase History
- ☑ Usage Data → Product Interaction
- ☑ Diagnostics → Crash Data, Performance Data

### Q3: 每項依序填寫
- Linked to user identity？依表
- Used for tracking？**全部 No**
- Purpose？依表（多選 OK）

### Q4: Privacy Policy URL
- `https://pandora-calendar.app/privacy`（待 ops 設定）

---

## Privacy Manifest（PrivacyInfo.xcprivacy）對應

iOS 17+ 規定每個 third-party SDK 與主 App 都要附 PrivacyInfo.xcprivacy。月曆需在 Capacitor iOS bundle 加入：

```xml
<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE plist PUBLIC "-//Apple//DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/PropertyList-1.0.dtd">
<plist version="1.0">
<dict>
    <key>NSPrivacyTracking</key>
    <false/>
    <key>NSPrivacyTrackingDomains</key>
    <array/>
    <key>NSPrivacyCollectedDataTypes</key>
    <array>
        <dict>
            <key>NSPrivacyCollectedDataType</key>
            <string>NSPrivacyCollectedDataTypeEmailAddress</string>
            <key>NSPrivacyCollectedDataTypeLinked</key>
            <true/>
            <key>NSPrivacyCollectedDataTypeTracking</key>
            <false/>
            <key>NSPrivacyCollectedDataTypePurposes</key>
            <array>
                <string>NSPrivacyCollectedDataTypePurposeAppFunctionality</string>
            </array>
        </dict>
        <dict>
            <key>NSPrivacyCollectedDataType</key>
            <string>NSPrivacyCollectedDataTypeHealth</string>
            <key>NSPrivacyCollectedDataTypeLinked</key>
            <true/>
            <key>NSPrivacyCollectedDataTypeTracking</key>
            <false/>
            <key>NSPrivacyCollectedDataTypePurposes</key>
            <array>
                <string>NSPrivacyCollectedDataTypePurposeAppFunctionality</string>
            </array>
        </dict>
        <dict>
            <key>NSPrivacyCollectedDataType</key>
            <string>NSPrivacyCollectedDataTypeUserID</string>
            <key>NSPrivacyCollectedDataTypeLinked</key>
            <true/>
            <key>NSPrivacyCollectedDataTypeTracking</key>
            <false/>
            <key>NSPrivacyCollectedDataTypePurposes</key>
            <array>
                <string>NSPrivacyCollectedDataTypePurposeAppFunctionality</string>
            </array>
        </dict>
        <dict>
            <key>NSPrivacyCollectedDataType</key>
            <string>NSPrivacyCollectedDataTypePurchaseHistory</string>
            <key>NSPrivacyCollectedDataTypeLinked</key>
            <true/>
            <key>NSPrivacyCollectedDataTypeTracking</key>
            <false/>
            <key>NSPrivacyCollectedDataTypePurposes</key>
            <array>
                <string>NSPrivacyCollectedDataTypePurposeAppFunctionality</string>
            </array>
        </dict>
        <dict>
            <key>NSPrivacyCollectedDataType</key>
            <string>NSPrivacyCollectedDataTypeProductInteraction</string>
            <key>NSPrivacyCollectedDataTypeLinked</key>
            <true/>
            <key>NSPrivacyCollectedDataTypeTracking</key>
            <false/>
            <key>NSPrivacyCollectedDataTypePurposes</key>
            <array>
                <string>NSPrivacyCollectedDataTypePurposeAppFunctionality</string>
                <string>NSPrivacyCollectedDataTypePurposeAnalytics</string>
            </array>
        </dict>
        <dict>
            <key>NSPrivacyCollectedDataType</key>
            <string>NSPrivacyCollectedDataTypeCrashData</string>
            <key>NSPrivacyCollectedDataTypeLinked</key>
            <false/>
            <key>NSPrivacyCollectedDataTypeTracking</key>
            <false/>
            <key>NSPrivacyCollectedDataTypePurposes</key>
            <array>
                <string>NSPrivacyCollectedDataTypePurposeAppFunctionality</string>
            </array>
        </dict>
        <dict>
            <key>NSPrivacyCollectedDataType</key>
            <string>NSPrivacyCollectedDataTypePerformanceData</string>
            <key>NSPrivacyCollectedDataTypeLinked</key>
            <false/>
            <key>NSPrivacyCollectedDataTypeTracking</key>
            <false/>
            <key>NSPrivacyCollectedDataTypePurposes</key>
            <array>
                <string>NSPrivacyCollectedDataTypePurposeAppFunctionality</string>
            </array>
        </dict>
    </array>
    <key>NSPrivacyAccessedAPITypes</key>
    <array>
        <dict>
            <key>NSPrivacyAccessedAPIType</key>
            <string>NSPrivacyAccessedAPICategoryUserDefaults</string>
            <key>NSPrivacyAccessedAPITypeReasons</key>
            <array>
                <string>CA92.1</string>
            </array>
        </dict>
    </array>
</dict>
</plist>
```

---

## 提交前 checklist

- [ ] Privacy Policy URL 已生效並可訪問（未 404）
- [ ] Privacy Policy 內容與本表 100% 一致（不能 App Store 寫不收，policy 寫收）
- [ ] PrivacyInfo.xcprivacy 已加入 iOS bundle 並通過 Xcode 驗證
- [ ] 所有第三方 SDK（Capacitor plugin）的 Privacy Manifest 已 audit
- [ ] App 內 IAP 不蒐集卡號（StoreKit 處理）
- [ ] 法務最後 sign-off

---

> 📅 最後更新：2026-05-03
> 🛡️ 哲學：寧可少報不要多報；Tracking 永遠 No
> 📝 維護者：content-creator + 法務 review
