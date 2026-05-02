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
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
