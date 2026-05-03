<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## 朵朵 LLM 配置（pluggable env-driven）

朵朵 check-in 對白支援接 LLM（OpenAI / Claude），無 key 時自動 fallback 到 `DodoDialogLibrary`（100+ 句變體），不阻塞上架。

### 切換 provider

```bash
# 預設（無成本，用 library 變體）
LLM_PROVIDER=null

# OpenAI gpt-4o-mini（最便宜，~$0.0002/次對白）
LLM_PROVIDER=openai
OPENAI_API_KEY=sk-...
OPENAI_MODEL=gpt-4o-mini

# Claude haiku-4-5
LLM_PROVIDER=claude
ANTHROPIC_API_KEY=ant-...
CLAUDE_MODEL=claude-haiku-4-5
```

### 成本控制

每用戶每日 USD cap（超過自動 fallback library）：

```bash
LLM_USER_DAILY_CAP=0.05         # free 用戶 ~250 次/日
LLM_PREMIUM_USER_DAILY_CAP=0.20 # premium 用戶 ~1000 次/日
```

成本估算用 `config/llm.php` 的 `price_per_1k_input/output_usd` 做粗估（中文 token 用 char/2 近似），累計到 `Cache::put('llm:cost:{userId}:{date}', ...)`，每日 endOfDay 自動歸零。

### 合規防護

LLM output 走兩層紅線檢查（任一命中即 fallback library）：

1. **本地紅線詞**（`config/dodo-llm-redlines.php`，UTF-8 byte sequence 編碼避免 source code 出現 raw 違規詞）—— 涵蓋食安 / 健食法療效詞 + 朵朵 tone & voice 禁用稱呼
2. **集團 sanitizer**（`Pandora\Shared\Compliance\LegalContentSanitizer::riskReport`，55+ 詞）

System prompt 存在 `resources/prompts/dodo-system-prompt.txt`（外部 .txt，避免 ComplianceContentGuard 誤殺），啟動時也跑一次 sanitizer self-check。

### 隱私

- prompt 不送 user_id raw / email / name；OpenAI `user` 欄位送 `sha256(user_id + APP_KEY)` 前 32 字
- 最近 5 個 checkin context 只送 `mood / phase / cycle_day`（無原文）

### 監控

DodoController POST `/api/v1/dodo/checkin` 回傳 `data.dodo_source`（`'llm'` 或 `'library'`），前端 / Sentry breadcrumb 可記錄分布。

---

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework.

In addition, [Laracasts](https://laracasts.com) contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

You can also watch bite-sized lessons with real-world projects on [Laravel Learn](https://laravel.com/learn), where you will be guided through building a Laravel application from scratch while learning PHP fundamentals.

## Agentic Development

Laravel's predictable structure and conventions make it ideal for AI coding agents like Claude Code, Cursor, and GitHub Copilot. Install [Laravel Boost](https://laravel.com/docs/ai) to supercharge your AI workflow:

```bash
composer require laravel/boost --dev

php artisan boost:install
```

Boost provides your agent 15+ tools and skills that help agents build Laravel applications while following best practices.

## Sentry 觀測性（wave 5）

上架後第一週看到 issue 在哪 — 集中以 `App\Support\Sentry\SentryHelper` 統一介面：

- `SentryHelper::captureException($e, $module, $context)` — exception + module tag + scrubbed context
- `SentryHelper::captureMessage($msg, $level, $module, $context)` — 給「預期內但異常」（webhook HMAC fail / unhandled event_type / 5xx upstream）
- `SentryHelper::addBreadcrumb($category, $msg, $data)` — 串軌跡（4xx / LLM fail）

### 散布的 hot path

| Module tag | 位置 | 觸發 |
|---|---|---|
| `iap` | `IapVerifier::verifyApple` / `verifyGoogle` | 商店 verify throw（status / endpoint exhaust / mismatch） |
| `iap` | `AppleJwsVerifier::verifyAndDecode` | JWS chain / alg / signature 驗證失敗 |
| `iap` | `GooglePlayAccessTokenProvider::fetchAndCache` | service-account JWT exchange 失敗 |
| `oauth` | `IdentityClient::resolveFromJwt` | JWT verifier throw / sub claim 缺失（`captureMessage`） |
| `oauth` | `AuthController::forward` | PC unreachable（exception）+ PC 5xx（`captureMessage`） |
| `llm` | `OpenAIProvider` / `ClaudeProvider` | 5xx upstream + parse error → `captureMessage`；exception throw → `captureException`；4xx → `addBreadcrumb` only（預期 fallback） |
| `webhook.identity` | `VerifyIdentityWebhookSignature` middleware | missing headers / timestamp out of window / HMAC mismatch |
| `webhook.identity` | `IdentityWebhookController` | payload schema invalid / unknown event type / sync exception |
| `webhook.gamification` | `VerifyGamificationWebhookSignature` middleware | header / timestamp / HMAC / payload schema |
| `webhook.gamification` | `GamificationWebhookController` | unhandled event_type；unknown user → breadcrumb only |
| `subscription` | `SubscriptionController::pause` | DB write 失敗 |
| `dodo` | `DodoController::checkin` | gamification publish dispatch 失敗（不擋用戶寫入） |

### 紅線（強制）

- **Health 資料禁送 Sentry**：URL pattern → 整個 event drop（`HealthDataScrubber::scrub`）；context key 命中 cycle / symptom / mood / bbt / pms / pregnancy / temperature / weight / height → `[Filtered]`；URL-like value 命中 health-route → `[health-route]`
- **PII 禁送**：email / phone / address / password / token 一律 redact
- **OK 送**：`identity_uuid`（uuid 非 PII）、subscription / iap / oauth / llm 失敗 metadata、product_id / platform / status code

### Frontend 對應

- `frontend/src/lib/sentry.ts` — Sentry init + beforeSend / beforeBreadcrumb scrubber（與 backend 紅線一致）
- `frontend/src/api.ts` — axios response interceptor：5xx `captureMessage`、4xx 非 401 `addBreadcrumb`、network error `captureMessage`；URL 命中 health-route 一律 `[health-route]`
- `frontend/src/router.ts` — `afterEach` navigation breadcrumb；health route（含 `/log` `/dodo`）redact

### 加新 capture 點時

1. 在新 catch / fail branch import `App\Support\Sentry\SentryHelper`
2. 選對 module tag（沿用上表類別，新增前先想是否真的不能歸入既有）
3. context array **絕對不放** request body / response body / 任何 health 欄位 — 即使你覺得 sanitizer 會擋，也不要試。送 `*_hash` / `*_uuid` / status / product_id 即可
4. 加完跑 `php artisan test --filter=SentryHelperTest` 確保 sanitizer 還是擋得住新 key

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
