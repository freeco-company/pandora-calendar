<?php

namespace App\Services\Pet;

use App\Models\PetBond;
use App\Services\Economy\DodoCoinService;
use Carbon\CarbonImmutable;

/**
 * Wave 13 — Pet bond service。
 *
 * Bond curve（gentle）：
 *   lv1 = 0    lv5 = 100   lv10 = 400
 *   lv20 = 1500   lv30 = 4000   lv50 (max) = 12000
 *
 * Intimacy tiers：
 *   friendly  Lv 1-9
 *   close     Lv 10-24    解鎖 pet 主動 daily dialog
 *   soulmate  Lv 25-50    解隱藏 outfit 系列 + 情緒同步
 *
 * 紅線：bond level 不會降；feed / pet-head 有每日上限避免刷分。
 */
final class PetBondService
{
    public const FEED_DAILY_LIMIT = 3;

    public const PET_HEAD_DAILY_LIMIT = 5;

    public const FEED_COIN_COST = 10; // base，pet item 可不同（service caller 決定）

    public const FEED_BOND_REWARD = 10;

    public const PET_HEAD_BOND_REWARD = 1;

    public const TIER_FRIENDLY = 'friendly';

    public const TIER_CLOSE = 'close';

    public const TIER_SOULMATE = 'soulmate';

    public function __construct(private readonly DodoCoinService $coins) {}

    public function getOrCreate(int $userId, string $species): PetBond
    {
        return PetBond::firstOrCreate(
            ['user_id' => $userId, 'pet_species' => $species],
            ['bond_xp' => 0, 'feed_count_today' => 0, 'pet_head_count_today' => 0, 'counters_reset_on' => CarbonImmutable::today()->toDateString()],
        );
    }

    public function award(int $userId, string $species, int $delta, string $source = 'unspecified'): PetBond
    {
        if ($delta <= 0) {
            throw new \InvalidArgumentException('bond delta must be positive');
        }

        $bond = $this->getOrCreate($userId, $species);
        $bond->bond_xp = $bond->bond_xp + $delta;
        $bond->save();

        return $bond->fresh();
    }

    public function currentLevel(int $xp): int
    {
        // Gentle curve: piecewise linear sampled at the documented anchor points.
        $anchors = [
            [0, 1], [100, 5], [400, 10], [1500, 20], [4000, 30], [12000, 50],
        ];

        $level = 1;
        for ($i = 1; $i < count($anchors); $i++) {
            [$xpA, $lvA] = $anchors[$i - 1];
            [$xpB, $lvB] = $anchors[$i];
            if ($xp < $xpA) {
                break;
            }
            if ($xp >= $xpB) {
                $level = $lvB;

                continue;
            }
            $progress = ($xp - $xpA) / max(1, $xpB - $xpA);
            $level = (int) floor($lvA + $progress * ($lvB - $lvA));
            break;
        }

        return min(50, max(1, $level));
    }

    public function intimacyTier(int $level): string
    {
        if ($level >= 25) {
            return self::TIER_SOULMATE;
        }
        if ($level >= 10) {
            return self::TIER_CLOSE;
        }

        return self::TIER_FRIENDLY;
    }

    /**
     * Feed pet — costs coins, awards bond. Returns null if insufficient coin or daily limit hit.
     */
    public function feed(int $userId, string $species, string $itemCode = 'default', int $coinCost = self::FEED_COIN_COST, int $bondReward = self::FEED_BOND_REWARD): ?PetBond
    {
        $bond = $this->getOrCreate($userId, $species);
        $this->resetCountersIfNeeded($bond);

        if ($bond->feed_count_today >= self::FEED_DAILY_LIMIT) {
            return null;
        }

        $spend = $this->coins->spend($userId, $coinCost, DodoCoinService::SOURCE_SPEND_PET_ITEM, [
            'item' => $itemCode, 'species' => $species,
        ]);
        if ($spend === null) {
            return null;
        }

        $bond->bond_xp = $bond->bond_xp + $bondReward;
        $bond->feed_count_today = $bond->feed_count_today + 1;
        $bond->save();

        return $bond->fresh();
    }

    public function petHead(int $userId, string $species): ?PetBond
    {
        $bond = $this->getOrCreate($userId, $species);
        $this->resetCountersIfNeeded($bond);

        if ($bond->pet_head_count_today >= self::PET_HEAD_DAILY_LIMIT) {
            return null;
        }

        $bond->bond_xp = $bond->bond_xp + self::PET_HEAD_BOND_REWARD;
        $bond->pet_head_count_today = $bond->pet_head_count_today + 1;
        $bond->save();

        return $bond->fresh();
    }

    public function snapshot(int $userId, string $species): array
    {
        $bond = $this->getOrCreate($userId, $species);
        $this->resetCountersIfNeeded($bond);
        $level = $this->currentLevel((int) $bond->bond_xp);

        return [
            'species' => $species,
            'bond_xp' => (int) $bond->bond_xp,
            'level' => $level,
            'intimacy_tier' => $this->intimacyTier($level),
            'feed_remaining_today' => max(0, self::FEED_DAILY_LIMIT - $bond->feed_count_today),
            'pet_head_remaining_today' => max(0, self::PET_HEAD_DAILY_LIMIT - $bond->pet_head_count_today),
        ];
    }

    private function resetCountersIfNeeded(PetBond $bond): void
    {
        $today = CarbonImmutable::today()->toDateString();
        if ($bond->counters_reset_on?->toDateString() !== $today) {
            $bond->feed_count_today = 0;
            $bond->pet_head_count_today = 0;
            $bond->counters_reset_on = $today;
            $bond->save();
        }
    }
}
