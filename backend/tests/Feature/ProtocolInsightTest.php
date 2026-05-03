<?php

use App\Models\ActionFeedback;
use App\Models\Cycle;
use App\Models\CycleSymptom;
use App\Models\DailyActionRecommendation;
use App\Models\ProtocolInsightDismissed;
use App\Models\User;
use App\Models\UserActionProtocol;
use App\Services\Action\ProtocolInsightSurfacer;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    Sanctum::actingAs($this->user);
});

it('returns null when no protocol data', function () {
    $svc = app(ProtocolInsightSurfacer::class);
    expect($svc->activeFor($this->user->id))->toBeNull();
});

it('does not surface action_works when sample_size < 5', function () {
    UserActionProtocol::create([
        'user_id' => $this->user->id,
        'phase' => 'luteal',
        'action_key' => 'menstrual_warm_belly_15min',
        'sample_size' => 4,
        'effectiveness_score' => 0.9,
    ]);

    $svc = app(ProtocolInsightSurfacer::class);
    expect($svc->activeFor($this->user->id))->toBeNull();
});

it('surfaces specific_action_works when sample_size >= 5 and score >= 0.7', function () {
    UserActionProtocol::create([
        'user_id' => $this->user->id,
        'phase' => 'luteal',
        'action_key' => 'menstrual_warm_belly_15min',
        'sample_size' => 6,
        'effectiveness_score' => 0.85,
    ]);

    $svc = app(ProtocolInsightSurfacer::class);
    $r = $svc->activeFor($this->user->id);

    expect($r)->not->toBeNull();
    expect($r['source'])->toBe('specific_action_works');
    expect($r['insight_key'])->toBe('action_works:luteal:menstrual_warm_belly_15min');
});

it('takes highest score when multiple insights available', function () {
    // specific_action_works baseline ≈ 1.85+
    UserActionProtocol::create([
        'user_id' => $this->user->id,
        'phase' => 'luteal',
        'action_key' => 'menstrual_warm_belly_15min',
        'sample_size' => 10,
        'effectiveness_score' => 0.95,
    ]);

    // type_responds baseline ≈ 0.84
    for ($i = 0; $i < 10; $i++) {
        $rec = DailyActionRecommendation::create([
            'user_id' => $this->user->id,
            'recommended_on' => CarbonImmutable::today()->subDays($i + 1)->toDateString(),
            'action_key' => 'menstrual_warm_water', // type=eat
            'phase' => 'menstrual',
            'cycle_day' => 1,
            'is_completed' => true,
        ]);
        ActionFeedback::create([
            'user_id' => $this->user->id,
            'recommendation_id' => $rec->id,
            'feedback' => 'helpful',
            'submitted_at' => now(),
        ]);
    }

    $svc = app(ProtocolInsightSurfacer::class);
    $r = $svc->activeFor($this->user->id);
    expect($r['source'])->toBe('specific_action_works');
});

it('respects dismiss cooldown — same key not surfaced within 7 days', function () {
    UserActionProtocol::create([
        'user_id' => $this->user->id,
        'phase' => 'luteal',
        'action_key' => 'menstrual_warm_belly_15min',
        'sample_size' => 6,
        'effectiveness_score' => 0.85,
    ]);

    $svc = app(ProtocolInsightSurfacer::class);
    $first = $svc->activeFor($this->user->id);
    expect($first)->not->toBeNull();

    $svc->dismiss($this->user->id, $first['insight_key']);

    expect($svc->activeFor($this->user->id))->toBeNull();
});

it('re-surfaces dismissed key after 7 days', function () {
    UserActionProtocol::create([
        'user_id' => $this->user->id,
        'phase' => 'luteal',
        'action_key' => 'menstrual_warm_belly_15min',
        'sample_size' => 6,
        'effectiveness_score' => 0.85,
    ]);

    $svc = app(ProtocolInsightSurfacer::class);
    $first = $svc->activeFor($this->user->id);

    // 8 天前 dismiss
    ProtocolInsightDismissed::create([
        'user_id' => $this->user->id,
        'insight_key' => $first['insight_key'],
        'dismissed_at' => CarbonImmutable::now()->subDays(8),
    ]);

    expect($svc->activeFor($this->user->id))->not->toBeNull();
});

it('endpoint dismisses and returns 201', function () {
    UserActionProtocol::create([
        'user_id' => $this->user->id,
        'phase' => 'luteal',
        'action_key' => 'menstrual_warm_belly_15min',
        'sample_size' => 6,
        'effectiveness_score' => 0.85,
    ]);

    $key = $this->getJson('/api/v1/protocol-insights/active')
        ->assertOk()
        ->json('data.insight_key');
    expect($key)->toBeString();

    $this->postJson("/api/v1/protocol-insights/{$key}/dismiss")
        ->assertCreated()
        ->assertJsonPath('data.dismissed', true);

    $this->getJson('/api/v1/protocol-insights/active')
        ->assertOk()
        ->assertJsonPath('data', null);
});

it('daily action endpoint includes protocol_insight field', function () {
    Cycle::create([
        'user_id' => $this->user->id,
        'start_date' => now()->subDays(5)->toDateString(),
        'end_date' => now()->subDays(1)->toDateString(),
    ]);

    $res = $this->getJson('/api/v1/actions/today')->assertOk();
    expect($res->json())->toHaveKey('protocol_insight');
});
