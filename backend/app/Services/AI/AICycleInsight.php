<?php

namespace App\Services\AI;

use App\Models\Cycle;
use App\Models\CycleSymptom;
use App\Models\User;
use Illuminate\Support\Collection;
use Pandora\Shared\Compliance\LegalContentSanitizer;

/**
 * P4 個人化 insight 引擎。
 *
 * Phase 0-3：純規則 (PMS 模式辨識基於症狀頻次)
 * P0 升級（2026-05-03）：
 *   - 鎖定「黃體期」窗口（不是 generic「經前 5 天」）
 *   - 對 Top 3 tag 給朵朵建議文案（過 sanitizer）
 *   - severity_trend：最近 3 週期 vs 之前 3 週期同 tag count 比較
 * P4：接 py-service LLM endpoint 生成個人化文案
 *
 * 紅線：
 * - 所有輸出必須過 LegalContentSanitizer
 * - 禁療效詞（改善 / 緩解 / 治療 / 排毒 / 調理...）
 * - 文案前綴一律「妳 / 朋友」，不寫「您 / 會員」
 */
class AICycleInsight
{
    /**
     * 朵朵 voice 對 Top tag 的建議文案。中性、不療效、不命令。
     * 用 const map，PR 加新 tag 時這裡擴；缺的話走 fallback「不如試試...」。
     */
    private const TAG_SUGGESTIONS = [
        'cramp'         => '經痛時不如熱敷小腹、深呼吸幾次，朵朵發現對自己溫柔通常會比較好過。',
        'headache'      => '頭痛常跟睡眠 / 水分有關，妳可以試試早一點關螢幕。',
        'fatigue'       => '黃體期容易累是身體節律。不如今天少安排一件事，留點空白給自己。',
        'bloating'      => '腹脹的時候少喝冰、多走幾步，朵朵想跟妳說：身體會記得妳的溫柔。',
        'breast_tender' => '胸部脹脹時不如選比較鬆的內衣，這幾天就讓自己舒服一點。',
        'acne'          => '冒痘期妳可以試試清淡飲食 + 早睡，朵朵不是專家但這通常有用。',
        'mood_swing'    => '情緒起伏不是妳的錯，是黃體期的訊號。朵朵想跟妳說：先接住自己。',
        'craving_sweet' => '想吃甜不是貪嘴，是身體在說話。不如挑一個自己真心喜歡的甜點，認真享受它。',
        'craving_salty' => '想吃鹹也是常見訊號。挑自己喜歡的小份量，慢慢吃，朵朵陪妳。',
        'insomnia'      => '睡不好的時候不如睡前一小時關掉螢幕、把房間調暗。',
        'back_pain'     => '腰痠時不如熱敷或泡個腳，朵朵覺得這是對自己最溫柔的事。',
        'anxious'       => '焦慮的時候朵朵建議深呼吸 4-7-8（吸 4、停 7、吐 8），會比較踏實。',
        'irritable'     => '容易煩躁是身體節律。先給自己 10 分鐘獨處，再決定要不要回那條訊息。',
        'low_mood'      => '心情低低的時候不用解釋。朵朵陪著就好。',
    ];

    public function __construct(private readonly LegalContentSanitizer $sanitizer) {}

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

        // 計算「黃體期窗口」內出現的 tag 頻次
        // 黃體期 ≈ 經期前 14 天（排卵後）；保守用「經期前 5-14 天」14 天區間做窗
        $recentCycles = $cycles->take(3); // 最近 3 週期
        $olderCycles = $cycles->slice(3, 3); // 第 4-6 週期

        $recentTags = $this->collectLutealTags($recentCycles, $symptoms);
        $olderTags = $this->collectLutealTags($olderCycles, $symptoms);

        if ($recentTags->isEmpty()) {
            return null;
        }

        $freq = $recentTags->countBy()->sortDesc();
        $top = $freq->take(3);
        if ($top->isEmpty()) {
            return null;
        }

        $topKeys = $top->keys()->all();
        $suggestions = $this->buildSuggestions($topKeys);
        $trend = $this->computeSeverityTrend($recentTags, $olderTags, $topKeys);

        return new PmsPattern(
            sampleCycles: $cycles->count(),
            topSymptoms: $topKeys,
            symptomCounts: $top->all(),
            confidence: $cycles->count() >= 3 ? 'high' : 'low',
            suggestions: $suggestions,
            severityTrend: $trend,
        );
    }

    /**
     * 過去版本 hard-coded daily insight；保留 fallback。
     * 新版優先走 daily_insights 表（DailyInsightSeeder）。
     */
    public function dailyInsight(User $user, string $phase, ?int $cycleDay): string
    {
        return match ($phase) {
            'menstrual' => '經期保養是長期投資；今天記得補水跟休息，朵朵陪妳一起。',
            'follicular' => '能量回來了，這幾天適合做點新嘗試。',
            'ovulation' => '排卵期通常感覺最好，朵朵覺得妳今天會發光。',
            'luteal' => '黃體期會比較想吃甜跟容易煩躁，這是身體的訊號，不是妳的錯。',
            default => '今天朵朵在這裡。',
        };
    }

    /**
     * 從 cycles 集合裡，取「該 cycle 起始日前 5-14 天」的 symptom tags。
     */
    private function collectLutealTags(Collection $cycles, Collection $allSymptoms): Collection
    {
        $tags = collect();
        foreach ($cycles as $cycle) {
            $start = $cycle->start_date;
            $window = $allSymptoms->filter(function ($s) use ($start) {
                return $s->logged_on >= $start->copy()->subDays(14)
                    && $s->logged_on < $start->copy()->subDays(4);
            });
            foreach ($window as $sym) {
                foreach ($sym->tags ?? [] as $tag) {
                    $tags->push($tag);
                }
            }
        }

        return $tags;
    }

    /**
     * @param  array<int, string>  $tagKeys
     * @return array<string, string>
     */
    private function buildSuggestions(array $tagKeys): array
    {
        $out = [];
        foreach ($tagKeys as $key) {
            $raw = self::TAG_SUGGESTIONS[$key] ?? '不如試試多睡一點、喝點溫水，朵朵陪妳。';
            $out[$key] = $this->sanitizer->sanitize($raw);
        }

        return $out;
    }

    /**
     * @param  array<int, string>  $topKeys
     */
    private function computeSeverityTrend(Collection $recent, Collection $older, array $topKeys): string
    {
        if ($older->isEmpty()) {
            return 'unknown';
        }

        $recentCount = $recent->filter(fn ($t) => in_array($t, $topKeys, true))->count();
        $olderCount = $older->filter(fn ($t) => in_array($t, $topKeys, true))->count();

        if ($olderCount === 0) {
            return $recentCount > 0 ? 'worsening' : 'stable';
        }

        $delta = ($recentCount - $olderCount) / $olderCount;

        return match (true) {
            $delta > 0.3 => 'worsening',
            $delta < -0.3 => 'improving',
            default => 'stable',
        };
    }
}
