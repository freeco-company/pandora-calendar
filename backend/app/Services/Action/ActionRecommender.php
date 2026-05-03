<?php

namespace App\Services\Action;

use App\Models\DailyActionRecommendation;
use App\Models\UserActionProtocol;
use App\Services\Calendar\BodyRhythmCalculator;
use App\Services\Calendar\CyclePredictor;
use Carbon\CarbonImmutable;

/**
 * 為使用者挑選今天的單一推薦行動。
 *
 * Config schema（daily-actions.php v2）：
 *   phase:  string[]            可命中的 phase（menstrual/follicular/ovulation/luteal/late）
 *   day_offset_min/max: int     phase 內的第幾天（0-base）
 *   type:   sleep/move/eat/relax/track/learn/connect
 *   title / body / expected_benefit / time_minutes / difficulty
 *
 * 排序邏輯（高分先）：
 *   1. base = 0.5
 *   2. + (effectiveness_score - 0.5) * 0.6（用戶過去對此 action 的有效性 -0.3 ~ +0.3）
 *   3. - 同 type 過去 7 天已完成 ≥ 7 次 → -0.3（疲勞降權）
 *   4. + random jitter 0~0.1（避免完全僵化）
 *   - 過去 3 天推過同 action → 排除
 *   - 今天已推 → 直接 return（idempotent）
 *
 * Phase 對應：
 *   - BodyRhythmCalculator 給的 phase 是 menstrual/follicular/ovulation/luteal/unknown
 *   - 'late' 卡片觸發於 cycle_day 超過 avg_cycle_length（推估月經遲到中），但本 service
 *     不直接判定 late，narrative 卡片有設 phase=['late']，cycleDay 落在該 phase 才命中。
 *   - unknown phase fallback：取所有不指定特定 phase 的卡（暫無 'any' 卡片，會回 null）。
 */
class ActionRecommender
{
    public function __construct(
        private readonly CyclePredictor $predictor,
        private readonly BodyRhythmCalculator $rhythmCalc,
    ) {}

    public function recommendForToday(int $userId, ?CarbonImmutable $today = null): ?DailyActionRecommendation
    {
        $today ??= CarbonImmutable::today();

        $existing = DailyActionRecommendation::where('user_id', $userId)
            ->whereDate('recommended_on', $today->toDateString())
            ->orderByDesc('id')
            ->first();
        if ($existing) {
            return $existing;
        }

        $prediction = $this->predictor->predict($userId, $today);
        $rhythm = $this->rhythmCalc->compute($prediction, $today);
        $phase = $rhythm->phase ?: BodyRhythmCalculator::PHASE_UNKNOWN;
        $cycleDay = $rhythm->cycleDay; // 1-base from BodyRhythmCalculator

        $cards = (array) config('daily-actions', []);
        if (empty($cards)) {
            return null;
        }

        // Step 2: phase + day_offset 過濾
        $candidates = $this->filterByPhaseAndDay($cards, $phase, $cycleDay);
        if (empty($candidates)) {
            return null;
        }

        // Step 3: 排除過去 3 天已推 action
        $recentKeys = DailyActionRecommendation::where('user_id', $userId)
            ->where('recommended_on', '>=', $today->subDays(3)->toDateString())
            ->pluck('action_key')
            ->all();
        $candidates = array_diff_key($candidates, array_flip($recentKeys));
        if (empty($candidates)) {
            // 全被排除（小型 candidate set 邊界）→ 退回 phase 全集（忽略 dedup）
            $candidates = $this->filterByPhaseAndDay($cards, $phase, $cycleDay);
            if (empty($candidates)) {
                return null;
            }
        }

        // Step 4: effectiveness map
        $protocols = UserActionProtocol::where('user_id', $userId)
            ->where('phase', $phase)
            ->whereIn('action_key', array_keys($candidates))
            ->get()
            ->keyBy('action_key');

        $fatigueByType = $this->fatigueByType($userId, $today, $cards);

        $scored = [];
        foreach ($candidates as $key => $card) {
            $score = 0.5;
            $protocol = $protocols->get($key);
            if ($protocol) {
                $score += ($protocol->effectiveness_score - 0.5) * 0.6;
            }

            $type = (string) ($card['type'] ?? '');
            if ($type !== '' && ($fatigueByType[$type] ?? 0) >= 7) {
                $score -= 0.3;
            }

            $score += mt_rand(0, 100) / 1000;
            $scored[$key] = $score;
        }

        arsort($scored);
        $chosenKey = (string) array_key_first($scored);

        // Step 6: persist（unique key 防 race）
        try {
            $rec = DailyActionRecommendation::create([
                'user_id' => $userId,
                'recommended_on' => $today->toDateString(),
                'action_key' => $chosenKey,
                'phase' => $phase,
                'cycle_day' => $cycleDay,
                'is_completed' => false,
            ]);
        } catch (\Illuminate\Database\UniqueConstraintViolationException) {
            $rec = DailyActionRecommendation::where('user_id', $userId)
                ->whereDate('recommended_on', $today->toDateString())
                ->orderByDesc('id')
                ->first();
        }

        return $rec;
    }

    /**
     * 依 phase + cycle_day 過濾候選卡片。
     *
     * v2 schema：phase 是 array，day_offset_min/max 控制命中 day 範圍（含端點）。
     */
    private function filterByPhaseAndDay(array $cards, string $phase, ?int $cycleDay): array
    {
        $out = [];
        foreach ($cards as $key => $card) {
            $cardPhases = (array) ($card['phase'] ?? []);
            if (empty($cardPhases)) {
                continue;
            }
            if (! in_array($phase, $cardPhases, true)) {
                continue;
            }

            // day_offset 是 phase 內的相對天數，本 stub 對 phase 內 day 不做精細映射，
            // 改用「cycle_day 是否落在 [min+1, max+1]（1-base）」近似。
            // 經期 phase day 1-5 對應 day_offset 0-4 → cycle_day 1-7 範圍寬鬆通過。
            if ($cycleDay !== null && isset($card['day_offset_min'], $card['day_offset_max'])) {
                $approxDay = $cycleDay - 1; // 0-base 近似
                if ($approxDay < (int) $card['day_offset_min'] || $approxDay > (int) $card['day_offset_max'] + 7) {
                    continue;
                }
            }

            $out[$key] = $card;
        }

        return $out;
    }

    /**
     * 過去 7 天每個 type 完成數（用於疲勞降權）。
     *
     * @return array<string, int>
     */
    private function fatigueByType(int $userId, CarbonImmutable $today, array $cards): array
    {
        $rows = DailyActionRecommendation::where('user_id', $userId)
            ->where('is_completed', true)
            ->where('recommended_on', '>=', $today->subDays(7)->toDateString())
            ->get(['action_key']);

        $counts = [];
        foreach ($rows as $row) {
            $type = (string) ($cards[$row->action_key]['type'] ?? '');
            if ($type === '') {
                continue;
            }
            $counts[$type] = ($counts[$type] ?? 0) + 1;
        }

        return $counts;
    }
}
