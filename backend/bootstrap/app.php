<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->statefulApi();

        $middleware->alias([
            // P5.3 ADR-009：gamification webhook HMAC + replay 驗證
            'gamification.webhook' => \App\Http\Middleware\VerifyGamificationWebhookSignature::class,
            // P1 ADR-007：純 Pandora Core JWT 認證（嚴格模式）
            'pandora.jwt' => \App\Http\Middleware\PandoraJwtAuth::class,
            // P1 ADR-007：JWT 優先 + sanctum fallback（dev demo login 路徑）
            'auth.platform' => \App\Http\Middleware\SanctumOrPandoraJwt::class,
            // P1 ADR-007：PC user.upserted webhook 簽章 + nonce 驗證
            'identity.webhook' => \App\Http\Middleware\VerifyIdentityWebhookSignature::class,
            // P5：婕樂纖深層商品連結 gate（母艦消費 + 訂閱 + 連用 ≥ 90 天）
            'ensure.mother_customer' => \App\Http\Middleware\EnsureMotherCustomer::class,
            // SPEC-cross-app-streak Phase 1.B：每日登入 streak 中介層（Asia/Taipei）
            'daily.streak' => \App\Http\Middleware\RecordDailyStreak::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Sentry — Laravel 11+ exception handler 註冊（health-data scrubbing 在 config/sentry.php）
        // DSN 未設時 SDK noop，不影響 dev / test
        \Sentry\Laravel\Integration::handles($exceptions);
    })->create();
