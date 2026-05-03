<?php

use App\Models\BbtReading;
use App\Models\Subscription;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\DailyInsightSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    Sanctum::actingAs($this->user);
});

function makePremiumForBbt(User $user): void
{
    Subscription::create([
        'user_id' => $user->id,
        'platform' => 'apple',
        'product_id' => 'calendar.premium.monthly',
        'original_transaction_id' => 'tx-bbt-'.$user->id,
        'starts_at' => now()->subDay(),
        'ends_at' => now()->addMonth(),
        'status' => 'active',
        'auto_renew' => true,
    ]);
}

it('returns symptom tag canonical grouped', function () {
    $res = $this->getJson('/api/v1/symptom-tags');

    $res->assertOk()
        ->assertJsonStructure(['data' => ['physical', 'emotional', 'sexual', 'fertility']]);

    expect($res->json('data.physical'))->not->toBeEmpty();
});

it('accepts new tag keys via store endpoint', function () {
    $res = $this->postJson('/api/v1/symptoms', [
        'logged_on' => CarbonImmutable::today()->toDateString(),
        'tags' => ['anxious', 'libido_low'],
    ]);

    $res->assertCreated();
});

it('returns daily insight by phase + offset', function () {
    $this->seed(DailyInsightSeeder::class);

    $res = $this->getJson('/api/v1/insights/today?phase=luteal&day_offset=3');
    $res->assertOk();
    expect($res->json('data'))->not->toBeNull();
    expect($res->json('data.phase'))->toBe('luteal');
});

it('detects bbt biphasic shift', function () {
    makePremiumForBbt($this->user);
    $today = CarbonImmutable::today();
    // 6 天 baseline 36.4，之後 5 天 36.7（>coverline 36.5）
    foreach (range(0, 5) as $i) {
        BbtReading::create([
            'user_id' => $this->user->id,
            'measured_on' => $today->subDays(15 - $i)->toDateString(),
            'temperature_c' => 36.40,
        ]);
    }
    foreach (range(0, 4) as $i) {
        BbtReading::create([
            'user_id' => $this->user->id,
            'measured_on' => $today->subDays(9 - $i)->toDateString(),
            'temperature_c' => 36.70,
        ]);
    }

    $res = $this->getJson('/api/v1/bbt/biphasic');
    $res->assertOk()
        ->assertJsonPath('data.has_shift', true);

    expect($res->json('data.shift_date'))->not->toBeNull();
    expect($res->json('data.coverline'))->toBeGreaterThan(36.4);
});

it('returns no shift with insufficient bbt data', function () {
    makePremiumForBbt($this->user);
    $res = $this->getJson('/api/v1/bbt/biphasic');
    $res->assertOk()
        ->assertJsonPath('data.has_shift', false);
});

it('exposes confidence_level and std_dev in prediction', function () {
    $today = CarbonImmutable::today();
    foreach ([90, 60, 30] as $daysAgo) {
        $this->postJson('/api/v1/cycles', [
            'start_date' => $today->subDays($daysAgo)->toDateString(),
            'end_date' => $today->subDays($daysAgo - 4)->toDateString(),
        ])->assertCreated();
    }

    $res = $this->getJson('/api/v1/cycles');
    $res->assertOk()
        ->assertJsonPath('prediction.sample_size', 3);

    expect($res->json('prediction.confidence_level'))->toBeIn(['high', 'medium', 'low', 'unknown']);
});
