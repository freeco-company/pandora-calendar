<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Calendar\BodyRhythmCalculator;
use App\Services\Calendar\CyclePredictor;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * P1-6 — 給朵朵 tab 顯示「今天朵朵想說的話」(基於當下 phase + cycle_day + 用戶 streak)。
 *
 * Hard-coded mapping，不接 LLM（P4 才接）。文案必過 sanitizer（pandora-shared）。
 */
class DailyReminderController extends Controller
{
    public function __construct(
        private readonly CyclePredictor $predictor,
        private readonly BodyRhythmCalculator $calc,
    ) {}

    public function show(Request $request): JsonResponse
    {
        $u = $request->user();
        $today = CarbonImmutable::today();
        $prediction = $this->predictor->predict($u->id, $today);
        $rhythm = $this->calc->compute($prediction, $today);

        $phase = $rhythm->phase;
        $cycleDay = $rhythm->cycleDay;

        $tip = $this->buildTip($phase, $cycleDay);

        return response()->json([
            'data' => [
                'phase' => $phase,
                'cycle_day' => $cycleDay,
                'days_until_next_period' => $rhythm->daysUntilNextPeriod,
                'icon' => $tip['icon'],
                'title' => $tip['title'],
                'body' => $tip['body'],
                'tone' => $tip['tone'],
            ],
        ]);
    }

    /**
     * @return array{icon:string,title:string,body:string,tone:string}
     */
    private function buildTip(string $phase, ?int $cycleDay): array
    {
        return match ($phase) {
            'menstrual' => [
                'icon' => '🌙',
                'title' => '經期照顧自己',
                'body' => '今天身體在做大工程，多喝溫水、別硬撐。如果不舒服就早點休息，朵朵陪妳。',
                'tone' => 'sakura',
            ],
            'follicular' => [
                'icon' => '🌱',
                'title' => '能量回來了',
                'body' => '濾泡期狀態通常會慢慢變好，這幾天適合計畫想做的事。記得記下身體狀態，朵朵會跟著一起學。',
                'tone' => 'cream',
            ],
            'ovulation' => [
                'icon' => '✨',
                'title' => '排卵期',
                'body' => '這是週期裡能量最高的時段，朵朵覺得妳今天會發光。如果有計畫懷孕的朋友，這幾天是黃金窗口。',
                'tone' => 'peach',
            ],
            'luteal' => [
                'icon' => '🍃',
                'title' => '黃體期溫柔對待自己',
                'body' => $cycleDay !== null && $cycleDay >= 24
                    ? '經期前一週，情緒起伏 / 腹脹 / 嗜甜都很正常，不是妳的錯。可以多吃溫熱食物，少喝冰飲。'
                    : '黃體期身體比較敏感，朵朵建議妳放慢一點，記下任何身體訊號，幫朵朵更懂妳。',
                'tone' => 'lavender',
            ],
            default => [
                'icon' => '🌸',
                'title' => '朵朵想跟妳打招呼',
                'body' => '記下今天身體的感覺，幾次以後朵朵就能更懂妳。',
                'tone' => 'cream',
            ],
        };
    }
}
