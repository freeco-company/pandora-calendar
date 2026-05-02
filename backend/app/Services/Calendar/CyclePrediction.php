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
        ];
    }

    public function confidence(): string
    {
        return match (true) {
            $this->sampleSize >= 3 => 'high',
            $this->sampleSize >= 1 => 'low',
            default => 'none',
        };
    }
}
