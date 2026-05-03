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

it('coin unlock spends coins', function () {
    $this->coins->earn($this->user->id, 200, DodoCoinService::SOURCE_DAILY_ACTION);
    expect($this->stories->unlockWithCoins($this->user->id, 5))->toBeTrue();
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

it('exposes endpoint', function () {
    $this->stories->unlockOnboarding($this->user->id);
    $res = $this->getJson('/api/v1/me/stories/chapters')->assertOk();
    expect($res->json('data.unlocked_count'))->toBe(1);
    expect($res->json('data.total'))->toBe(25);
});
