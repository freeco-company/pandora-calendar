<?php

use App\Models\User;
use App\Services\Calendar\Streak\DailyLoginStreakService;
use App\Services\Calendar\Streak\StreakMilestoneRewardService;
use App\Services\Gamification\GamificationPublisher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    Carbon::setTestNow(Carbon::create(2026, 1, 1, 9, 0, 0, 'Asia/Taipei'));
});

afterEach(function () {
    Carbon::setTestNow();
});

/**
 * Tracking publisher stub — captures publish() calls without throwing so
 * we can assert the payload and that fail-soft skip works for unknown kinds.
 */
function makeTrackingPublisher(bool $throw = false): GamificationPublisher
{
    return new class($throw) implements GamificationPublisher
    {
        /** @var list<array{event:string, ctx:array, idem:?string}> */
        public array $calls = [];

        public function __construct(public bool $throw) {}

        public function publish(\App\Models\User $user, string $eventKind, array $context = [], ?string $idempotencyKey = null): void
        {
            $this->calls[] = ['event' => $eventKind, 'ctx' => $context, 'idem' => $idempotencyKey];
            if ($this->throw) {
                throw new \RuntimeException('simulated catalog miss');
            }
        }
    };
}

/**
 * Walk N consecutive days through DailyLoginStreakService so we hit the real
 * milestone path (and the cascade — 1, 3, 7, ... fire as we cross them).
 *
 * @return array<string, mixed> the final recordLogin() return
 */
function walkDays(User $user, int $days): array
{
    $svc = app(DailyLoginStreakService::class);
    $start = Carbon::create(2026, 1, 1, 9, 0, 0, 'Asia/Taipei');
    $r = [];
    for ($d = 0; $d < $days; $d++) {
        Carbon::setTestNow($start->copy()->addDays($d));
        $r = $svc->recordLogin($user);
    }

    return $r;
}

it('non-milestone day returns empty reward (no outfit, no xp)', function () {
    $user = User::factory()->create();
    $rewards = app(StreakMilestoneRewardService::class);

    $r = $rewards->unlockForMilestone($user, 5);

    expect($r['outfit_unlocked'])->toBeNull()
        ->and($r['cards_unlocked'])->toBe([])
        ->and($r['xp_bonus'])->toBe(0)
        ->and($r['total_xp_after'])->toBeNull();
});

it('streak=1 unlocks initial card without outfit', function () {
    $user = User::factory()->create();
    $rewards = app(StreakMilestoneRewardService::class);

    $r = $rewards->unlockForMilestone($user, 1);

    expect($r['outfit_unlocked'])->toBeNull()
        ->and($r['cards_unlocked'])->toHaveCount(1)
        ->and($r['cards_unlocked'][0]['code'])->toBe('streak_1')
        ->and($r['cards_unlocked'][0]['label'])->toBe('初心徽章')
        ->and($r['xp_bonus'])->toBe(0);
});

it('streak=3 unlocks sparkle_pin outfit (real catalog code)', function () {
    $user = User::factory()->create();
    $rewards = app(StreakMilestoneRewardService::class);

    $r = $rewards->unlockForMilestone($user, 3);

    expect($r['outfit_unlocked'])->toBe('sparkle_pin')
        ->and($r['outfit_skipped'])->toBeNull();

    $user->refresh();
    $owned = $user->outfit_state['owned'] ?? [];
    expect($owned)->toContain('sparkle_pin');
});

it('streak=7 unlocks sakura outfit', function () {
    $user = User::factory()->create();
    $rewards = app(StreakMilestoneRewardService::class);

    $r = $rewards->unlockForMilestone($user, 7);

    expect($r['outfit_unlocked'])->toBe('sakura');
    $user->refresh();
    expect($user->outfit_state['owned'] ?? [])->toContain('sakura');
});

it('streak=14 unlocks star_clip outfit', function () {
    $user = User::factory()->create();
    $r = app(StreakMilestoneRewardService::class)->unlockForMilestone($user, 14);
    expect($r['outfit_unlocked'])->toBe('star_clip');
});

it('streak=21 grants xp bonus 50 with no outfit', function () {
    $user = User::factory()->create(['total_xp' => 100]);
    $rewards = app(StreakMilestoneRewardService::class);

    $r = $rewards->unlockForMilestone($user, 21);

    expect($r['outfit_unlocked'])->toBeNull()
        ->and($r['xp_bonus'])->toBe(50)
        ->and($r['total_xp_after'])->toBe(150);

    $user->refresh();
    expect((int) $user->total_xp)->toBe(150);
});

it('streak=30 unlocks starry_cape + 100 xp bonus', function () {
    $user = User::factory()->create(['total_xp' => 200]);
    $rewards = app(StreakMilestoneRewardService::class);

    $r = $rewards->unlockForMilestone($user, 30);

    expect($r['outfit_unlocked'])->toBe('starry_cape')
        ->and($r['xp_bonus'])->toBe(100)
        ->and($r['total_xp_after'])->toBe(300);

    $user->refresh();
    expect($user->outfit_state['owned'] ?? [])->toContain('starry_cape');
});

it('streak=60 unlocks moon_tiara without xp bonus', function () {
    $user = User::factory()->create();
    $r = app(StreakMilestoneRewardService::class)->unlockForMilestone($user, 60);
    expect($r['outfit_unlocked'])->toBe('moon_tiara')
        ->and($r['xp_bonus'])->toBe(0);
});

it('streak=100 unlocks angel_wings + 300 xp bonus', function () {
    $user = User::factory()->create(['total_xp' => 0]);
    $r = app(StreakMilestoneRewardService::class)->unlockForMilestone($user, 100);

    expect($r['outfit_unlocked'])->toBe('angel_wings')
        ->and($r['xp_bonus'])->toBe(300)
        ->and($r['total_xp_after'])->toBe(300);
});

it('outfit unlock is idempotent — already owned returns null (no double-toast)', function () {
    $user = User::factory()->create([
        'outfit_state' => ['owned' => ['sakura'], 'equipped' => null],
    ]);
    $rewards = app(StreakMilestoneRewardService::class);

    $r = $rewards->unlockForMilestone($user, 7);

    // Already owned → outfit_unlocked is null (frontend shouldn't celebrate again)
    expect($r['outfit_unlocked'])->toBeNull()
        ->and($r['outfit_skipped'])->toBeNull();
});

it('preserves other outfits already in owned list when merging', function () {
    $user = User::factory()->create([
        'outfit_state' => ['owned' => ['ribbon', 'flower_crown'], 'equipped' => 'ribbon'],
    ]);
    $rewards = app(StreakMilestoneRewardService::class);

    $rewards->unlockForMilestone($user, 3);

    $user->refresh();
    $owned = $user->outfit_state['owned'] ?? [];
    expect($owned)->toContain('ribbon')
        ->and($owned)->toContain('flower_crown')
        ->and($owned)->toContain('sparkle_pin')
        // equipped untouched (only user toggles)
        ->and($user->outfit_state['equipped'] ?? null)->toBe('ribbon');
});

it('publishes calendar.streak_milestone_unlocked event with correct payload', function () {
    $publisher = makeTrackingPublisher();
    app()->instance(GamificationPublisher::class, $publisher);

    $user = User::factory()->create(['identity_uuid' => 'uuid-aaa']);
    app(StreakMilestoneRewardService::class)->unlockForMilestone($user, 30);

    expect($publisher->calls)->toHaveCount(1)
        ->and($publisher->calls[0]['event'])->toBe('calendar.streak_milestone_unlocked')
        ->and($publisher->calls[0]['ctx']['streak'])->toBe(30)
        ->and($publisher->calls[0]['ctx']['outfit_code'])->toBe('starry_cape')
        ->and($publisher->calls[0]['ctx']['xp_bonus'])->toBe(100)
        ->and($publisher->calls[0]['ctx']['card_codes'])->toBe(['streak_30'])
        ->and($publisher->calls[0]['idem'])->toContain('uuid-aaa');
});

it('skips publish when identity_uuid is empty (mirror not yet ready)', function () {
    $publisher = makeTrackingPublisher();
    app()->instance(GamificationPublisher::class, $publisher);

    $user = User::factory()->create(['identity_uuid' => null]);
    app(StreakMilestoneRewardService::class)->unlockForMilestone($user, 7);

    expect($publisher->calls)->toBe([]);
});

it('publish failure does not break milestone reward flow (fail-soft)', function () {
    $publisher = makeTrackingPublisher(throw: true);
    app()->instance(GamificationPublisher::class, $publisher);

    $user = User::factory()->create(['identity_uuid' => 'uuid-bbb']);
    $r = app(StreakMilestoneRewardService::class)->unlockForMilestone($user, 7);

    // Outfit still unlocked locally even though publish threw
    expect($r['outfit_unlocked'])->toBe('sakura');
    $user->refresh();
    expect($user->outfit_state['owned'] ?? [])->toContain('sakura');
});

it('end-to-end: walking 30 days fires milestone at each tier and accumulates xp', function () {
    $user = User::factory()->create(['identity_uuid' => 'uuid-walk', 'total_xp' => 0]);
    // Use noop publisher (default in test env): no throws, no asserts on it.

    $r = walkDays($user, 30);

    expect($r['streak'])->toBe(30)
        ->and($r['is_milestone'])->toBeTrue()
        ->and($r['unlocks'])->not->toBeNull()
        ->and($r['unlocks']['outfit_unlocked'])->toBe('starry_cape')
        ->and($r['unlocks']['xp_bonus'])->toBe(100);

    // Cumulative xp from 21 (50) + 30 (100) = 150
    $user->refresh();
    expect((int) $user->total_xp)->toBe(150);

    // All five outfit unlocks should now be in owned (3 / 7 / 14 / 30; 60/100 not yet)
    $owned = $user->outfit_state['owned'] ?? [];
    expect($owned)->toContain('sparkle_pin')
        ->and($owned)->toContain('sakura')
        ->and($owned)->toContain('star_clip')
        ->and($owned)->toContain('starry_cape');
});

it('GET /api/streak/today exposes unlocks payload at milestone day', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    // Day 1 is itself a milestone (streak=1 in MILESTONES list)
    $resp = $this->getJson('/api/streak/today');

    $resp->assertOk()
        ->assertJsonPath('current_streak', 1)
        ->assertJsonPath('is_milestone', true)
        ->assertJsonPath('unlocks.cards_unlocked.0.code', 'streak_1')
        ->assertJsonPath('unlocks.outfit_unlocked', null);
});

it('GET /api/streak/today unlocks is null on non-first-today repeat call', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $this->getJson('/api/streak/today')->assertOk();
    // Second call same day: is_first_today=false → no milestone → unlocks should be null
    $resp = $this->getJson('/api/streak/today');

    $resp->assertOk()
        ->assertJsonPath('is_first_today', false)
        ->assertJsonPath('is_milestone', false)
        ->assertJsonPath('unlocks', null);
});
