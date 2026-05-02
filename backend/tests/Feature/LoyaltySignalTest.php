<?php

use App\Models\Cycle;
use App\Models\OutboxEvent;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Conversion\LifecycleEventCatalog;
use App\Services\Conversion\LoyaltySignalEvaluator;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('emits sustained_user signal at 90 days of activity', function () {
    $user = User::factory()->create();
    Cycle::create([
        'user_id' => $user->id,
        'start_date' => CarbonImmutable::today()->subDays(95)->toDateString(),
    ]);

    $signal = app(LoyaltySignalEvaluator::class)->evaluate($user);

    expect($signal)->toBe(LifecycleEventCatalog::SUSTAINED_USER);
    expect(OutboxEvent::where('event_kind', LifecycleEventCatalog::SUSTAINED_USER)->exists())->toBeTrue();
});

it('emits loyalist_high signal only when sub + mother purchase + 180 days', function () {
    $user = User::factory()->create(['mother_total_orders' => 1]);
    Cycle::create([
        'user_id' => $user->id,
        'start_date' => CarbonImmutable::today()->subDays(200)->toDateString(),
    ]);
    Subscription::create([
        'user_id' => $user->id,
        'platform' => 'apple',
        'product_id' => 'calendar.premium.monthly',
        'original_transaction_id' => 'tx-loyal',
        'starts_at' => now()->subMonth(),
        'ends_at' => now()->addMonth(),
        'status' => 'active',
        'auto_renew' => true,
    ]);

    $signal = app(LoyaltySignalEvaluator::class)->evaluate($user);

    expect($signal)->toBe(LifecycleEventCatalog::LOYALIST_HIGH);
});

it('returns null when active days < 90', function () {
    $user = User::factory()->create();
    Cycle::create([
        'user_id' => $user->id,
        'start_date' => CarbonImmutable::today()->subDays(30)->toDateString(),
    ]);

    expect(app(LoyaltySignalEvaluator::class)->evaluate($user))->toBeNull();
});
