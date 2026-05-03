<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Cycle;
use App\Models\CycleSymptom;
use App\Models\DodoCheckin;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * P0-4 Journey dashboard — 給用戶看「我目前 Lv X / 還差 Y XP / 已解鎖 outfit / streak」。
 *
 * Lv 公式對齊 frontend lib/gamification.ts: xpForLevel(lv) = 50*lv + 25*lv^2
 *   Lv2 = 150, Lv3 = 375, Lv4 = 700, Lv5 = 1125
 */
class JourneyController extends Controller
{
    private function xpForLevel(int $level): int
    {
        return (int) (50 * $level + 25 * $level * $level);
    }

    public function show(Request $request): JsonResponse
    {
        $u = $request->user();
        $level = (int) ($u->level ?? 1);
        $totalXp = (int) ($u->total_xp ?? 0);
        $nextThreshold = $this->xpForLevel($level);
        $prevThreshold = $level > 1 ? $this->xpForLevel($level - 1) : 0;
        $progressInLevel = max(0, $totalXp - $prevThreshold);
        $needForNext = max(1, $nextThreshold - $prevThreshold);

        // Streak — 連續天數的「有記錄日」（cycle / symptom / dodo_checkin 任一即算）
        $today = now()->toDateString();
        $streak = $this->computeStreak($u->id, $today);

        // 過去 30 天的累積動作
        $thirtyDaysAgo = now()->subDays(30)->toDateString();
        $stats = [
            'cycles_logged' => Cycle::where('user_id', $u->id)->where('start_date', '>=', $thirtyDaysAgo)->count(),
            'symptoms_logged' => CycleSymptom::where('user_id', $u->id)->where('logged_on', '>=', $thirtyDaysAgo)->count(),
            'dodo_checkins' => DodoCheckin::where('user_id', $u->id)->where('checked_on', '>=', $thirtyDaysAgo)->count(),
        ];

        $outfitState = (array) ($u->outfit_state ?? []);
        $owned = (array) ($outfitState['owned'] ?? []);

        // 簡單的 milestone 預覽（已解 / 待解）
        $milestones = [
            ['code' => 'first_cycle', 'name' => '第一次記錄', 'icon' => '🌸', 'unlocked' => count($owned) > 0 || $totalXp >= 30],
            ['code' => 'streak_7', 'name' => '連續 7 天', 'icon' => '🔥', 'unlocked' => $streak >= 7, 'progress' => min(7, $streak), 'target' => 7],
            ['code' => 'streak_30', 'name' => '連續 30 天', 'icon' => '⭐', 'unlocked' => $streak >= 30, 'progress' => min(30, $streak), 'target' => 30],
            ['code' => 'cycle_streak_3_months', 'name' => '記錄 3 個完整週期', 'icon' => '🌙', 'unlocked' => $stats['cycles_logged'] >= 3, 'progress' => min(3, $stats['cycles_logged']), 'target' => 3],
            ['code' => 'level_5', 'name' => '達到 Lv 5', 'icon' => '✨', 'unlocked' => $level >= 5, 'progress' => $level, 'target' => 5],
            ['code' => 'level_10', 'name' => '達到 Lv 10', 'icon' => '🏆', 'unlocked' => $level >= 10, 'progress' => $level, 'target' => 10],
        ];

        return response()->json([
            'data' => [
                'level' => $level,
                'total_xp' => $totalXp,
                'progress_in_level' => $progressInLevel,
                'need_for_next_level' => $needForNext,
                'next_level_at_xp' => $nextThreshold,
                'streak_days' => $streak,
                'last_30_days' => $stats,
                'outfit_owned' => $owned,
                'outfit_equipped' => $outfitState['equipped'] ?? null,
                'milestones' => $milestones,
            ],
        ]);
    }

    /**
     * 從今天往回算連續天數，任何一張表有當天記錄就算 streak day。
     */
    private function computeStreak(int $userId, string $today): int
    {
        // 一次 union 取出 distinct dates
        $dates = collect()
            ->merge(Cycle::where('user_id', $userId)->pluck('start_date'))
            ->merge(CycleSymptom::where('user_id', $userId)->pluck('logged_on'))
            ->merge(DodoCheckin::where('user_id', $userId)->pluck('checked_on'))
            ->map(fn ($d) => is_string($d) ? substr($d, 0, 10) : $d->toDateString())
            ->unique()
            ->sort()
            ->values();

        if ($dates->isEmpty()) {
            return 0;
        }

        $streak = 0;
        $cursor = \Carbon\Carbon::parse($today);
        while ($dates->contains($cursor->toDateString())) {
            $streak++;
            $cursor->subDay();
        }

        return $streak;
    }
}
