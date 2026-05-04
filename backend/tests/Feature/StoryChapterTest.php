<?php

use App\Models\Cycle;
use App\Models\User;
use Carbon\CarbonImmutable;
use App\Services\Economy\DodoCoinService;
use App\Services\Gamification\StoryChapterService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    Sanctum::actingAs($this->user);
    $this->stories = app(StoryChapterService::class);
    $this->coins = app(DodoCoinService::class);
});

it('catalog has 25 chapters', function () {
    expect($this->stories->chapters())->toHaveCount(25);
});

it('onboarding unlocks chapter 1', function () {
    expect($this->stories->unlockOnboarding($this->user->id))->toBeTrue();
    expect($this->stories->isUnlocked($this->user->id, 1))->toBeTrue();
    expect($this->stories->unlockOnboarding($this->user->id))->toBeFalse(); // idempotent
});

it('auto-unlocks chapters based on cycle count', function () {
    $today = CarbonImmutable::today();
    for ($i = 0; $i < 5; $i++) {
        Cycle::create([
            'user_id' => $this->user->id,
            'start_date' => $today->subDays(30 * ($i + 1))->toDateString(),
            'end_date' => $today->subDays(30 * ($i + 1) - 5)->toDateString(),
        ]);
    }
    $new = $this->stories->autoUnlockByCycles($this->user->id);
    // chapter 1 needs 0 cycles, ch2 needs 1, ... ch6 needs 5 → 6 unlocked
    expect(count($new))->toBeGreaterThanOrEqual(6);
});

it('coin unlock spends coins (chapter 6+，ch1-5 已改 free)', function () {
    // 2026-05-04 freemium：ch1-5 unlock_cycle=0 + coin_cost=0 已 free 自動。
    // 用 ch 6 測 coin spend（仍要 100）
    $this->coins->earn($this->user->id, 200, DodoCoinService::SOURCE_DAILY_ACTION);
    expect($this->stories->unlockWithCoins($this->user->id, 6))->toBeTrue();
    expect($this->coins->balance($this->user->id))->toBe(100);
});

it('coin unlock fails on insufficient balance', function () {
    expect($this->stories->unlockWithCoins($this->user->id, 10))->toBeFalse();
});

it('marks read once', function () {
    $this->stories->unlockOnboarding($this->user->id);
    expect($this->stories->markRead($this->user->id, 1))->toBeTrue();
    expect($this->stories->markRead($this->user->id, 1))->toBeFalse();
});

it('exposes endpoint with auto-unlock ch1-5 free (freemium 2026-05-04)', function () {
    // GET 端會 auto-unlock 滿足 cycle 條件的章節；ch1-5 unlock_cycle=0 → 立刻全解
    $res = $this->getJson('/api/v1/me/stories/chapters')->assertOk();
    expect($res->json('data.unlocked_count'))->toBe(5);
    expect($res->json('data.total'))->toBe(25);
});
