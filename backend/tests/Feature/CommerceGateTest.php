<?php

use App\Models\Cycle;
use App\Models\Subscription;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    Sanctum::actingAs($this->user);
});

it('returns empty product links when none of the gate conditions are met', function () {
    $res = $this->getJson('/api/v1/commerce/product-links');

    $res->assertOk()
        ->assertJsonPath('gate_passed', false)
        ->assertJsonPath('data', []);
});

it('still returns empty when only mother purchase exists', function () {
    $this->user->update(['mother_total_orders' => 2]);

    $this->getJson('/api/v1/commerce/product-links')
        ->assertJsonPath('gate_passed', false);
});

it('still returns empty when only premium subscription exists', function () {
    Subscription::create([
        'user_id' => $this->user->id,
        'platform' => 'apple',
        'product_id' => 'calendar.premium.monthly',
        'original_transaction_id' => 'tx-1',
        'starts_at' => now()->subDay(),
        'ends_at' => now()->addMonth(),
        'status' => 'active',
        'auto_renew' => true,
    ]);

    $this->getJson('/api/v1/commerce/product-links')
        ->assertJsonPath('gate_passed', false);
});

it('passes the gate only when all three: mother + premium + 90 day usage', function () {
    $this->user->update(['mother_total_orders' => 2]);
    Subscription::create([
        'user_id' => $this->user->id,
        'platform' => 'apple',
        'product_id' => 'calendar.premium.monthly',
        'original_transaction_id' => 'tx-1',
        'starts_at' => now()->subDays(120),
        'ends_at' => now()->addMonth(),
        'status' => 'active',
        'auto_renew' => true,
    ]);
    // 91 days of cycle history
    Cycle::create([
        'user_id' => $this->user->id,
        'start_date' => CarbonImmutable::today()->subDays(91)->toDateString(),
    ]);

    $res = $this->getJson('/api/v1/commerce/product-links');

    $res->assertOk()->assertJsonPath('gate_passed', true);
});
