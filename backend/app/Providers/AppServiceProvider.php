<?php

namespace App\Providers;

use App\Services\Conversion\ConversionPublisher;
use App\Services\Conversion\NoopConversionPublisher;
use App\Services\Conversion\HttpConversionPublisher;
use App\Services\Gamification\GamificationPublisher;
use App\Services\Gamification\NoopGamificationPublisher;
use App\Services\Gamification\HttpGamificationPublisher;
use App\Services\Identity\HttpIdentityClient;
use App\Services\Identity\IdentityClient;
use App\Services\Identity\MockIdentityClient;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(IdentityClient::class, function ($app) {
            $driver = config('pandora.identity.driver', 'mock');

            return $driver === 'http'
                ? new HttpIdentityClient(
                    $app->make(HttpFactory::class),
                    config('pandora.identity.base_url', ''),
                    config('pandora.identity.secret', ''),
                )
                : new MockIdentityClient;
        });

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
        //
    }
}
