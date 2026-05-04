<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Calendar\Streak\DailyLoginStreakService;
use App\Services\Calendar\Streak\GroupStreakClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * SPEC-cross-app-streak Phase 1.B — read-only streak endpoint (calendar).
 *
 * GET /api/streak/today — returns current state plus a fresh recordLogin()
 * summary so the FE can decide whether to flash a toast (is_first_today /
 * is_milestone) on app boot, in one round-trip.
 */
class StreakController extends Controller
{
    public function __construct(
        private readonly DailyLoginStreakService $service,
        private readonly GroupStreakClient $groupStreak,
    ) {}

    public function today(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $recorded = $this->service->recordLogin($user);
        $snapshot = $this->service->snapshot($user);

        // Phase 5B — overlay 集團 master streak（fail-soft：null 時 frontend 只顯自家）。
        // 必須先 recordLogin → 自家 streak bump 會經 publisher 進 outbox，flush worker
        // 再餵到 py-service 觸發 group_streak_service.bump()；同一 request 內讀 group 多半
        // 還拿不到剛剛 bump 的最新值（背景 worker 異步），但對下次 boot 就是即時的。
        $group = $user->identity_uuid
            ? $this->groupStreak->fetch($user->identity_uuid)
            : null;

        return response()->json([
            'current_streak' => $recorded['streak'],
            'longest_streak' => max($recorded['longest_streak'], $snapshot['longest_streak']),
            'is_first_today' => $recorded['is_first_today'],
            'is_milestone' => $recorded['is_milestone'],
            'milestone_label' => $recorded['milestone_label'],
            'today_date' => $recorded['today_date'],
            // Frontend surfaces unlocks in StreakToast reveal animation.
            // null = no milestone fired this call (or fail-soft skipped).
            'unlocks' => $recorded['unlocks'] ?? null,
            // Phase 5B — cross-App master streak overlay；null = unbound user / py-service unavailable.
            'group' => $group,
        ]);
    }
}
