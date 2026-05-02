<?php

namespace App\Services\Calendar;

use Carbon\CarbonImmutable;

class BodyRhythm
{
    public function __construct(
        public readonly CarbonImmutable $date,
        public readonly string $phase,
        public readonly ?int $cycleDay,
        public readonly ?CarbonImmutable $nextPeriodEta,
        public readonly ?int $daysUntilNextPeriod,
    ) {}

    public function toArray(): array
    {
        return [
            'date' => $this->date->toDateString(),
            'phase' => $this->phase,
            'cycle_day' => $this->cycleDay,
            'next_period_eta' => $this->nextPeriodEta?->toDateString(),
            'days_until_next_period' => $this->daysUntilNextPeriod,
        ];
    }
}
