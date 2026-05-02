<?php

use App\Services\Gamification\GamificationPublisher;
use App\Services\Gamification\HttpGamificationPublisher;
use App\Services\Gamification\NoopGamificationPublisher;

it('binds Noop publisher when gamification disabled', function () {
    config()->set('gamification.enabled', false);
    config()->set('gamification.base_url', 'https://py.example.test');
    config()->set('gamification.hmac_secret', 'shh');

    $this->refreshApplication();
    config()->set('gamification.enabled', false);
    config()->set('gamification.base_url', 'https://py.example.test');
    config()->set('gamification.hmac_secret', 'shh');

    expect(app(GamificationPublisher::class))->toBeInstanceOf(NoopGamificationPublisher::class);
});

it('binds Http publisher when enabled + base_url + secret all set', function () {
    config()->set('gamification.enabled', true);
    config()->set('gamification.base_url', 'https://py.example.test');
    config()->set('gamification.hmac_secret', 'shh');
    config()->set('gamification.internal_secret', 'shh');

    // re-resolve binding cleanly (Laravel singleton cache busting)
    app()->forgetInstance(GamificationPublisher::class);

    expect(app(GamificationPublisher::class))->toBeInstanceOf(HttpGamificationPublisher::class);
});

it('binds Noop when enabled but secret missing', function () {
    config()->set('gamification.enabled', true);
    config()->set('gamification.base_url', 'https://py.example.test');
    config()->set('gamification.hmac_secret', null);
    config()->set('gamification.internal_secret', null);
    config()->set('pandora.gamification.secret', null);

    app()->forgetInstance(GamificationPublisher::class);

    expect(app(GamificationPublisher::class))->toBeInstanceOf(NoopGamificationPublisher::class);
});
