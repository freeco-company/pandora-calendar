<?php

use App\Models\BbtReading;
use App\Models\Cycle;
use App\Models\HealthSample;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Health\HealthSampleReflection;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

function makePremiumForReflection(User $user): void
{
    Subscription::create([
        'user_id' => $user->id,
        'platform' => 'apple',
        'product_id' => 'calendar.premium.monthly',
        'original_transaction_id' => 'tx-rf-'.$user->id,
        'starts_at' => now()->subDay(),
        'ends_at' => now()->addMonth(),
        'status' => 'active',
        'auto_renew' => true,
    ]);
}

beforeEach(function () {
    $this->user = User::factory()->create();
    Sanctum::actingAs($this->user);
    makePremiumForReflection($this->user);
});

it('returns null when no health data', function () {
    $svc = app(HealthSampleReflection::class);
    expect($svc->reflectToday($this->user->id))->toBeNull();
});

it('reflects sleep_insufficient_luteal when luteal phase + bad sleep', function () {
    // 製造 luteal phase：cycle_length 28，今天 cycleDay = 22（luteal）
    // start = today - 21 → cycleDay = 22
    Cycle::create([
        'user_id' => $this->user->id,
        'start_date' => CarbonImmutable::today()->subDays(49)->toDateString(),
        'end_date' => CarbonImmutable::today()->subDays(45)->toDateString(),
    ]);
    Cycle::create([
        'user_id' => $this->user->id,
        'start_date' => CarbonImmutable::today()->subDays(21)->toDateString(),
        'end_date' => CarbonImmutable::today()->subDays(17)->toDateString(),
    ]);

    HealthSample::create([
        'user_id' => $this->user->id,
        'source' => 'healthkit',
        'metric' => HealthSample::METRIC_SLEEP_HOURS,
        'value' => 5.5,
        'recorded_on' => CarbonImmutable::today()->subDay()->toDateString(),
        'recorded_at' => CarbonImmutable::today()->subDay(),
    ]);

    $svc = app(HealthSampleReflection::class);
    $r = $svc->reflectToday($this->user->id);
    expect($r)->not->toBeNull();
    expect($r['suggested_action_type'])->toBe('sleep');
    expect($r['severity'])->toBe('heads_up');
    expect($r['source'])->toBe('sleep_insufficient_luteal');
    expect($r['message'])->toContain('5.5');
});

it('reflects bbt_shift_ovulation when near ovulation + 0.3+ shift', function () {
    // ovulationDay = 28-14 = 14；cycleDay = 14 → start = today - 13
    Cycle::create([
        'user_id' => $this->user->id,
        'start_date' => CarbonImmutable::today()->subDays(41)->toDateString(),
        'end_date' => CarbonImmutable::today()->subDays(37)->toDateString(),
    ]);
    Cycle::create([
        'user_id' => $this->user->id,
        'start_date' => CarbonImmutable::today()->subDays(13)->toDateString(),
        'end_date' => CarbonImmutable::today()->subDays(9)->toDateString(),
    ]);

    // 7 天 BBT baseline 平均 36.4，昨天 36.75 → +0.35
    foreach (range(7, 2) as $offset) {
        BbtReading::create([
            'user_id' => $this->user->id,
            'measured_on' => CarbonImmutable::today()->subDays($offset)->toDateString(),
            'temperature_c' => 36.4,
        ]);
    }
    BbtReading::create([
        'user_id' => $this->user->id,
        'measured_on' => CarbonImmutable::today()->subDay()->toDateString(),
        'temperature_c' => 36.75,
    ]);

    $svc = app(HealthSampleReflection::class);
    $r = $svc->reflectToday($this->user->id);
    expect($r)->not->toBeNull();
    expect($r['suggested_action_type'])->toBe('track');
    expect($r['source'])->toBe('bbt_shift_ovulation');
});

it('reflects steps_dip_3d when last 3 days < 80% of 7d avg', function () {
    Cycle::create([
        'user_id' => $this->user->id,
        'start_date' => CarbonImmutable::today()->subDays(5)->toDateString(),
        'end_date' => CarbonImmutable::today()->subDays(1)->toDateString(),
    ]);

    // baseline (day -7..-4)：每天 10000；近 3 天 (day -3..-1)：每天 5000（< 80% of 10000）
    $today = CarbonImmutable::today();
    foreach ([7, 6, 5, 4] as $offset) {
        HealthSample::create([
            'user_id' => $this->user->id,
            'source' => 'healthkit',
            'metric' => HealthSample::METRIC_STEPS,
            'value' => 10000,
            'recorded_on' => $today->subDays($offset)->toDateString(),
            'recorded_at' => $today->subDays($offset),
        ]);
    }
    foreach ([3, 2, 1] as $offset) {
        HealthSample::create([
            'user_id' => $this->user->id,
            'source' => 'healthkit',
            'metric' => HealthSample::METRIC_STEPS,
            'value' => 5000,
            'recorded_on' => $today->subDays($offset)->toDateString(),
            'recorded_at' => $today->subDays($offset),
        ]);
    }

    $svc = app(HealthSampleReflection::class);
    $r = $svc->reflectToday($this->user->id);
    expect($r)->not->toBeNull();
    expect($r['suggested_action_type'])->toBe('move');
    expect($r['source'])->toBe('steps_dip_3d');
});

it('endpoint requires premium', function () {
    $free = User::factory()->create();
    Sanctum::actingAs($free);

    $this->getJson('/api/v1/health-samples/reflection/today')->assertStatus(402);
});

it('endpoint returns null when no data', function () {
    $this->getJson('/api/v1/health-samples/reflection/today')
        ->assertOk()
        ->assertJsonPath('data', null);
});
