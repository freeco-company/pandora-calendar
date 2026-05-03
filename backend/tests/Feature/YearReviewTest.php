<?php

use App\Models\Cycle;
use App\Models\CycleSymptom;
use App\Models\DodoCheckin;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

function makePremiumYR(User $user): void
{
    Subscription::create([
        'user_id' => $user->id,
        'platform' => 'apple',
        'product_id' => 'calendar.premium.monthly',
        'original_transaction_id' => 'tx-yr-'.$user->id,
        'starts_at' => now()->subDay(),
        'ends_at' => now()->addMonth(),
        'status' => 'active',
        'auto_renew' => true,
    ]);
}

beforeEach(function () {
    $this->user = User::factory()->create(['name' => '小敏', 'level' => 5]);
    Sanctum::actingAs($this->user);
});

it('blocks free user from year review', function () {
    $this->getJson('/api/v1/year-review/'.now()->year)
        ->assertStatus(402);
});

it('returns at least 8 cards on happy path', function () {
    makePremiumYR($this->user);
    $year = (int) now()->year;

    // 6 cycles 一年
    for ($i = 0; $i < 6; $i++) {
        Cycle::create([
            'user_id' => $this->user->id,
            'start_date' => now()->setYear($year)->setMonth(1)->setDay(1)->addDays($i * 28)->toDateString(),
            'end_date' => now()->setYear($year)->setMonth(1)->setDay(1)->addDays($i * 28 + 4)->toDateString(),
        ]);
    }

    CycleSymptom::create([
        'user_id' => $this->user->id,
        'logged_on' => now()->setYear($year)->setMonth(3)->setDay(15)->toDateString(),
        'tags' => ['cramp', 'fatigue'],
        'mood' => 'low',
    ]);

    DodoCheckin::create([
        'user_id' => $this->user->id,
        'checked_on' => now()->setYear($year)->setMonth(4)->setDay(10)->toDateString(),
        'mood' => 'okay',
        'phase_at_checkin' => 'follicular',
        'cycle_day_at_checkin' => 5,
        'dodo_response' => '今天朵朵在這裡。',
    ]);

    $res = $this->getJson("/api/v1/year-review/{$year}")->assertOk();

    $cards = $res->json('data.cards');
    expect(count($cards))->toBeGreaterThanOrEqual(8);
    expect($res->json('data.stats.cycle_count'))->toBe(6);
});

it('falls back to short template when data insufficient', function () {
    makePremiumYR($this->user);
    $year = (int) now()->year;

    $res = $this->getJson("/api/v1/year-review/{$year}")->assertOk();

    // < 2 cycles → fallback (2 cards)
    expect(count($res->json('data.cards')))->toBeLessThanOrEqual(4);
    expect($res->json('data.stats.cycle_count'))->toBe(0);
});

it('rejects out-of-range year', function () {
    makePremiumYR($this->user);
    $this->getJson('/api/v1/year-review/'.((int) now()->year + 5))->assertNotFound();
    $this->getJson('/api/v1/year-review/'.((int) now()->year - 99))->assertNotFound();
});
