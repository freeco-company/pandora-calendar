<?php

use App\Models\User;
use App\Services\Subscription\FeatureGate;
use App\Services\Subscription\PremiumTrialService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
});

it('starts a 7-day trial on onboarding completion', function () {
    Sanctum::actingAs($this->user);

    $res = $this->postJson('/api/v1/onboarding/complete', [
        'last_period_at' => now()->subDays(10)->toDateString(),
        'cycle_length' => 28,
        'goal' => 'tracking',
    ]);

    $res->assertOk()
        ->assertJsonPath('data.trial.is_trial', true)
        ->assertJsonPath('data.trial.days_remaining', 7)
        ->assertJsonPath('data.trial.trial_used', true)
        ->assertJsonPath('data.trial.source', 'onboarding')
        ->assertJsonPath('data.trial_activated', true);

    $this->user->refresh();
    expect($this->user->trial_used)->toBeTrue();
    expect($this->user->trial_started_at)->not->toBeNull();
    expect($this->user->trial_ends_at)->not->toBeNull();
});

it('counts trial as Premium for FeatureGate::isPremium', function () {
    $svc = app(PremiumTrialService::class);
    $svc->startTrial($this->user->id, 'onboarding');

    $gate = app(FeatureGate::class);
    expect($gate->isPremium($this->user->fresh()))->toBeTrue();
    expect($gate->effectiveTier($this->user->fresh()))->toBe('trial');
});

it('cannot start trial twice — trial_used is one-shot', function () {
    $svc = app(PremiumTrialService::class);

    expect($svc->startTrial($this->user->id))->toBeTrue();
    expect($svc->startTrial($this->user->id))->toBeFalse();
});

it('isInTrial returns false after 7 days', function () {
    $svc = app(PremiumTrialService::class);
    $svc->startTrial($this->user->id);

    expect($svc->isInTrial($this->user->id))->toBeTrue();

    Carbon::setTestNow(now()->addDays(8));
    expect($svc->isInTrial($this->user->id))->toBeFalse();

    $gate = app(FeatureGate::class);
    expect($gate->isPremium($this->user->fresh()))->toBeFalse();
    expect($gate->effectiveTier($this->user->fresh()))->toBe('free');

    Carbon::setTestNow(null);
});

it('daysRemaining decreases over time', function () {
    $svc = app(PremiumTrialService::class);
    $svc->startTrial($this->user->id);

    expect($svc->daysRemaining($this->user->id))->toBe(7);

    Carbon::setTestNow(now()->addDays(3));
    expect($svc->daysRemaining($this->user->id))->toBeLessThanOrEqual(4)
        ->and($svc->daysRemaining($this->user->id))->toBeGreaterThan(0);

    Carbon::setTestNow(null);
});

it('exposes trial state in subscription/me', function () {
    Sanctum::actingAs($this->user);
    app(PremiumTrialService::class)->startTrial($this->user->id);

    $res = $this->getJson('/api/v1/subscription/me');

    $res->assertOk()
        ->assertJsonPath('data.premium', true)
        ->assertJsonPath('data.tier', 'trial')
        ->assertJsonPath('data.trial.is_trial', true)
        ->assertJsonPath('data.trial.trial_used', true);
});

it('exposes trial endpoint independently', function () {
    Sanctum::actingAs($this->user);

    $this->getJson('/api/v1/subscription/trial')
        ->assertOk()
        ->assertJsonPath('data.is_trial', false)
        ->assertJsonPath('data.trial_used', false);

    app(PremiumTrialService::class)->startTrial($this->user->id);

    $this->getJson('/api/v1/subscription/trial')
        ->assertOk()
        ->assertJsonPath('data.is_trial', true)
        ->assertJsonPath('data.days_remaining', 7);
});

it('does not re-activate trial on second onboarding submit', function () {
    Sanctum::actingAs($this->user);

    $this->postJson('/api/v1/onboarding/complete', [
        'last_period_at' => now()->subDays(10)->toDateString(),
    ])->assertOk();

    $res = $this->postJson('/api/v1/onboarding/complete', [
        'last_period_at' => now()->subDays(5)->toDateString(),
    ]);

    $res->assertOk()
        ->assertJsonPath('data.trial_activated', false)
        ->assertJsonPath('data.trial.trial_used', true);
});
