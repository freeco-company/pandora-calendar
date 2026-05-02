<?php

namespace App\Services\Reports;

use App\Models\Cycle;
use App\Models\CycleSymptom;
use App\Models\DodoCheckin;
use App\Models\HealthSample;
use App\Models\User;
use App\Models\WeekReport;
use App\Services\AI\AICycleInsight;
use App\Services\Calendar\BodyRhythmCalculator;
use App\Services\Calendar\CyclePredictor;
use Carbon\CarbonImmutable;

/**
 * 每週朵朵報告 — Premium 功能（P5+）。
 *
 * 內容：
 * - 本週 phase 分佈
 * - 症狀統計
 * - check-in 心情分佈
 * - 朵朵 AI 一句總結（過 sanitizer）
 * - 圖卡可分享 / PDF 下載
 *
 * Phase 5 first cut: JSON summary。PDF 之後接 dompdf / browsershot 生成。
 */
class WeekReportGenerator
{
    public function __construct(
        private readonly CyclePredictor $predictor,
        private readonly BodyRhythmCalculator $rhythmCalc,
        private readonly AICycleInsight $insight,
    ) {}

    public function generate(User $user, ?CarbonImmutable $weekStart = null): WeekReport
    {
        $weekStart ??= CarbonImmutable::today()->startOfWeek();
        $weekEnd = $weekStart->endOfWeek();

        $cycles = Cycle::where('user_id', $user->id)
            ->whereBetween('start_date', [$weekStart, $weekEnd])
            ->get();

        $symptoms = CycleSymptom::where('user_id', $user->id)
            ->whereBetween('logged_on', [$weekStart, $weekEnd])
            ->get();

        $checkins = DodoCheckin::where('user_id', $user->id)
            ->whereBetween('checked_on', [$weekStart, $weekEnd])
            ->get();

        $health = HealthSample::where('user_id', $user->id)
            ->whereBetween('recorded_on', [$weekStart, $weekEnd])
            ->get();

        $prediction = $this->predictor->predict($user->id, $weekEnd);
        $rhythm = $this->rhythmCalc->compute($prediction, $weekEnd);

        $tagsCount = $symptoms->pluck('tags')->flatten()->countBy()->sortDesc()->take(3)->all();
        $moodDist = $checkins->pluck('mood')->countBy()->all();

        $summary = [
            'week_start' => $weekStart->toDateString(),
            'week_end' => $weekEnd->toDateString(),
            'cycles_started' => $cycles->count(),
            'symptoms_logged' => $symptoms->count(),
            'top_symptom_tags' => $tagsCount,
            'checkins' => $checkins->count(),
            'mood_distribution' => $moodDist,
            'health_samples' => $health->count(),
            'phase_at_week_end' => $rhythm->phase,
            'cycle_day_at_week_end' => $rhythm->cycleDay,
            'dodo_summary' => $this->insight->dailyInsight($user, $rhythm->phase, $rhythm->cycleDay),
        ];

        return WeekReport::updateOrCreate(
            ['user_id' => $user->id, 'week_start' => $weekStart->toDateString()],
            ['summary' => $summary, 'generated_at' => now()],
        );
    }
}
