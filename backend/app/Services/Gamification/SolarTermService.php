<?php

namespace App\Services\Gamification;

use App\Models\SolarTermParticipation;
use App\Services\Economy\DodoCoinService;
use Carbon\CarbonImmutable;

/**
 * Wave 13 — Solar term service（24 節氣）。
 *
 * 節氣前後 ±window_days 為 participation window；該年該節氣只能領 1 次 reward。
 */
final class SolarTermService
{
    public function __construct(private readonly DodoCoinService $coins) {}

    public function terms(): array
    {
        return (array) config('dodo-solar-terms.terms', []);
    }

    public function currentTerm(?CarbonImmutable $on = null): ?array
    {
        $on ??= CarbonImmutable::today();
        $window = (int) config('dodo-solar-terms.window_days', 1);

        foreach ($this->terms() as $term) {
            $year = (int) $on->format('Y');
            $center = CarbonImmutable::createFromDate($year, (int) $term['month'], (int) $term['day']);
            $start = $center->subDays($window);
            $end = $center->addDays($window);
            if ($on->gte($start) && $on->lte($end)) {
                return array_merge($term, [
                    'year' => $year,
                    'window_start' => $start->toDateString(),
                    'window_end' => $end->toDateString(),
                ]);
            }
        }

        return null;
    }

    public function termByKey(string $key): ?array
    {
        foreach ($this->terms() as $term) {
            if ($term['key'] === $key) {
                return $term;
            }
        }

        return null;
    }

    public function hasParticipated(int $userId, string $termKey, int $year): bool
    {
        return SolarTermParticipation::where('user_id', $userId)
            ->where('term_key', $termKey)
            ->where('year', $year)
            ->exists();
    }

    /**
     * Mark participation for current term (only valid during window).
     * Returns the participation record, or null if outside window or already participated.
     */
    public function participate(int $userId, ?string $termKey = null, ?CarbonImmutable $on = null): ?SolarTermParticipation
    {
        $on ??= CarbonImmutable::today();
        $current = $this->currentTerm($on);
        if ($current === null) {
            return null;
        }
        if ($termKey !== null && $termKey !== $current['key']) {
            return null;
        }

        $year = (int) $on->format('Y');
        if ($this->hasParticipated($userId, $current['key'], $year)) {
            return null;
        }

        $reward = (int) config('dodo-solar-terms.participation_reward_coins', 50);
        $this->coins->earn($userId, $reward, DodoCoinService::SOURCE_SOLAR_TERM, [
            'term' => $current['key'],
            'year' => $year,
        ]);

        return SolarTermParticipation::create([
            'user_id' => $userId,
            'term_key' => $current['key'],
            'year' => $year,
            'earned_coins' => $reward,
            'completed_at' => CarbonImmutable::now(),
        ]);
    }
}
