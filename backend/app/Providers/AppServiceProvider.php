<?php

namespace App\Providers;

use App\Services\Community\AnonymousHandle;
use App\Services\Conversion\ConversionPublisher;
use App\Services\Conversion\NoopConversionPublisher;
use App\Services\Conversion\HttpConversionPublisher;
use App\Services\Gamification\GamificationPublisher;
use App\Services\Gamification\NoopGamificationPublisher;
use App\Services\Gamification\HttpGamificationPublisher;
use App\Services\Push\ApnsChannel;
use App\Services\Push\FcmChannel;
use App\Services\Push\PushDispatcher;
use App\Services\Push\WebPushChannel;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // IdentityClient + PlatformJwtVerifier 為純 concrete class，由容器自動 resolve。
        // 舊 IDENTITY_DRIVER=mock|http 切換取消（PC 端 lookup API 從未實作），
        // 改為 mirror meal 的 resolveFromJwt pattern。Dev/testing 走 sanctum
        // demo login + SanctumOrPandoraJwt middleware，與 prod JWT 路徑並存。

        $this->app->bind(GamificationPublisher::class, function ($app) {
            // P5.1：以新 config('gamification') 為主，legacy config('pandora.gamification') 為 fallback。
            // enabled flag 必須 true，且 base_url + secret 都齊才走 HTTP 版；任一缺 → noop。
            $enabled = (bool) config('gamification.enabled', false);
            $url = config('gamification.base_url') ?: config('pandora.gamification.base_url');
            $secret = config('gamification.internal_secret')
                ?: config('gamification.hmac_secret')
                ?: config('pandora.gamification.secret');

            return ($enabled && $url && $secret)
                ? new HttpGamificationPublisher($app->make(HttpFactory::class), $url, $secret)
                : new NoopGamificationPublisher;
        });

        // Community anonymous handle generator — keyed by APP_KEY-derived secret
        // so handles are deterministic per env (dev / testing / prod) but unguessable.
        $this->app->singleton(AnonymousHandle::class, function () {
            $secret = config('app.community_handle_secret')
                ?: config('app.key', 'pandora-calendar-community-fallback-secret');

            return new AnonymousHandle((string) $secret);
        });

        // Push channels — pluggable, env-driven。缺 credential → channel.isConfigured()=false → noop。
        $this->app->singleton(FcmChannel::class, fn () => new FcmChannel(
            (string) config('push.fcm.project_id', ''),
            (string) config('push.fcm.credentials_path', ''),
        ));
        $this->app->singleton(ApnsChannel::class, fn () => new ApnsChannel(
            (string) config('push.apns.team_id', ''),
            (string) config('push.apns.key_id', ''),
            (string) config('push.apns.private_key_path', ''),
            (string) config('push.apns.bundle_id', ''),
            (bool) config('push.apns.sandbox', false),
        ));
        $this->app->singleton(WebPushChannel::class, fn () => new WebPushChannel(
            (string) config('push.webpush.subject', ''),
            (string) config('push.webpush.public_key', ''),
            (string) config('push.webpush.private_key', ''),
        ));
        $this->app->singleton(PushDispatcher::class, fn ($app) => new PushDispatcher(
            $app->make(FcmChannel::class),
            $app->make(ApnsChannel::class),
            $app->make(WebPushChannel::class),
        ));

        $this->app->bind(ConversionPublisher::class, function ($app) {
            $url = config('pandora.conversion.base_url');
            $secret = config('pandora.conversion.secret');

            return $url && $secret
                ? new HttpConversionPublisher($app->make(HttpFactory::class), $url, $secret)
                : new NoopConversionPublisher;
        });
    }

    public function boot(): void
    {
        // Sentry scrubbing closures 在 boot 動態註冊（不能放 config，會擋 config:cache）
        if (class_exists(\Sentry\SentrySdk::class) && config('sentry.dsn')) {
            $client = \Sentry\SentrySdk::getCurrentHub()->getClient();
            if ($client) {
                $options = $client->getOptions();
                $options->setBeforeSendCallback(fn (\Sentry\Event $event) => \App\Support\Sentry\HealthDataScrubber::scrub($event));
                $options->setBeforeSendTransactionCallback(fn (\Sentry\Event $event) => \App\Support\Sentry\HealthDataScrubber::scrubTransaction($event));
                $options->setBeforeBreadcrumbCallback(fn (\Sentry\Breadcrumb $b) => \App\Support\Sentry\HealthDataScrubber::scrubBreadcrumb($b));
            }
        }
    }
}
