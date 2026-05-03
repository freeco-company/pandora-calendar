<?php

namespace App\Services\Calendar;

use Carbon\CarbonImmutable;

class CyclePrediction
{
    public function __construct(
        public readonly CarbonImmutable $today,
        public readonly ?CarbonImmutable $latestCycleStart,
        public readonly int $avgCycleLength,
        public readonly int $avgPeriodLength,
        public readonly ?CarbonImmutable $nextPeriodEta,
        public readonly ?CarbonImmutable $ovulationEta,
        public readonly int $sampleSize,
        public readonly float $stdDev = 0.0,
        public readonly ?CarbonImmutable $confidenceLow = null,
        public readonly ?CarbonImmutable $confidenceHigh = null,
        public readonly string $confidenceLevel = 'unknown',
    ) {}

    public static function insufficient(CarbonImmutable $today): self
    {
        return new self(
            today: $today,
            latestCycleStart: null,
            avgCycleLength: CyclePredictor::DEFAULT_CYCLE_LENGTH,
            avgPeriodLength: CyclePredictor::DEFAULT_PERIOD_LENGTH,
            nextPeriodEta: null,
            ovulationEta: null,
            sampleSize: 0,
            stdDev: 0.0,
            confidenceLow: null,
            confidenceHigh: null,
            confidenceLevel: 'unknown',
        );
    }

    public function toArray(): array
    {
        return [
            'today' => $this->today->toDateString(),
            'latest_cycle_start' => $this->latestCycleStart?->toDateString(),
            'avg_cycle_length' => $this->avgCycleLength,
            'avg_period_length' => $this->avgPeriodLength,
            'next_period_eta' => $this->nextPeriodEta?->toDateString(),
            'ovulation_eta' => $this->ovulationEta?->toDateString(),
            'sample_size' => $this->sampleSize,
            'confidence' => $this->confidence(),
            // P0 升級：信心區間（前端可顯示「±N 天」）
            'std_dev' => round($this->stdDev, 2),
            'confidence_low' => $this->confidenceLow?->toDateString(),
            'confidence_high' => $this->confidenceHigh?->toDateString(),
            'confidence_level' => $this->confidenceLevel,
        ];
    }

    /**
     * 舊 contract：給 CycleApiTest 既有斷言 (`prediction.confidence == 'none' / 'low' / 'high'`) 用。
     * 新欄位 confidence_level 表達更細的 unknown / low / medium / high。
     */
    public function confidence(): string
    {
        return match (true) {
            $this->sampleSize >= 3 => 'high',
            $this->sampleSize >= 1 => 'low',
            default => 'none',
        };
    }
}
