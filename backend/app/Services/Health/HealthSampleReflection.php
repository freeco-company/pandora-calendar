<?php

namespace App\Services\Health;

use App\Models\BbtReading;
use App\Models\HealthSample;
use App\Services\Calendar\BodyRhythmCalculator;
use App\Services\Calendar\CyclePredictor;
use Carbon\CarbonImmutable;
use Pandora\Shared\Compliance\LegalContentSanitizer;

/**
 * HealthSampleReflection — 把 import 進來的 BBT / steps / sleep 資料解讀成
 * 朵朵口吻的 contextual insight，並標出對應 action_type 給 ActionRecommender 加權。
 *
 * 觸發 rule（高優先排在前）：
 *   1. luteal + 昨晚睡 < 7h            → suggested_action_type = sleep, severity = heads_up
 *   2. ovulation 附近 + BBT 升幅 ≥ 0.3 → suggested_action_type = track,  severity = info
 *   3. 連 3 天 step < 7 天平均 80%     → suggested_action_type = move,   severity = notice
 *
 * 沒命中或沒資料 → null。
 *
 * Tone：妳 / 朋友 / 朵朵；過 sanitizer riskReport 才 return（紅線文案直接 fallback null）。
 */
class HealthSampleReflection
{
    public function __construct(
        private readonly CyclePredictor $predictor,
        private readonly BodyRhythmCalculator $rhythmCalc,
        private readonly LegalContentSanitizer $sanitizer,
    ) {}

    /**
     * @return array{message:string,suggested_action_type:string,severity:string,source:string}|null
     */
    public function reflectToday(int $userId, ?CarbonImmutable $today = null): ?array
    {
        $today ??= CarbonImmutable::today();

        $prediction = $this->predictor->predict($userId, $today);
        $rhythm = $this->rhythmCalc->compute($prediction, $today);
        $phase = $rhythm->phase ?: BodyRhythmCalculator::PHASE_UNKNOWN;
        $cycleDay = $rhythm->cycleDay;
        $cycleLength = $prediction->avgCycleLength;
        $ovulationDay = $cycleLength - 14;

        // Rule 1：黃體期 + 昨晚睡眠不足
        if ($phase === BodyRhythmCalculator::PHASE_LUTEAL) {
            $sleep = $this->lastNightSleep($userId, $today);
            if ($sleep !== null && $sleep < 7.0) {
                $hoursLabel = $this->formatHours($sleep);
                $message = "妳昨晚只睡了 {$hoursLabel} 小時，黃體期睡眠不夠通常情緒起伏會比較大，今天可以早一點上床嗎？";

                return $this->maybeReturn($message, 'sleep', 'heads_up', 'sleep_insufficient_luteal');
            }
        }

        // Rule 2：排卵期附近 + BBT 升幅
        if ($cycleDay !== null && $ovulationDay > 0) {
            $nearOvulation = abs($cycleDay - $ovulationDay) <= 3;
            if ($nearOvulation) {
                $bbtShift = $this->bbtShift($userId, $today);
                if ($bbtShift !== null && $bbtShift >= 0.3) {
                    $shiftLabel = number_format($bbtShift, 1);
                    $message = "BBT 上升了 {$shiftLabel}°C，可能進入排卵後期 ✨ 朵朵幫妳記下今天的節奏。";

                    return $this->maybeReturn($message, 'track', 'info', 'bbt_shift_ovulation');
                }
            }
        }

        // Rule 3：連 3 天 step 低於平均
        $stepDip = $this->isStepDip($userId, $today);
        if ($stepDip) {
            $message = $phase === BodyRhythmCalculator::PHASE_LUTEAL
                ? '最近走得比平常少，黃體期適合輕鬆散步 15 分鐘，當作給自己一個小空檔。'
                : '最近三天走得比平常少，今天試試 15 分鐘散步換個節奏？';

            return $this->maybeReturn($message, 'move', 'notice', 'steps_dip_3d');
        }

        return null;
    }

    /**
     * 昨晚睡眠 hours（從 health_samples sleep_hours 找昨天那筆）。
     */
    private function lastNightSleep(int $userId, CarbonImmutable $today): ?float
    {
        $yesterday = $today->subDay()->toDateString();
        $row = HealthSample::where('user_id', $userId)
            ->where('metric', HealthSample::METRIC_SLEEP_HOURS)
            ->whereDate('recorded_on', $yesterday)
            ->orderByDesc('recorded_at')
            ->first();

        return $row ? (float) $row->value : null;
    }

    /**
     * BBT 升幅 = 昨天溫度 - 過去 7 天（昨天前）平均；資料不足 return null。
     */
    private function bbtShift(int $userId, CarbonImmutable $today): ?float
    {
        $yesterday = $today->subDay()->toDateString();
        $latest = BbtReading::where('user_id', $userId)
            ->whereDate('measured_on', $yesterday)
            ->first();
        if (! $latest) {
            return null;
        }

        $baseline = BbtReading::where('user_id', $userId)
            ->where('measured_on', '<', $yesterday)
            ->where('measured_on', '>=', $today->subDays(8)->toDateString())
            ->get(['temperature_c']);
        if ($baseline->count() < 3) {
            return null;
        }

        $avg = $baseline->avg(fn ($r) => (float) $r->temperature_c);

        return round((float) $latest->temperature_c - (float) $avg, 2);
    }

    /**
     * 過去 3 天 step 是否都低於 7 天平均 * 0.8（且 7 天平均 ≥ 1000，避免 baseline 太低 false positive）。
     */
    private function isStepDip(int $userId, CarbonImmutable $today): bool
    {
        $sevenDaysAgo = $today->subDays(7)->toDateString();
        $rows = HealthSample::where('user_id', $userId)
            ->where('metric', HealthSample::METRIC_STEPS)
            ->where('recorded_on', '>=', $sevenDaysAgo)
            ->where('recorded_on', '<', $today->toDateString())
            ->get(['recorded_on', 'value']);

        if ($rows->count() < 6) {
            return false;
        }

        $avg = $rows->avg('value');
        if ($avg < 1000) {
            return false;
        }
        $threshold = $avg * 0.8;

        $byDate = $rows->keyBy(fn ($r) => $r->recorded_on->toDateString());
        $dipDays = 0;
        for ($i = 1; $i <= 3; $i++) {
            $iso = $today->subDays($i)->toDateString();
            $row = $byDate->get($iso);
            if ($row && (float) $row->value < $threshold) {
                $dipDays++;
            } else {
                return false;
            }
        }

        return $dipDays >= 3;
    }

    private function formatHours(float $h): string
    {
        if (abs($h - round($h)) < 0.05) {
            return (string) (int) round($h);
        }

        return number_format($h, 1);
    }

    /**
     * 過合規 sanitizer；含紅線詞直接 return null（不 surface）。
     */
    private function maybeReturn(string $message, string $actionType, string $severity, string $sourceKey): ?array
    {
        if ($this->sanitizer->riskReport($message) !== []) {
            return null;
        }

        return [
            'message' => $message,
            'suggested_action_type' => $actionType,
            'severity' => $severity,
            'source' => $sourceKey,
        ];
    }
}
