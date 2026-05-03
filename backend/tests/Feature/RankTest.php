<?php

use App\Models\Achievement;
use App\Models\Cycle;
use Carbon\CarbonImmutable;
use App\Models\DodoCheckin;
use App\Models\User;
use App\Services\Gamification\RankService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    Sanctum::actingAs($this->user);
    $this->ranks = app(RankService::class);
});

it('starts in cang tier with zero xp', function () {
    $rank = $this->ranks->currentRank($this->user->id);
    expect($rank['tier_key'])->toBe('cang');
    expect($rank['xp'])->toBe(0);
});

it('progresses to higher tier as cycles + achievements + days_active accumulate', function () {
    // 11 cycles ×100 = 1100 → past yu (1000)
    $today = CarbonImmutable::today();
    for ($i = 0; $i < 11; $i++) {
        Cycle::create([
            'user_id' => $this->user->id,
            'start_date' => $today->subDays(30 * ($i + 1))->toDateString(),
            'end_date' => $today->subDays(30 * ($i + 1) - 5)->toDateString(),
        ]);
    }
    $rank = $this->ranks->currentRank($this->user->id);
    expect($rank['tier_key'])->toBe('yu');
    expect($rank['xp'])->toBeGreaterThanOrEqual(1100);
});

it('reports next threshold + progress percent', function () {
    $today = CarbonImmutable::today();
    for ($i = 0; $i < 3; $i++) {
        Cycle::create([
            'user_id' => $this->user->id,
            'start_date' => $today->subDays(30 * ($i + 1))->toDateString(),
            'end_date' => $today->subDays(30 * ($i + 1) - 5)->toDateString(),
        ]);
    }
    $rank = $this->ranks->currentRank($this->user->id);
    expect($rank['next_threshold'])->toBe(1000);
    expect($rank['progress_percent'])->toBeGreaterThan(0);
});

it('reaches max tier when xp huge', function () {
    $resolved = $this->ranks->resolve(50000);
    expect($resolved['tier_key'])->toBe('xuan');
    expect($resolved['is_max_tier'])->toBeTrue();
    expect($resolved['next_threshold'])->toBeNull();
});

it('exposes rank endpoint', function () {
    $res = $this->getJson('/api/v1/me/rank')->assertOk();
    expect($res->json('data.tier_key'))->toBe('cang');
});
