<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Achievement;
use App\Services\Gamification\AchievementCatalog;
use App\Services\Gamification\AchievementChecker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AchievementsController extends Controller
{
    public function __construct(private AchievementChecker $checker) {}

    public function index(Request $request): JsonResponse
    {
        $u = $request->user();

        // 主動 trigger check（避免漏 award，例如 user level up 之後 stats 改變但沒過 controller）
        $this->checker->checkAll($u);

        $unlocked = Achievement::query()
            ->where('user_id', $u->id)
            ->pluck('unlocked_at', 'achievement_key')
            ->toArray();

        $rows = collect(AchievementCatalog::all())->map(function (array $a) use ($unlocked) {
            $isUnlocked = isset($unlocked[$a['key']]);
            return [
                'key' => $a['key'],
                'name' => $a['name'],
                'hint' => $a['hint'],
                'kind' => $a['kind'],
                'tier' => $a['tier'],
                'badge' => $a['badge'],
                'badge_url' => "/badges/{$a['badge']}.svg",
                'xp' => $a['xp'],
                'target' => $a['target'],
                'unlocked' => $isUnlocked,
                'unlocked_at' => $isUnlocked ? $unlocked[$a['key']] : null,
            ];
        })->values();

        return response()->json([
            'data' => [
                'unlocked_count' => count($unlocked),
                'total' => $rows->count(),
                'achievements' => $rows,
            ],
        ]);
    }
}
