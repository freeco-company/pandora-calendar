<?php

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

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
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

    Route::get('/cycles', [CycleController::class, 'index']);
    Route::post('/cycles', [CycleController::class, 'store']);
    Route::delete('/cycles/{cycle}', [CycleController::class, 'destroy']);

    Route::get('/symptoms', [SymptomController::class, 'index']);
    Route::post('/symptoms', [SymptomController::class, 'store']);

    Route::post('/dodo/checkin', [DodoController::class, 'checkin']);
    Route::get('/dodo/recent', [DodoController::class, 'recent']);

    Route::get('/body-rhythm/me', [BodyRhythmController::class, 'me']);

    Route::get('/subscription/me', [SubscriptionController::class, 'me']);
    Route::get('/subscription/products', [SubscriptionController::class, 'products']);
    Route::post('/subscription/verify-apple', [SubscriptionController::class, 'verifyApple']);
    Route::post('/subscription/verify-google', [SubscriptionController::class, 'verifyGoogle']);
    Route::post('/subscription/ecpay-checkout', [SubscriptionController::class, 'ecpayCheckout']);

    Route::post('/health-samples/import', [HealthSampleController::class, 'importBatch']);

    Route::get('/pregnancy/current', [PregnancyController::class, 'current']);
    Route::post('/pregnancy', [PregnancyController::class, 'start']);
    Route::patch('/pregnancy/{pregnancy}/end', [PregnancyController::class, 'end']);

    Route::get('/insight/pms', [InsightController::class, 'pms']);

    Route::get('/week-report/latest', [WeekReportController::class, 'latest']);
    Route::post('/week-report/generate', [WeekReportController::class, 'generate']);

    // 婕樂纖商品連結（P5+，gate 嚴守）
    Route::get('/commerce/product-links', [CommerceController::class, 'productLinks']);

    // P5.3 / P5.4 ADR-009：朵朵 / pet / pending events
    Route::get('/me/gamification/pending', [\App\Http\Controllers\Api\V1\MeGamificationController::class, 'pending']);
    Route::get('/me/dodo', [\App\Http\Controllers\Api\V1\MeGamificationController::class, 'dodo']);
    Route::get('/me/pet', [\App\Http\Controllers\Api\V1\MeGamificationController::class, 'pet']);
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
