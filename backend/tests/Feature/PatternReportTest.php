<?php

use App\Models\ActionFeedback;
use App\Models\Cycle;
use App\Models\CyclePatternReport;
use App\Models\CycleSymptom;
use App\Models\DailyActionRecommendation;
use App\Models\User;
use App\Services\Reports\PatternReportGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    Sanctum::actingAs($this->user);

    // 兩個 cycle：上一個（要被 generate report）+ 這個月（剛開始）
    $this->prevCycle = Cycle::create([
        'user_id' => $this->user->id,
        'start_date' => now()->subDays(60)->toDateString(),
        'end_date' => now()->subDays(56)->toDateString(),
    ]);
    $this->currentCycle = Cycle::create([
        'user_id' => $this->user->id,
        'start_date' => now()->subDays(30)->toDateString(),
        'end_date' => now()->subDays(26)->toDateString(),
    ]);
});

function makeRecAndFeedback(int $userId, string $key, string $phase, string $feedback, string $on): void
{
    $rec = DailyActionRecommendation::create([
        'user_id' => $userId,
        'recommended_on' => $on,
        'action_key' => $key,
        'phase' => $phase,
        'cycle_day' => 5,
        'is_completed' => true,
        'completed_at' => $on,
    ]);
    ActionFeedback::create([
        'user_id' => $userId,
        'recommendation_id' => $rec->id,
        'feedback' => $feedback,
        'submitted_at' => $on,
    ]);
}

it('generates report with top helpful and unhelpful actions', function () {
    $on1 = now()->subDays(58)->toDateString();
    $on2 = now()->subDays(57)->toDateString();
    $on3 = now()->subDays(55)->toDateString();

    makeRecAndFeedback($this->user->id, 'menstrual_warm_belly_15min', 'menstrual', 'helpful', $on1);
    makeRecAndFeedback($this->user->id, 'menstrual_warm_belly_15min', 'menstrual', 'helpful', $on2);
    makeRecAndFeedback($this->user->id, 'menstrual_easy_walk_10min', 'menstrual', 'unhelpful', $on3);

    /** @var PatternReportGenerator $gen */
    $gen = app(PatternReportGenerator::class);
    $report = $gen->generateForCycle($this->user->id, $this->prevCycle->id);

    expect($report)->toBeInstanceOf(CyclePatternReport::class);
    expect($report->top_actions['top_helpful'][0]['action_key'])->toBe('menstrual_warm_belly_15min');
    expect((float) $report->top_actions['top_helpful'][0]['score'])->toBe(1.0);
    expect($report->top_actions['top_unhelpful'][0]['action_key'])->toBe('menstrual_easy_walk_10min');
    expect($report->dodo_message)->toBeString()->not->toBe('');
});

it('is idempotent — second call returns same row', function () {
    /** @var PatternReportGenerator $gen */
    $gen = app(PatternReportGenerator::class);
    $first = $gen->generateForCycle($this->user->id, $this->prevCycle->id);
    $second = $gen->generateForCycle($this->user->id, $this->prevCycle->id);

    expect($first->id)->toBe($second->id);
    expect(CyclePatternReport::count())->toBe(1);
});

it('computes vs_previous trend when prior cycle exists', function () {
    // 給 prev cycle 之前再加一個更早的 cycle（需要 vs_previous 有比較）
    $earlier = Cycle::create([
        'user_id' => $this->user->id,
        'start_date' => now()->subDays(90)->toDateString(),
        'end_date' => now()->subDays(86)->toDateString(),
    ]);

    // earlier cycle 期間 5 個 symptom
    for ($i = 0; $i < 5; $i++) {
        CycleSymptom::create([
            'user_id' => $this->user->id,
            'logged_on' => now()->subDays(85 - $i)->toDateString(),
            'tags' => ['cramp'],
            'mood' => 'low',
        ]);
    }
    // prev cycle 期間 1 個 symptom（變少 → better）
    CycleSymptom::create([
        'user_id' => $this->user->id,
        'logged_on' => now()->subDays(58)->toDateString(),
        'tags' => ['fatigue'],
        'mood' => 'okay',
    ]);

    /** @var PatternReportGenerator $gen */
    $gen = app(PatternReportGenerator::class);
    $report = $gen->generateForCycle($this->user->id, $this->prevCycle->id);

    expect($report->vs_previous)->not->toBeNull();
    expect($report->vs_previous['symptom_trend'])->toBe('better');
    expect($report->vs_previous['previous_symptom_count'])->toBe(5);
    expect($report->vs_previous['current_symptom_count'])->toBe(1);
});

it('returns null vs_previous when no prior cycle', function () {
    // 把更早的 prev 砍掉，currentCycle 變成第一個
    $this->prevCycle->delete();
    $this->currentCycle->delete();

    $only = Cycle::create([
        'user_id' => $this->user->id,
        'start_date' => now()->subDays(30)->toDateString(),
        'end_date' => now()->subDays(26)->toDateString(),
    ]);

    /** @var PatternReportGenerator $gen */
    $gen = app(PatternReportGenerator::class);
    $report = $gen->generateForCycle($this->user->id, $only->id);

    expect($report->vs_previous)->toBeNull();
});

it('GET /pattern-report/latest returns null gracefully when no report', function () {
    $res = $this->getJson('/api/v1/pattern-report/latest')->assertOk();
    expect($res->json('data'))->toBeNull();
    expect($res->json('message'))->toBeString();
});

it('GET /pattern-report/latest returns generated report', function () {
    /** @var PatternReportGenerator $gen */
    $gen = app(PatternReportGenerator::class);
    $gen->generateForCycle($this->user->id, $this->prevCycle->id);

    $res = $this->getJson('/api/v1/pattern-report/latest')->assertOk();
    expect($res->json('data.cycle_id'))->toBe($this->prevCycle->id);
    expect($res->json('data.dodo_message'))->toBeString();
});

it('GET /pattern-report/list returns array', function () {
    /** @var PatternReportGenerator $gen */
    $gen = app(PatternReportGenerator::class);
    $gen->generateForCycle($this->user->id, $this->prevCycle->id);

    $res = $this->getJson('/api/v1/pattern-report/list')->assertOk();
    expect(count($res->json('data')))->toBe(1);
});

it('console command generates report for users whose cycle just ended', function () {
    // 造一個今天剛開新 cycle 的 user（current 的 start_date 改成今天）
    $this->currentCycle->update(['start_date' => now()->toDateString()]);

    $this->artisan('pandora:pattern-reports:generate')
        ->assertSuccessful();

    expect(CyclePatternReport::where('cycle_id', $this->prevCycle->id)->exists())->toBeTrue();
});
