<?php

namespace App\Services\Calendar;

use Carbon\CarbonImmutable;

class BodyRhythmCalculator
{
    public const PHASE_MENSTRUAL = 'menstrual';
    public const PHASE_FOLLICULAR = 'follicular';
    public const PHASE_OVULATION = 'ovulation';
    public const PHASE_LUTEAL = 'luteal';
    public const PHASE_UNKNOWN = 'unknown';

    public function compute(CyclePrediction $prediction, ?CarbonImmutable $on = null): BodyRhythm
    {
        $on ??= CarbonImmutable::today();

        if (! $prediction->latestCycleStart) {
            return new BodyRhythm(
                date: $on,
                phase: self::PHASE_UNKNOWN,
                cycleDay: null,
                nextPeriodEta: null,
                daysUntilNextPeriod: null,
            );
        }

        $cycleDay = $prediction->latestCycleStart->diffInDays($on) + 1;
        $cycleLength = $prediction->avgCycleLength;
        $periodLength = $prediction->avgPeriodLength;

        if ($cycleDay > $cycleLength) {
            $cyclesElapsed = intdiv($cycleDay - 1, $cycleLength);
            $cycleDay = (($cycleDay - 1) % $cycleLength) + 1;
            $effectiveStart = $prediction->latestCycleStart->addDays($cyclesElapsed * $cycleLength);
            $nextPeriodEta = $effectiveStart->addDays($cycleLength);
        } else {
            $nextPeriodEta = $prediction->latestCycleStart->addDays($cycleLength);
        }

        $ovulationDay = $cycleLength - 14;

        $phase = match (true) {
            $cycleDay <= $periodLength => self::PHASE_MENSTRUAL,
            $cycleDay >= $ovulationDay - 1 && $cycleDay <= $ovulationDay + 1 => self::PHASE_OVULATION,
            $cycleDay < $ovulationDay - 1 => self::PHASE_FOLLICULAR,
            default => self::PHASE_LUTEAL,
        };

        return new BodyRhythm(
            date: $on,
            phase: $phase,
            cycleDay: $cycleDay,
            nextPeriodEta: $nextPeriodEta,
            daysUntilNextPeriod: $on->diffInDays($nextPeriodEta, false),
        );
    }
}
