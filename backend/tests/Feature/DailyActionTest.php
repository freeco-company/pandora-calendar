<?php

use App\Models\Cycle;
use App\Models\DailyActionRecommendation;
use App\Models\User;
use App\Models\UserActionProtocol;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    Sanctum::actingAs($this->user);

    // 提供 cycles 讓 predictor / rhythm 算得出 phase；
    // start = 5 天前 → cycleDay=6（避免落在 menstrual 邊界）→ follicular phase
    Cycle::create([
        'user_id' => $this->user->id,
        'start_date' => now()->subDays(33)->toDateString(),
        'end_date' => now()->subDays(29)->toDateString(),
    ]);
    Cycle::create([
        'user_id' => $this->user->id,
        'start_date' => now()->subDays(5)->toDateString(),
        'end_date' => now()->subDays(1)->toDateString(),
    ]);
});

it('returns today action with phase + card', function () {
    $res = $this->getJson('/api/v1/actions/today')->assertOk();

    expect($res->json('data.id'))->toBeInt();
    expect($res->json('data.phase'))->toBeString();
    expect($res->json('data.card.title'))->toBeString();
    expect($res->json('data.is_completed'))->toBeFalse();
});

it('is idempotent — same user same day returns same recommendation', function () {
    $first = $this->getJson('/api/v1/actions/today')->json('data.id');
    $second = $this->getJson('/api/v1/actions/today')->json('data.id');

    expect($first)->toBe($second);
    expect(DailyActionRecommendation::where('user_id', $this->user->id)->count())->toBe(1);
});

it('marks recommendation complete', function () {
    $recId = $this->getJson('/api/v1/actions/today')->json('data.id');

    $res = $this->postJson("/api/v1/actions/{$recId}/complete")->assertOk();

    expect($res->json('data.is_completed'))->toBeTrue();
    expect($res->json('data.completed_at'))->not->toBeNull();
});

it('records feedback and recomputes protocol', function () {
    $recId = $this->getJson('/api/v1/actions/today')->json('data.id');
    $rec = DailyActionRecommendation::find($recId);

    $res = $this->postJson("/api/v1/actions/{$recId}/feedback", [
        'feedback' => 'helpful',
        'body_note' => '今天有差',
    ])->assertCreated();

    expect($res->json('data.feedback'))->toBe('helpful');
    expect($res->json('data.recommendation.is_completed'))->toBeTrue();

    $protocol = UserActionProtocol::where('user_id', $this->user->id)
        ->where('action_key', $rec->action_key)
        ->first();
    expect($protocol)->not->toBeNull();
    expect($protocol->sample_size)->toBe(1);
    expect($protocol->effectiveness_score)->toBe(1.0);
});

it('rejects invalid feedback value', function () {
    $recId = $this->getJson('/api/v1/actions/today')->json('data.id');

    $this->postJson("/api/v1/actions/{$recId}/feedback", [
        'feedback' => 'awesome',
    ])->assertStatus(422);
});

it('returns 404 for other user recommendation', function () {
    $other = User::factory()->create();
    $rec = DailyActionRecommendation::create([
        'user_id' => $other->id,
        'recommended_on' => now()->toDateString(),
        'action_key' => 'menstrual_warm_belly_15min',
        'phase' => 'menstrual',
        'cycle_day' => 1,
    ]);

    $this->postJson("/api/v1/actions/{$rec->id}/feedback", ['feedback' => 'helpful'])
        ->assertStatus(404);
});

it('lists history limited by days', function () {
    DailyActionRecommendation::create([
        'user_id' => $this->user->id,
        'recommended_on' => now()->subDays(2)->toDateString(),
        'action_key' => 'any_water',
        'phase' => 'follicular',
        'cycle_day' => 4,
        'is_completed' => true,
    ]);
    DailyActionRecommendation::create([
        'user_id' => $this->user->id,
        'recommended_on' => now()->subDays(60)->toDateString(),
        'action_key' => 'any_breathe',
        'phase' => 'luteal',
        'cycle_day' => 20,
    ]);

    $res = $this->getJson('/api/v1/actions/history?days=30')->assertOk();
    expect(count($res->json('data')))->toBe(1);
});

it('returns free protocol view (top 1 per phase) for free user', function () {
    UserActionProtocol::create([
        'user_id' => $this->user->id,
        'phase' => 'luteal',
        'action_key' => 'luteal_journal_5min',
        'sample_size' => 5,
        'effectiveness_score' => 0.9,
        'last_calculated_at' => now(),
    ]);
    UserActionProtocol::create([
        'user_id' => $this->user->id,
        'phase' => 'luteal',
        'action_key' => 'luteal_cut_one_caffeine',
        'sample_size' => 3,
        'effectiveness_score' => 0.7,
        'last_calculated_at' => now(),
    ]);

    $res = $this->getJson('/api/v1/actions/protocol')->assertOk();
    expect($res->json('data.tier'))->toBe('free');
    // free 每 phase 只看到 1 個（top score）
    $luteal = collect($res->json('data.protocols'))->where('phase', 'luteal');
    expect($luteal->count())->toBe(1);
    expect($luteal->first()['action_key'])->toBe('luteal_journal_5min');
});

it('respects fatigue penalty — same type 7+ days suppressed', function () {
    // 過去 7 天填滿同 type=track 完成記錄（follicular_journal_intentions 是 track）
    for ($i = 1; $i <= 7; $i++) {
        DailyActionRecommendation::create([
            'user_id' => $this->user->id,
            'recommended_on' => now()->subDays($i)->toDateString(),
            'action_key' => 'follicular_journal_intentions',
            'phase' => 'follicular',
            'cycle_day' => 5,
            'is_completed' => true,
            'completed_at' => now()->subDays($i),
        ]);
    }

    $res = $this->getJson('/api/v1/actions/today')->assertOk();
    $card = (array) config('daily-actions.'.$res->json('data.action_key'));
    // 不應再推 track（有降權 0.3）
    expect($card['type'] ?? '')->not->toBe('track');
});

it('effectiveness score boosts ranking', function () {
    // 給 follicular_reach_old_friend 高分 → 應被選中
    UserActionProtocol::create([
        'user_id' => $this->user->id,
        'phase' => 'follicular',
        'action_key' => 'follicular_reach_old_friend',
        'sample_size' => 5,
        'effectiveness_score' => 1.0,
        'last_calculated_at' => now(),
    ]);

    // boost 0.3 vs jitter 0~0.1 → single-run 應穩定選中
    $key = $this->getJson('/api/v1/actions/today')->json('data.action_key');
    expect($key)->toBe('follicular_reach_old_friend');
});
