<?php

use App\Models\Cycle;
use App\Models\User;
use App\Services\Calendar\CyclePredictor;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    Sanctum::actingAs($this->user);
});

it('rejects end_date in the future', function () {
    $today = CarbonImmutable::today();

    $res = $this->postJson('/api/v1/cycles', [
        'start_date' => $today->subDays(2)->toDateString(),
        'end_date' => $today->addDay()->toDateString(),
        'peak_flow' => 3,
    ]);

    $res->assertStatus(422)
        ->assertJsonValidationErrors(['end_date']);

    expect($res->json('errors.end_date.0'))->toContain('未來');
});

it('rejects end_date 30 days after start (period too long)', function () {
    $today = CarbonImmutable::today();

    $res = $this->postJson('/api/v1/cycles', [
        'start_date' => $today->subDays(30)->toDateString(),
        'end_date' => $today->toDateString(),
        'peak_flow' => 3,
    ]);

    $res->assertStatus(422)
        ->assertJsonValidationErrors(['end_date']);

    expect($res->json('errors.end_date.0'))->toContain('異常');
});

it('accepts end_date 7 days after start (normal period)', function () {
    $today = CarbonImmutable::today();

    $this->postJson('/api/v1/cycles', [
        'start_date' => $today->subDays(10)->toDateString(),
        'end_date' => $today->subDays(3)->toDateString(),
        'peak_flow' => 3,
    ])->assertCreated();
});

it('predictor filters dirty cycle (>14d) and uses clean ones for avg', function () {
    $today = CarbonImmutable::today();

    // 髒資料：直接 DB insert 繞過 controller validation（模擬 prod 既有髒料）
    Cycle::create([
        'user_id' => $this->user->id,
        'start_date' => $today->subDays(60)->toDateString(),
        'end_date' => $today->subDays(42)->toDateString(), // 18 天 → 髒
        'peak_flow' => 3,
    ]);

    // 乾淨資料 5 天
    Cycle::create([
        'user_id' => $this->user->id,
        'start_date' => $today->subDays(30)->toDateString(),
        'end_date' => $today->subDays(26)->toDateString(), // 5 天
        'peak_flow' => 3,
    ]);

    $predictor = app(CyclePredictor::class);
    $prediction = $predictor->predict($this->user->id, $today);

    // 只用乾淨的 5 天算 avgPeriodLength
    expect($prediction->avgPeriodLength)->toBe(5);
});

it('predictor falls back to DEFAULT_PERIOD_LENGTH when all cycles are dirty', function () {
    $today = CarbonImmutable::today();

    Cycle::create([
        'user_id' => $this->user->id,
        'start_date' => $today->subDays(60)->toDateString(),
        'end_date' => $today->subDays(42)->toDateString(), // 18 天 → 髒
        'peak_flow' => 3,
    ]);
    Cycle::create([
        'user_id' => $this->user->id,
        'start_date' => $today->subDays(30)->toDateString(),
        'end_date' => $today->subDays(10)->toDateString(), // 20 天 → 髒
        'peak_flow' => 3,
    ]);

    $predictor = app(CyclePredictor::class);
    $prediction = $predictor->predict($this->user->id, $today);

    expect($prediction->avgPeriodLength)->toBe(CyclePredictor::DEFAULT_PERIOD_LENGTH);
});

it('Cycle model lengthInDays returns null for dirty rows', function () {
    $today = CarbonImmutable::today();

    $dirty = Cycle::create([
        'user_id' => $this->user->id,
        'start_date' => $today->subDays(20)->toDateString(),
        'end_date' => $today->toDateString(), // 21 天
        'peak_flow' => 3,
    ]);

    expect($dirty->lengthInDays())->toBeNull();

    $clean = Cycle::create([
        'user_id' => $this->user->id,
        'start_date' => $today->subDays(60)->toDateString(),
        'end_date' => $today->subDays(56)->toDateString(), // 5 天
        'peak_flow' => 3,
    ]);

    expect($clean->lengthInDays())->toBe(5);
});
