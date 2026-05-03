<?php

namespace App\Services\Gamification;

use App\Models\RandomEventLog;
use App\Services\Calendar\BodyRhythmCalculator;
use App\Services\Calendar\CyclePredictor;
use App\Services\Economy\DodoCoinService;
use Carbon\CarbonImmutable;

/**
 * Wave 13 — Random event service。
 *
 * 每天最多 1 個 event；roll_chance 機率觸發。
 * Phase 過濾：event 有 phase 限制就需匹配當前 phase。
 */
final class RandomEventService
{
    public function __construct(
        private readonly CyclePredictor $predictor,
        private readonly BodyRhythmCalculator $rhythmCalc,
        private readonly DodoCoinService $coins,
    ) {}

    public function todayLog(int $userId, ?CarbonImmutable $today = null): ?RandomEventLog
    {
        $today ??= CarbonImmutable::today();

        return RandomEventLog::where('user_id', $userId)
            ->whereDate('triggered_on', $today->toDateString())
            ->first();
    }

    /**
     * Roll once for today. Returns existing log if already rolled, or new log if hit, or null on miss.
     */
    public function roll(int $userId, ?CarbonImmutable $today = null): ?RandomEventLog
    {
        $today ??= CarbonImmutable::today();

        $existing = $this->todayLog($userId, $today);
        if ($existing !== null) {
            return $existing;
        }

        $chance = (float) config('dodo-random-events.roll_chance', 0.15);
        $hit = (mt_rand(0, 9999) / 10000) < $chance;
        if (! $hit) {
            return null;
        }

        $phase = null;
        try {
            $prediction = $this->predictor->predict($userId, $today);
            $rhythm = $this->rhythmCalc->compute($prediction, $today);
            $phase = $rhythm->phase ?: null;
        } catch (\Throwable) {
            // 沒週期資料就跳過 phase 過濾
        }

        $event = $this->pickEvent($phase);
        if ($event === null) {
            return null;
        }

        return RandomEventLog::create([
            'user_id' => $userId,
            'event_key' => $event['key'],
            'triggered_on' => $today->toDateString(),
            'triggered_at' => CarbonImmutable::now(),
            'reward_coins' => (int) ($event['reward_coins'] ?? 0),
            'reward_xp' => (int) ($event['reward_xp'] ?? 0),
            'claimed' => false,
        ]);
    }

    public function claim(int $userId, int $logId): ?RandomEventLog
    {
        $log = RandomEventLog::where('user_id', $userId)->where('id', $logId)->first();
        if ($log === null || $log->claimed) {
            return null;
        }

        if ($log->reward_coins > 0) {
            $this->coins->earn($userId, $log->reward_coins, DodoCoinService::SOURCE_RANDOM_EVENT, [
                'event_key' => $log->event_key,
                'log_id' => $log->id,
            ]);
        }

        $log->claimed = true;
        $log->claimed_at = CarbonImmutable::now();
        $log->save();

        return $log->fresh();
    }

    public function eventByKey(string $key): ?array
    {
        foreach ((array) config('dodo-random-events.events', []) as $e) {
            if ($e['key'] === $key) {
                return $e;
            }
        }

        return null;
    }

    private function pickEvent(?string $phase): ?array
    {
        $events = (array) config('dodo-random-events.events', []);
        $eligible = array_values(array_filter($events, fn ($e) => $e['phase'] === null || $e['phase'] === $phase));
        if (empty($eligible)) {
            return null;
        }

        $totalWeight = 0;
        foreach ($eligible as $e) {
            $totalWeight += max(1, (int) ($e['weight'] ?? 1));
        }
        $pick = mt_rand(1, $totalWeight);
        $running = 0;
        foreach ($eligible as $e) {
            $running += max(1, (int) ($e['weight'] ?? 1));
            if ($pick <= $running) {
                return $e;
            }
        }

        return $eligible[0];
    }
}
