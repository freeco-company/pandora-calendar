<?php

use App\Models\Subscription;
use App\Models\User;
use App\Services\Subscription\EntitlementResolver;
use App\Services\Subscription\FeatureGate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    Sanctum::actingAs($this->user);
});

it('returns free entitlements when no subscription', function () {
    $res = $this->getJson('/api/v1/subscription/me');

    $res->assertOk()->assertJsonPath('data.premium', false);
});

it('returns premium when active subscription exists', function () {
    Subscription::create([
        'user_id' => $this->user->id,
        'platform' => 'apple',
        'product_id' => 'calendar.premium.monthly',
        'original_transaction_id' => 'tx-99',
        'starts_at' => now()->subDay(),
        'ends_at' => now()->addMonth(),
        'status' => 'active',
        'auto_renew' => true,
    ]);

    $res = $this->getJson('/api/v1/subscription/me');

    $res->assertOk()
        ->assertJsonPath('data.premium', true)
        ->assertJsonPath('data.product_id', 'calendar.premium.monthly');
});

it('treats expired subscriptions as free', function () {
    Subscription::create([
        'user_id' => $this->user->id,
        'platform' => 'google',
        'product_id' => 'calendar.premium.monthly',
        'original_transaction_id' => 'tx-old',
        'starts_at' => now()->subYear(),
        'ends_at' => now()->subMonth(),
        'status' => 'expired',
        'auto_renew' => false,
    ]);

    expect(app(FeatureGate::class)->isPremium($this->user))->toBeFalse();
});

it('lists products with annual discount', function () {
    $res = $this->getJson('/api/v1/subscription/products');

    $res->assertOk();
    expect($res->json('data'))->toHaveCount(2);
    expect($res->json('data.0.price_twd'))->toBe(99);
    expect($res->json('data.1.price_twd'))->toBe(899);
});

it('verifies a google purchase via stub when no service account configured', function () {
    config(['pandora.subscription.google_play_service_account_json' => null]);

    $res = $this->postJson('/api/v1/subscription/verify-google', [
        'purchase_token' => 'fake-token-xyz',
        'product_id' => 'calendar.premium.monthly',
        'package_name' => 'com.jerosse.pandora.calendar',
    ]);

    $res->assertCreated()->assertJsonPath('data.status', 'active');

    expect(app(EntitlementResolver::class)->resolve($this->user)->isPremium())->toBeTrue();
});

it('rejects ecpay checkout with bad product', function () {
    $this->postJson('/api/v1/subscription/ecpay-checkout', [
        'product_id' => 'not-a-product',
        'return_url' => 'https://example.com/r',
    ])->assertStatus(422);
});
