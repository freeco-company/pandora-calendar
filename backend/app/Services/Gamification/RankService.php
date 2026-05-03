<?php

namespace App\Services\Gamification;

use App\Models\Achievement;
use App\Models\Cycle;
use App\Models\DodoCheckin;

/**
 * Wave 13 — Rank service（節律段位）。
 *
 * rank_xp = cycles_count*100 + achievements_count*30 + days_active*1
 *   - cycles：完整週期數
 *   - achievements：解鎖數
 *   - days_active：有任何 DodoCheckin 的 distinct date 數
 */
final class RankService
{
    public function rankXp(int $userId): int
    {
        $cycles = Cycle::query()->where('user_id', $userId)->count();
        $achievements = Achievement::query()->where('user_id', $userId)->count();
        $daysActive = DodoCheckin::query()
            ->where('user_id', $userId)
            ->selectRaw('COUNT(DISTINCT DATE(created_at)) as c')
            ->value('c') ?? 0;

        return ($cycles * 100) + ($achievements * 30) + (int) $daysActive;
    }

    public function tiers(): array
    {
        return (array) config('ranks.tiers', []);
    }

    public function currentRank(int $userId): array
    {
        $xp = $this->rankXp($userId);

        return $this->resolve($xp);
    }

    public function resolve(int $xp): array
    {
        $tiers = $this->tiers();
        $current = $tiers[0] ?? ['key' => 'cang', 'label' => '蒼月', 'min_xp' => 0];
        $next = null;

        foreach ($tiers as $idx => $tier) {
            if ($xp >= ($tier['min_xp'] ?? 0)) {
                $current = $tier;
                $next = $tiers[$idx + 1] ?? null;
            }
        }

        $progressPercent = 0.0;
        if ($next !== null) {
            $span = max(1, ($next['min_xp'] - $current['min_xp']));
            $progressPercent = round((($xp - $current['min_xp']) / $span) * 100, 1);
        } else {
            $progressPercent = 100.0;
        }

        return [
            'tier_key' => $current['key'],
            'tier_label' => $current['label'],
            'xp' => $xp,
            'current_min_xp' => (int) $current['min_xp'],
            'next_threshold' => $next ? (int) $next['min_xp'] : null,
            'next_tier_label' => $next['label'] ?? null,
            'progress_percent' => $progressPercent,
            'is_max_tier' => $next === null,
        ];
    }
}
