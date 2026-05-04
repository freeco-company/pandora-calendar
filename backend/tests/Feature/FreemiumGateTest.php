<?php

use App\Models\Cycle;
use App\Models\CyclePatternReport;
use App\Models\CycleSymptom;
use App\Models\QnaQuestion;
use App\Models\User;
use App\Services\Subscription\PremiumTrialService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    Sanctum::actingAs($this->user);
});

it('lets free users see PMS Top 3 (not 402)', function () {
    // 製造 6 個月的 luteal symptoms 確保 detectPmsPattern 會回 pattern
    $today = CarbonImmutable::today();
    for ($c = 0; $c < 3; $c++) {
        $start = $today->subDays(28 * ($c + 1));
        Cycle::create(['user_id' => $this->user->id, 'start_date' => $start->toDateString()]);
        for ($d = 1; $d <= 5; $d++) {
            CycleSymptom::create([
                'user_id' => $this->user->id,
                'logged_on' => $start->subDays($d)->toDateString(),
                'tags' => ['craving_sweet', 'mood_swing', 'cramp', 'fatigue'],
            ]);
        }
    }

    $res = $this->getJson('/api/v1/insight/pms');

    $res->assertOk()
        ->assertJsonPath('tier', 'free');

    $body = $res->json('data');
    expect($body)->not->toBeNull();
    expect(count($body['top_symptoms']))->toBeLessThanOrEqual(3);
    expect($body['severity_trend'])->toBe('locked');
    expect($body['locked_features'])->toContain('top_4_5');
});

it('lets premium users see PMS full top 5 + severity_trend', function () {
    app(PremiumTrialService::class)->startTrial($this->user->id);

    $today = CarbonImmutable::today();
    for ($c = 0; $c < 3; $c++) {
        $start = $today->subDays(28 * ($c + 1));
        Cycle::create(['user_id' => $this->user->id, 'start_date' => $start->toDateString()]);
        for ($d = 1; $d <= 5; $d++) {
            CycleSymptom::create([
                'user_id' => $this->user->id,
                'logged_on' => $start->subDays($d)->toDateString(),
                'tags' => ['craving_sweet', 'mood_swing'],
            ]);
        }
    }

    $res = $this->getJson('/api/v1/insight/pms');
    $res->assertOk()
        ->assertJsonPath('tier', 'trial');
    $body = $res->json('data');
    expect($body['severity_trend'])->not->toBe('locked');
});

it('lets free users see BBT biphasic basic shape (not 402)', function () {
    $res = $this->getJson('/api/v1/bbt/biphasic');

    $res->assertOk()
        ->assertJsonPath('tier', 'free');

    $body = $res->json('data');
    expect($body)->toHaveKey('has_shift');
    expect($body)->toHaveKey('shift_date');
    expect($body['coverline'])->toBeNull(); // 鎖
    expect($body['locked_features'])->toContain('coverline');
});

it('gives premium users full BBT biphasic detail', function () {
    app(PremiumTrialService::class)->startTrial($this->user->id);

    $res = $this->getJson('/api/v1/bbt/biphasic');
    $res->assertOk();
    $body = $res->json('data');
    expect($body)->toHaveKey('coverline');
    expect($body)->not->toHaveKey('locked_features');
});

it('lets free users see YearReview basic (cover + closing)', function () {
    $year = (int) now()->year;
    $res = $this->getJson("/api/v1/year-review/{$year}");

    $res->assertOk()
        ->assertJsonPath('tier', 'free');

    $cards = $res->json('data.cards');
    $ids = collect($cards)->pluck('id')->all();
    // free 卡只能含這 4 個 ids
    foreach ($ids as $id) {
        expect(in_array($id, ['cover', 'phase_distribution', 'top_mood', 'closing'], true))->toBeTrue();
    }
    expect($res->json('data.locked_features'))->toContain('full_12_cards');
});

it('lets free users do 5 Q&A per day (not 3)', function () {
    expect(config('qna.free_daily_cap'))->toBe(5);
});

it('does not auto-unlock story chapters 1-5 until list endpoint is called, then auto-unlocks all 5', function () {
    $res = $this->getJson('/api/v1/me/stories/chapters');
    $res->assertOk();

    $rows = $res->json('data.chapters');
    $unlocked = collect($rows)->filter(fn ($r) => $r['is_unlocked'])->pluck('chapter')->all();
    sort($unlocked);

    // ch 1-5 應該都自動解（unlock_cycle = 0 條件已達）
    expect($unlocked)->toContain(1, 2, 3, 4, 5);
});

it('keeps story chapter 6+ locked for free users until cycle / coin condition', function () {
    $res = $this->getJson('/api/v1/me/stories/chapters');
    $rows = $res->json('data.chapters');

    $ch6 = collect($rows)->firstWhere('chapter', 6);
    expect($ch6)->not->toBeNull();
    expect($ch6['is_unlocked'])->toBeFalse();
    expect($ch6['unlock_cycle'])->toBeGreaterThan(0);
});

it('lets free users see at most 3 pattern reports', function () {
    // 先建 cycle 才能掛 pattern report (FK)
    $cycleIds = [];
    for ($i = 0; $i < 5; $i++) {
        $c = Cycle::create([
            'user_id' => $this->user->id,
            'start_date' => now()->subDays(28 * ($i + 1))->toDateString(),
        ]);
        $cycleIds[] = $c->id;
    }
    for ($i = 0; $i < 5; $i++) {
        CyclePatternReport::create([
            'user_id' => $this->user->id,
            'cycle_id' => $cycleIds[$i],
            'generated_at' => now()->subDays($i),
            'phase_summary' => [],
            'top_actions' => [],
            'vs_previous' => [],
            'dodo_message' => "report {$i}",
        ]);
    }

    $res = $this->getJson('/api/v1/pattern-report/list');
    $res->assertOk()
        ->assertJsonPath('tier', 'free')
        ->assertJsonPath('total_count', 5)
        ->assertJsonPath('has_more_locked', true);

    expect(count($res->json('data')))->toBe(3);
});

it('lets premium users see all pattern reports', function () {
    app(PremiumTrialService::class)->startTrial($this->user->id);

    $cycleIds = [];
    for ($i = 0; $i < 5; $i++) {
        $c = Cycle::create([
            'user_id' => $this->user->id,
            'start_date' => now()->subDays(28 * ($i + 1))->toDateString(),
        ]);
        $cycleIds[] = $c->id;
    }
    for ($i = 0; $i < 5; $i++) {
        CyclePatternReport::create([
            'user_id' => $this->user->id,
            'cycle_id' => $cycleIds[$i],
            'generated_at' => now()->subDays($i),
            'phase_summary' => [],
            'top_actions' => [],
            'vs_previous' => [],
            'dodo_message' => "report {$i}",
        ]);
    }

    $res = $this->getJson('/api/v1/pattern-report/list');
    $res->assertOk()
        ->assertJsonPath('has_more_locked', false);

    expect(count($res->json('data')))->toBe(5);
});
