<?php

namespace App\Services\AI;

use App\Models\Cycle;
use App\Models\CycleSymptom;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * P4 個人化 insight 引擎。
 *
 * Phase 0-3：純規則 (PMS 模式辨識基於症狀頻次)
 * P4：接 py-service LLM endpoint 生成個人化文案
 *
 * 紅線：
 * - 所有 LLM 輸出必須過 LegalContentSanitizer
 * - 禁療效詞（改善 / 緩解 / 治療 / 排毒 / 調理...）
 * - 文案前綴一律「妳 / 朋友」，不寫「您 / 會員」
 */
class AICycleInsight
{
    public function detectPmsPattern(User $user): ?PmsPattern
    {
        $symptoms = CycleSymptom::where('user_id', $user->id)
            ->where('logged_on', '>=', now()->subMonths(6))
            ->get();

        if ($symptoms->count() < 6) {
            return null; // 樣本不足
        }

        $cycles = Cycle::where('user_id', $user->id)
            ->orderByDesc('start_date')
            ->limit(6)
            ->get();

        // 計算「經前 5 天內」出現的 tag 頻次
        $premenstrualTags = collect();
        foreach ($cycles as $cycle) {
            $start = $cycle->start_date;
            $window = $symptoms->filter(fn ($s) => $s->logged_on >= $start->copy()->subDays(5)
                && $s->logged_on < $start);
            foreach ($window as $sym) {
                foreach ($sym->tags ?? [] as $tag) {
                    $premenstrualTags->push($tag);
                }
            }
        }

        $freq = $premenstrualTags->countBy()->sortDesc();
        $top = $freq->take(3);
        if ($top->isEmpty()) {
            return null;
        }

        return new PmsPattern(
            sampleCycles: $cycles->count(),
            topSymptoms: $top->keys()->all(),
            symptomCounts: $top->all(),
            confidence: $cycles->count() >= 3 ? 'high' : 'low',
        );
    }

    public function dailyInsight(User $user, string $phase, ?int $cycleDay): string
    {
        // Phase 0: 規則 / hard-coded（沿用 DodoCheckinResponder 同 tone）
        return match ($phase) {
            'menstrual' => '經期保養是長期投資；今天記得補水跟休息，朵朵陪妳一起。',
            'follicular' => '能量回來了，這幾天適合做點新嘗試。',
            'ovulation' => '排卵期通常感覺最好，朵朵覺得妳今天會發光。',
            'luteal' => '黃體期會比較想吃甜跟容易煩躁，這是身體的訊號，不是妳的錯。',
            default => '今天朵朵在這裡。',
        };
    }
}
