<?php

use App\Models\Feedback;
use App\Models\SubscriptionPauseRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    Sanctum::actingAs($this->user);
});

it('records pause request', function () {
    $res = $this->postJson('/api/v1/subscription/pause', [
        'months' => 2,
        'reason' => 'not_using',
    ])->assertCreated();

    expect(SubscriptionPauseRequest::count())->toBe(1);
    expect($res->json('data.months'))->toBe(2);
    expect($res->json('data.pause_until'))->not->toBeNull();
});

it('rejects out-of-range pause months', function () {
    $this->postJson('/api/v1/subscription/pause', ['months' => 12])
        ->assertStatus(422);
});

it('records cancel feedback', function () {
    $this->postJson('/api/v1/subscription/cancel-feedback', [
        'reason' => 'too_expensive',
        'message' => '能再便宜點就好了',
    ])->assertCreated();

    expect(Feedback::count())->toBe(1);
});

it('returns churn-intercept config', function () {
    $res = $this->getJson('/api/v1/subscription/churn-intercept')
        ->assertOk();

    expect($res->json('data.reasons'))->toBeArray();
    expect(count($res->json('data.reasons')))->toBeGreaterThan(0);
});

it('faq endpoint returns categories', function () {
    // 公開 endpoint — 即使有/沒有登入都應該回 200
    $res = $this->getJson('/api/v1/faq')->assertOk();

    expect($res->json('data.categories'))->toBeArray();
    expect(count($res->json('data.categories')))->toBeGreaterThan(0);
});
