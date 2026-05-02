<?php

use App\Models\Cycle;
use App\Models\CycleSymptom;
use App\Models\User;
use App\Services\AI\AICycleInsight;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('returns null pattern with insufficient samples', function () {
    $user = User::factory()->create();

    expect(app(AICycleInsight::class)->detectPmsPattern($user))->toBeNull();
});

it('detects top symptoms across multiple premenstrual windows', function () {
    $user = User::factory()->create();
    $today = CarbonImmutable::today();

    // 3 cycles, each with 5 days of premenstrual symptoms
    for ($c = 0; $c < 3; $c++) {
        $start = $today->subDays(28 * ($c + 1));
        Cycle::create([
            'user_id' => $user->id,
            'start_date' => $start->toDateString(),
        ]);
        for ($d = 1; $d <= 5; $d++) {
            CycleSymptom::create([
                'user_id' => $user->id,
                'logged_on' => $start->subDays($d)->toDateString(),
                'tags' => ['craving_sweet', 'mood_swing'],
            ]);
        }
    }

    $pattern = app(AICycleInsight::class)->detectPmsPattern($user);

    expect($pattern)->not->toBeNull();
    expect($pattern->topSymptoms)->toContain('craving_sweet');
    expect($pattern->topSymptoms)->toContain('mood_swing');
    expect($pattern->confidence)->toBe('high');
});
