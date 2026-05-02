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
        $avgCycleLength = $cycleLengths->isEmpty()
            ? self::DEFAULT_CYCLE_LENGTH
            : (int) round($cycleLengths->avg());

        $periodLengths = $cycles
            ->filter(fn (Cycle $c) => $c->end_date !== null)
            ->map(fn (Cycle $c) => $c->lengthInDays());

        $avgPeriodLength = $periodLengths->isEmpty()
            ? self::DEFAULT_PERIOD_LENGTH
            : (int) round($periodLengths->avg());

        $latest = $cycles->first();
        $latestStart = CarbonImmutable::parse($latest->start_date);
        $nextStart = $latestStart->addDays($avgCycleLength);

        return new CyclePrediction(
            today: $today,
            latestCycleStart: $latestStart,
            avgCycleLength: $avgCycleLength,
            avgPeriodLength: $avgPeriodLength,
            nextPeriodEta: $nextStart,
            ovulationEta: $nextStart->subDays(14),
            sampleSize: $cycles->count(),
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
}
