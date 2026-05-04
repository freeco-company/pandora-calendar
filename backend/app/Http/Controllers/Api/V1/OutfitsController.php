<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Achievement;
use App\Services\Gamification\OutfitCatalog;
use App\Services\Subscription\FeatureGate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OutfitsController extends Controller
{
    public function __construct(private readonly FeatureGate $gate) {}

    public function index(Request $request): JsonResponse
    {
        $u = $request->user();
        $achievements = Achievement::query()->where('user_id', $u->id)->pluck('achievement_key')->all();

        // streak 算（同 AchievementChecker 邏輯，簡化版）
        $streak = $this->streak($u->id);

        // trial 期視為 Premium（freemium funnel）
        $isPremium = $this->gate->isPremium($u);
        $level = (int) ($u->level ?? 1);

        $context = [
            'level' => $level,
            'streak' => $streak,
            'achievements' => $achievements,
            'is_premium' => $isPremium,
        ];

        $outfitState = (array) ($u->outfit_state ?? []);
        $equipped = (string) ($outfitState['equipped'] ?? 'none');

        $rows = collect(OutfitCatalog::all())->map(function (array $o) use ($context, $equipped) {
            $unlocked = OutfitCatalog::isUnlocked($o, $context);
            return [
                'code' => $o['code'],
                'name' => $o['name'],
                'hint' => $o['hint'],
                'rarity' => $o['rarity'],
                'icon' => $o['icon'],
                'unlock' => $o['unlock'],
                'svg_url' => "/character/outfits/outfit_{$o['code']}_overlay.svg",
                'unlocked' => $unlocked,
                'equipped' => $unlocked && $equipped === $o['code'],
            ];
        })->values();

        return response()->json([
            'data' => [
                'unlocked_count' => $rows->where('unlocked', true)->count(),
                'total' => $rows->count(),
                'equipped' => $equipped,
                'outfits' => $rows,
            ],
        ]);
    }

    public function equip(Request $request): JsonResponse
    {
        $data = $request->validate(['code' => ['required', 'string']]);
        $u = $request->user();

        // 不允許 equip 沒解鎖的
        $achievements = Achievement::query()->where('user_id', $u->id)->pluck('achievement_key')->all();
        $context = [
            'level' => (int) ($u->level ?? 1),
            'streak' => $this->streak($u->id),
            'achievements' => $achievements,
            'is_premium' => $this->gate->isPremium($u),
        ];

        if ($data['code'] !== 'none') {
            $outfit = collect(OutfitCatalog::all())->firstWhere('code', $data['code']);
            if (! $outfit) {
                return response()->json(['error' => 'unknown_outfit'], 422);
            }
            if (! OutfitCatalog::isUnlocked($outfit, $context)) {
                return response()->json(['error' => 'outfit_locked'], 403);
            }
        }

        $state = (array) ($u->outfit_state ?? []);
        $state['equipped'] = $data['code'];
        $u->outfit_state = $state;
        $u->save();

        return response()->json(['data' => ['equipped' => $data['code']]]);
    }

    private function streak(int $userId): int
    {
        $dates = collect()
            ->merge(\App\Models\Cycle::where('user_id', $userId)->pluck('start_date'))
            ->merge(\App\Models\CycleSymptom::where('user_id', $userId)->pluck('logged_on'))
            ->merge(\App\Models\DodoCheckin::where('user_id', $userId)->pluck('checked_on'))
            ->merge(\App\Models\BbtReading::where('user_id', $userId)->pluck('measured_on'))
            ->map(fn ($d) => is_string($d) ? substr($d, 0, 10) : $d->toDateString())
            ->unique()
            ->sort()
            ->values();

        if ($dates->isEmpty()) {
            return 0;
        }
        $streak = 0;
        $cursor = \Carbon\Carbon::today();
        while ($dates->contains($cursor->toDateString())) {
            $streak++;
            $cursor->subDay();
        }

        return $streak;
    }
}
