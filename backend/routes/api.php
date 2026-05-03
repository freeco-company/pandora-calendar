<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BodyRhythmController;
use App\Http\Controllers\Api\V1\CommerceController;
use App\Http\Controllers\Api\V1\CycleController;
use App\Http\Controllers\Api\V1\DodoController;
use App\Http\Controllers\Api\V1\HealthSampleController;
use App\Http\Controllers\Api\V1\InsightController;
use App\Http\Controllers\Api\V1\PregnancyController;
use App\Http\Controllers\Api\V1\SubscriptionController;
use App\Http\Controllers\Api\V1\SymptomController;
use App\Http\Controllers\Api\V1\WeekReportController;
use App\Http\Controllers\Webhooks\AppleAsnController;
use App\Http\Controllers\Webhooks\EcpayNotifyController;
use App\Http\Controllers\Webhooks\GoogleRtdnController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/health', fn () => response()->json(['status' => 'ok', 'app' => 'pandora-calendar']));

// Webhook endpoints（不走 sanctum）
Route::post('/webhooks/apple-asn', [AppleAsnController::class, 'handle']);
Route::post('/webhooks/google-rtdn', [GoogleRtdnController::class, 'handle']);
Route::post('/webhooks/ecpay-notify', [EcpayNotifyController::class, 'handle']);

// P5.3 ADR-009 — py-service → calendar gamification webhook（HMAC + nonce 由 middleware 驗）
Route::post('/v1/internal/webhooks/gamification', [\App\Http\Controllers\Api\V1\Internal\GamificationWebhookController::class, 'handle'])
    ->middleware('gamification.webhook');

// P1 ADR-007 — PC → calendar identity webhook（user.upserted/suspended/merged）
Route::post('/v1/internal/webhooks/identity', \App\Http\Controllers\Api\V1\Internal\IdentityWebhookController::class)
    ->middleware('identity.webhook');

// P2-9 Partner share — public anonymous view by token（無 auth）
Route::get('/v1/partner/{token}', [\App\Http\Controllers\Api\V1\PartnerShareController::class, 'publicView']);

// FAQ — 公開（無 auth），cache 1h
Route::get('/v1/faq', [\App\Http\Controllers\Api\V1\FaqController::class, 'index']);

// 取消挽留文案 — 公開（無 auth，前端取消頁面進去就 fetch；不含 PII）
Route::get('/v1/subscription/churn-intercept', [\App\Http\Controllers\Api\V1\SubscriptionController::class, 'churnIntercept']);

// Signed export download — 嚴格驗 user_id + signature
Route::get('/v1/exports/{userId}/{filename}', [\App\Http\Controllers\Api\V1\ExportController::class, 'download'])
    ->middleware('auth.platform')
    ->name('export.download');

// P1 ADR-007 — auth proxy 到 Pandora Core（不存 password / refresh token 在本機）
// throttle:5,1 = 5 req/min/IP，PC 自己也擋一次但 calendar 站前面也擋（defense-in-depth）
Route::prefix('v1/auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:5,1');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');
    Route::post('/refresh', [AuthController::class, 'refresh'])->middleware('throttle:30,1');
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/oauth/{provider}/url', [AuthController::class, 'oauthUrl'])->middleware('throttle:30,1');
});

Route::middleware(['auth.platform'])->prefix('v1')->group(function () {
    Route::get('/me', function (Request $r) {
        $u = $r->user();

        // P5.2 ADR-009：每天首次 hit /me → calendar.app_opened
        // idempotency key 包含日期 → 同 user 同天送一次（py-service catalog daily_cap=3 還會再過濾）
        $today = now()->toDateString();
        $publisher = app(\App\Services\Gamification\GamificationPublisher::class);
        $publisher->publish(
            $u,
            \App\Services\Gamification\CalendarEventCatalog::APP_OPENED,
            ['date' => $today],
            \App\Services\Gamification\IdempotencyKey::make(
                \App\Services\Gamification\CalendarEventCatalog::APP_OPENED,
                $u->id, 0, $today,
            ),
        );

        return response()->json(['data' => [
            'id' => $u->id,
            'name' => $u->name,
            // P1 ADR-007: PC mirror 寫的是 display_name，name 是 sanctum demo 殘留
            'display_name' => $u->display_name ?? $u->name,
            'avatar_url' => $u->avatar_url,
            'subscription_tier' => $u->subscription_tier,
            'identity_uuid' => $u->identity_uuid,
            'linked_to_mother' => (bool) $u->mother_customer_id,
            'mother_total_orders' => (int) ($u->mother_total_orders ?? 0),
            'total_xp' => (int) ($u->total_xp ?? 0),
            'level' => (int) ($u->level ?? 1),
            'outfit_state' => $u->outfit_state,
            'pet_species' => $u->pet_species,
            'pet_nickname' => $u->pet_nickname,
        ]]);
    });

    // Sentry 接通驗證 endpoint — 留著直到 prod DSN 設定 + 看到事件後再移除
    // auth required（避免被掃 endpoint 灌爆 quota）+ Premium not required（任何登入用戶可觸發）
    Route::get('/sentry-test', function () {
        throw new \RuntimeException('Sentry connectivity probe (pandora-calendar backend)');
    });

    Route::get('/cycles', [CycleController::class, 'index']);
    Route::post('/cycles', [CycleController::class, 'store']);
    Route::patch('/cycles/{cycle}', [CycleController::class, 'update']);
    Route::delete('/cycles/{cycle}', [CycleController::class, 'destroy']);

    Route::get('/symptoms', [SymptomController::class, 'index']);
    Route::post('/symptoms', [SymptomController::class, 'store']);
    Route::get('/symptom-tags', [\App\Http\Controllers\Api\V1\SymptomTagsController::class, 'index']);

    // P0+P1 Onboarding
    Route::post('/onboarding/complete', [\App\Http\Controllers\Api\V1\OnboardingController::class, 'complete']);
    Route::get('/onboarding/status', [\App\Http\Controllers\Api\V1\OnboardingController::class, 'status']);

    // P1 Daily insights（衛教文章）
    Route::get('/insights/today', [\App\Http\Controllers\Api\V1\DailyInsightController::class, 'today']);

    // P1 BBT 雙相 shift 偵測
    Route::get('/bbt/biphasic', [\App\Http\Controllers\Api\V1\BbtController::class, 'biphasic']);

    Route::post('/dodo/checkin', [DodoController::class, 'checkin']);
    Route::get('/dodo/recent', [DodoController::class, 'recent']);

    Route::get('/body-rhythm/me', [BodyRhythmController::class, 'me']);

    Route::get('/subscription/me', [SubscriptionController::class, 'me']);
    Route::get('/subscription/products', [SubscriptionController::class, 'products']);
    Route::post('/subscription/verify-apple', [SubscriptionController::class, 'verifyApple']);
    Route::post('/subscription/verify-google', [SubscriptionController::class, 'verifyGoogle']);
    Route::post('/subscription/ecpay-checkout', [SubscriptionController::class, 'ecpayCheckout']);

    Route::post('/health-samples/import', [HealthSampleController::class, 'importBatch'])->middleware('throttle:30,1');
    Route::post('/health-samples/sync', [HealthSampleController::class, 'import'])->middleware('throttle:30,1');
    Route::get('/health-samples/reflection/today', [HealthSampleController::class, 'reflectionToday']);

    // Protocol insight — 朵朵主動報「我發現 X 對妳 work」
    Route::get('/protocol-insights/active', [\App\Http\Controllers\Api\V1\ProtocolInsightController::class, 'active']);
    Route::post('/protocol-insights/{key}/dismiss', [\App\Http\Controllers\Api\V1\ProtocolInsightController::class, 'dismiss'])
        ->where('key', '[A-Za-z0-9:_\-]+');

    Route::get('/pregnancy/current', [PregnancyController::class, 'current']);
    Route::post('/pregnancy', [PregnancyController::class, 'start']);
    Route::post('/pregnancy/start', [PregnancyController::class, 'start']);
    Route::patch('/pregnancy/end', [PregnancyController::class, 'end']);
    Route::patch('/pregnancy/{pregnancy}/end', [PregnancyController::class, 'end']);
    Route::get('/pregnancy/week/{week}', [PregnancyController::class, 'week'])->where('week', '[0-9]+');

    Route::get('/insight/pms', [InsightController::class, 'pms']);

    Route::get('/week-report/latest', [WeekReportController::class, 'latest']);
    Route::post('/week-report/generate', [WeekReportController::class, 'generate']);

    // 婕樂纖商品連結（P5+，gate 嚴守）
    // legacy endpoint — 保留向後相容；新前端走 /v1/ecommerce/* 兩段式 (eligibility / recommendations)
    Route::get('/commerce/product-links', [CommerceController::class, 'productLinks']);

    // P5 ecommerce 深層 endpoints（紅線：只在「我的 → 婕樂纖會員」呼叫）
    Route::get('/ecommerce/eligibility', [\App\Http\Controllers\Api\V1\EcommerceController::class, 'eligibility']);
    Route::get('/ecommerce/recommendations', [\App\Http\Controllers\Api\V1\EcommerceController::class, 'recommendations'])
        ->middleware('ensure.mother_customer');

    // P5.3 / P5.4 ADR-009：朵朵 / pet / pending events
    Route::get('/me/gamification/pending', [\App\Http\Controllers\Api\V1\MeGamificationController::class, 'pending']);
    Route::get('/me/dodo', [\App\Http\Controllers\Api\V1\MeGamificationController::class, 'dodo']);
    // /me/pet 改由 PetController 處理（superset response）

    // App Store / GDPR：In-app account data deletion
    Route::delete('/me', [\App\Http\Controllers\Api\V1\AccountController::class, 'destroy']);

    // P0-1 Pet：onboarding picker + persistent
    Route::get('/me/pet', [\App\Http\Controllers\Api\V1\PetController::class, 'show']);
    Route::patch('/me/pet', [\App\Http\Controllers\Api\V1\PetController::class, 'update']);

    // P0-4 Journey dashboard
    Route::get('/me/journey', [\App\Http\Controllers\Api\V1\JourneyController::class, 'show']);

    // P3 成就 + outfits
    Route::get('/me/achievements', [\App\Http\Controllers\Api\V1\AchievementsController::class, 'index']);
    Route::get('/me/outfits', [\App\Http\Controllers\Api\V1\OutfitsController::class, 'index']);
    Route::post('/me/outfits/equip', [\App\Http\Controllers\Api\V1\OutfitsController::class, 'equip']);

    // P1-5 Dodo chat history
    Route::get('/me/dodo/history', [\App\Http\Controllers\Api\V1\DodoChatHistoryController::class, 'index']);

    // P1-6 Daily reminder（phase-based hard-coded tip）
    Route::get('/me/daily-reminder', [\App\Http\Controllers\Api\V1\DailyReminderController::class, 'show']);

    // P2-8 BBT（基礎體溫）
    Route::get('/me/bbt', [\App\Http\Controllers\Api\V1\BbtController::class, 'index']);
    Route::post('/me/bbt', [\App\Http\Controllers\Api\V1\BbtController::class, 'store']);
    Route::delete('/me/bbt/{id}', [\App\Http\Controllers\Api\V1\BbtController::class, 'destroy']);

    // P2-9 Partner share（自己 manage）
    Route::get('/me/partner-share', [\App\Http\Controllers\Api\V1\PartnerShareController::class, 'show']);
    Route::post('/me/partner-share', [\App\Http\Controllers\Api\V1\PartnerShareController::class, 'enable']);
    Route::delete('/me/partner-share', [\App\Http\Controllers\Api\V1\PartnerShareController::class, 'disable']);

    // Push subscription（web-push + native iOS/Android）
    Route::post('/me/push/subscribe', [\App\Http\Controllers\Api\V1\PushSubscriptionController::class, 'subscribe']);
    Route::post('/me/push/unsubscribe', [\App\Http\Controllers\Api\V1\PushSubscriptionController::class, 'unsubscribe']);
    Route::get('/me/push/subscriptions', [\App\Http\Controllers\Api\V1\PushSubscriptionController::class, 'index']);
    Route::post('/me/push/test', [\App\Http\Controllers\Api\V1\PushSubscriptionController::class, 'test']);

    // Wave 13 — 深度遊戲化（economy / bond / rank / skill / dex / story / event / solar）
    Route::get('/economy/balance', [\App\Http\Controllers\Api\V1\EconomyController::class, 'balance']);
    Route::get('/economy/history', [\App\Http\Controllers\Api\V1\EconomyController::class, 'history']);

    Route::get('/me/rank', [\App\Http\Controllers\Api\V1\GameDepthController::class, 'rank']);

    Route::get('/me/skill-path', [\App\Http\Controllers\Api\V1\GameDepthController::class, 'skillPath']);
    Route::post('/me/skill-path', [\App\Http\Controllers\Api\V1\GameDepthController::class, 'chooseSkillPath']);
    Route::get('/me/skill-path/quests', [\App\Http\Controllers\Api\V1\GameDepthController::class, 'skillPathQuests']);

    Route::get('/me/body-dex', [\App\Http\Controllers\Api\V1\GameDepthController::class, 'bodyDex']);

    Route::get('/me/stories/chapters', [\App\Http\Controllers\Api\V1\GameDepthController::class, 'storyChapters']);
    Route::post('/me/stories/{chapter}/unlock', [\App\Http\Controllers\Api\V1\GameDepthController::class, 'unlockStoryChapter'])->whereNumber('chapter');
    Route::post('/me/stories/{chapter}/read', [\App\Http\Controllers\Api\V1\GameDepthController::class, 'readStoryChapter'])->whereNumber('chapter');

    Route::get('/me/random-event/today', [\App\Http\Controllers\Api\V1\GameDepthController::class, 'todayRandomEvent']);
    Route::post('/me/random-event/{id}/claim', [\App\Http\Controllers\Api\V1\GameDepthController::class, 'claimRandomEvent'])->whereNumber('id');

    Route::get('/solar-term/current', [\App\Http\Controllers\Api\V1\GameDepthController::class, 'currentSolarTerm']);
    Route::post('/solar-term/{term}/participate', [\App\Http\Controllers\Api\V1\GameDepthController::class, 'participateSolarTerm']);

    Route::get('/me/pet/bond', [\App\Http\Controllers\Api\V1\GameDepthController::class, 'petBond']);
    Route::post('/me/pet/feed', [\App\Http\Controllers\Api\V1\GameDepthController::class, 'feedPet'])->middleware('throttle:30,1');
    Route::post('/me/pet/pet-head', [\App\Http\Controllers\Api\V1\GameDepthController::class, 'petHead'])->middleware('throttle:60,1');

    // 資料匯出（Premium）
    Route::post('/export/pdf', [\App\Http\Controllers\Api\V1\ExportController::class, 'pdf']);
    Route::post('/export/csv', [\App\Http\Controllers\Api\V1\ExportController::class, 'csv']);

    // 年度回顧（Premium）
    Route::get('/year-review/{year}', [\App\Http\Controllers\Api\V1\YearReviewController::class, 'show'])
        ->whereNumber('year');

    // In-app feedback
    Route::post('/feedback', [\App\Http\Controllers\Api\V1\FeedbackController::class, 'store']);

    // 醫療安全決策樹
    Route::get('/medical-safety/evaluate', [\App\Http\Controllers\Api\V1\MedicalSafetyController::class, 'evaluate']);

    // P5+ 匿名社群問板 — gate（連用 14 天 + 5 筆紀錄才能 post）+ moderation（auto-block 紅線）
    Route::get('/community/posts', [\App\Http\Controllers\Api\V1\CommunityController::class, 'index']);
    Route::get('/community/posts/{id}', [\App\Http\Controllers\Api\V1\CommunityController::class, 'show'])->whereNumber('id');
    Route::post('/community/posts', [\App\Http\Controllers\Api\V1\CommunityController::class, 'store'])->middleware('throttle:10,1');
    Route::delete('/community/posts/{id}', [\App\Http\Controllers\Api\V1\CommunityController::class, 'destroy'])->whereNumber('id');
    Route::post('/community/posts/{id}/replies', [\App\Http\Controllers\Api\V1\CommunityController::class, 'reply'])
        ->whereNumber('id')->middleware('throttle:30,1');
    Route::post('/community/posts/{id}/like', [\App\Http\Controllers\Api\V1\CommunityController::class, 'likePost'])->whereNumber('id');
    Route::post('/community/replies/{id}/like', [\App\Http\Controllers\Api\V1\CommunityController::class, 'likeReply'])->whereNumber('id');
    Route::post('/community/reports', [\App\Http\Controllers\Api\V1\CommunityController::class, 'report']);

    // 訂閱挽留 / 暫停
    Route::post('/subscription/pause', [\App\Http\Controllers\Api\V1\SubscriptionController::class, 'pause']);
    Route::post('/subscription/cancel-feedback', [\App\Http\Controllers\Api\V1\SubscriptionController::class, 'cancelFeedback']);

    // Daily Action Engine（free，protocol 完整 view 才 Premium）
    Route::get('/actions/today', [\App\Http\Controllers\Api\V1\DailyActionController::class, 'today']);
    Route::post('/actions/{recId}/complete', [\App\Http\Controllers\Api\V1\DailyActionController::class, 'complete'])
        ->whereNumber('recId');
    Route::post('/actions/{recId}/feedback', [\App\Http\Controllers\Api\V1\DailyActionController::class, 'feedback'])
        ->whereNumber('recId');
    Route::get('/actions/history', [\App\Http\Controllers\Api\V1\DailyActionController::class, 'history']);
    Route::get('/actions/protocol', [\App\Http\Controllers\Api\V1\DailyActionController::class, 'protocol']);

    // P4 含金量 Q&A — 朵朵 LLM + RAG 衛教（free 3/day · Premium 無限）
    Route::post('/qna/ask', [\App\Http\Controllers\Api\V1\QnaController::class, 'ask'])
        ->middleware('throttle:30,1');
    Route::get('/qna/history', [\App\Http\Controllers\Api\V1\QnaController::class, 'history']);
    Route::delete('/qna/{id}', [\App\Http\Controllers\Api\V1\QnaController::class, 'destroy'])
        ->whereNumber('id');

    // Cycle pattern report（每 cycle 結束 04:00 schedule 自動生）
    Route::get('/pattern-report/latest', [\App\Http\Controllers\Api\V1\PatternReportController::class, 'latest']);
    Route::get('/pattern-report/list', [\App\Http\Controllers\Api\V1\PatternReportController::class, 'index']);

    // P5 進度照 photo journal — 隱私核心：metadata-only by default，雲端 sync = Premium opt-in
    Route::post('/photo-journal', [\App\Http\Controllers\Api\V1\PhotoJournalController::class, 'store']);
    Route::get('/photo-journal/list', [\App\Http\Controllers\Api\V1\PhotoJournalController::class, 'list']);
    Route::get('/photo-journal/{id}', [\App\Http\Controllers\Api\V1\PhotoJournalController::class, 'show'])->whereNumber('id');
    Route::post('/photo-journal/{id}/upload-cloud', [\App\Http\Controllers\Api\V1\PhotoJournalController::class, 'uploadCloud'])
        ->whereNumber('id')->middleware('throttle:30,1');
    Route::delete('/photo-journal/{id}', [\App\Http\Controllers\Api\V1\PhotoJournalController::class, 'destroy'])->whereNumber('id');
    Route::delete('/photo-journal/{id}/cloud-only', [\App\Http\Controllers\Api\V1\PhotoJournalController::class, 'destroyCloudOnly'])->whereNumber('id');

    // signed cloud stream（auth required + signature 雙重）
    Route::get('/photo-journal/cloud/{key}', [\App\Http\Controllers\Api\V1\PhotoJournalController::class, 'cloudStream'])
        ->name('photo-journal.cloud-stream');
});

// Phase 0 demo helper（dev / testing only）
Route::post('/demo/login', function (Request $request) {
    abort_unless(app()->environment('local', 'testing'), 404);
    $request->validate(['email' => ['required', 'email']]);

    $user = \App\Models\User::where('email', $request->input('email'))->first();
    abort_if(! $user, 404, 'demo user not found');

    $token = $user->createToken('demo')->plainTextToken;

    return response()->json([
        'token' => $token,
        'user' => [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'identity_uuid' => $user->identity_uuid,
        ],
    ]);
});
