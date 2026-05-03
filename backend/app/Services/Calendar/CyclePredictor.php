<?php

namespace App\Services\Calendar;

use App\Models\Cycle;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class CyclePredictor
{
    public const DEFAULT_CYCLE_LENGTH = 28;
    public const DEFAULT_PERIOD_LENGTH = 5;
    public const SAMPLE_WINDOW = 6;

    public function predict(int $userId, ?CarbonImmutable $today = null): CyclePrediction
    {
        $today ??= CarbonImmutable::today();

        $cycles = Cycle::query()
            ->where('user_id', $userId)
            ->orderByDesc('start_date')
            ->limit(self::SAMPLE_WINDOW)
            ->get();

        if ($cycles->isEmpty()) {
            return CyclePrediction::insufficient($today);
        }

        $cycleLengths = $this->computeCycleLengths($cycles);

        // 離群值處理：> 2 SD 的歷史週期不納入計算（樣本 >= 3 才篩，否則沒意義）
        $cleanLengths = $this->trimOutliers($cycleLengths);

        $avgCycleLength = $cleanLengths->isEmpty()
            ? self::DEFAULT_CYCLE_LENGTH
            : (int) round($cleanLengths->avg());

        $stdDev = $cleanLengths->count() >= 2 ? $this->stdDev($cleanLengths) : 0.0;

        // 防衛：髒資料（end_date 在未來、手動 import 錯誤）→ lengthInDays() 已回 null。
        // 額外 guard：原始 raw diff > 14 / < 1 也擋掉並 log，避免之後 model 邏輯改了又漏。
        $periodLengths = $cycles
            ->filter(fn (Cycle $c) => $c->end_date !== null)
            ->map(function (Cycle $c) {
                $raw = $c->start_date->diffInDays($c->end_date) + 1;
                if ($raw < 1 || $raw > 14) {
                    \Log::warning('CyclePredictor: filtered invalid cycle length', [
                        'cycle_id' => $c->id,
                        'user_id' => $c->user_id,
                        'raw_length_days' => $raw,
                    ]);

                    return null;
                }

                return $c->lengthInDays();
            })
            ->filter(fn (?int $v) => $v !== null && $v >= 1 && $v <= 14)
            ->values();

        $avgPeriodLength = $periodLengths->isEmpty()
            ? self::DEFAULT_PERIOD_LENGTH
            : (int) round($periodLengths->avg());

        $latest = $cycles->first();
        $latestStart = CarbonImmutable::parse($latest->start_date);
        $nextStart = $latestStart->addDays($avgCycleLength);

        // 信心區間：±1 SD（68%）四捨五入
        $confidenceLevel = $this->classifyConfidence($cycles->count(), $stdDev);
        [$low, $high] = $this->computeConfidenceWindow($nextStart, $stdDev, $cycles->count());

        return new CyclePrediction(
            today: $today,
            latestCycleStart: $latestStart,
            avgCycleLength: $avgCycleLength,
            avgPeriodLength: $avgPeriodLength,
            nextPeriodEta: $nextStart,
            ovulationEta: $nextStart->subDays(14),
            sampleSize: $cycles->count(),
            stdDev: $stdDev,
            confidenceLow: $low,
            confidenceHigh: $high,
            confidenceLevel: $confidenceLevel,
        );
    }

    private function computeCycleLengths(Collection $cycles): Collection
    {
        $sorted = $cycles->sortBy('start_date')->values();
        $lengths = collect();

        for ($i = 1; $i < $sorted->count(); $i++) {
            $prev = CarbonImmutable::parse($sorted[$i - 1]->start_date);
            $curr = CarbonImmutable::parse($sorted[$i]->start_date);
            $lengths->push($prev->diffInDays($curr));
        }

        return $lengths;
    }

    /**
     * 移除超過 mean ± 2 SD 的值（樣本 >= 3 才動，避免樣本太少把資料砍光）
     */
    private function trimOutliers(Collection $lengths): Collection
    {
        if ($lengths->count() < 3) {
            return $lengths;
        }

        $mean = $lengths->avg();
        $sd = $this->stdDev($lengths);
        if ($sd <= 0.0) {
            return $lengths;
        }

        $cleaned = $lengths->filter(fn (float|int $x) => abs($x - $mean) <= 2 * $sd);

        // 全砍光的極端 case：fallback 用原資料
        return $cleaned->isEmpty() ? $lengths : $cleaned->values();
    }

    private function stdDev(Collection $values): float
    {
        $n = $values->count();
        if ($n < 2) {
            return 0.0;
        }
        $mean = $values->avg();
        $variance = $values->reduce(fn ($carry, $v) => $carry + (($v - $mean) ** 2), 0) / ($n - 1);

        return sqrt($variance);
    }

    private function classifyConfidence(int $sampleSize, float $stdDev): string
    {
        // 完整 cycle 才能算 cycle length（n cycles 給 n-1 個 length）。
        if ($sampleSize < 2) {
            return 'unknown';
        }
        if ($sampleSize < 3) {
            return 'low'; // 一個 length 沒辦法判斷穩定性
        }
        // 標準差越小 → 越穩定
        return match (true) {
            $stdDev <= 1.5 => 'high',
            $stdDev <= 3.5 => 'medium',
            default => 'low',
        };
    }

    /**
     * @return array{0: ?CarbonImmutable, 1: ?CarbonImmutable}
     */
    private function computeConfidenceWindow(CarbonImmutable $eta, float $stdDev, int $sampleSize): array
    {
        if ($sampleSize < 2 || $stdDev <= 0.0) {
            return [null, null];
        }

        // 用 ±1 SD 表達 ~68% 區間，至少 ±1 天讓前端能畫範圍
        $delta = max(1, (int) round($stdDev));

        return [$eta->subDays($delta), $eta->addDays($delta)];
    }
}
