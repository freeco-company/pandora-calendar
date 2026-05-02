<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * P5.3 ADR-009：frontend polling endpoint 拉 gamification pending events
 * （level_up / achievement_unlocked / outfit_unlocked）給 toast 用。
 *
 * 一次拉一筆、拉走就清。前端可週期性 polling 或在關鍵動作後呼叫。
 */
class MeGamificationController extends Controller
{
    public function pending(Request $request): JsonResponse
    {
        $uuid = (string) ($request->user()->identity_uuid ?? '');
        if ($uuid === '') {
            return response()->json(['data' => null]);
        }

        $key = "gamification:pending:{$uuid}";
        $payload = Cache::pull($key);

        return response()->json(['data' => $payload]);
    }

    public function dodo(Request $request): JsonResponse
    {
        $u = $request->user();
        $outfit = (array) ($u->outfit_state ?? []);

        return response()->json([
            'data' => [
                'level' => (int) ($u->level ?? 1),
                'total_xp' => (int) ($u->total_xp ?? 0),
                'outfit_state' => $outfit ?: null,
                'mood' => $this->computeMood($u),
            ],
        ]);
    }

    public function pet(Request $request): JsonResponse
    {
        $u = $request->user();

        return response()->json([
            'data' => [
                'species' => $u->pet_species,
                'nickname' => $u->pet_nickname,
                'level' => (int) ($u->level ?? 1),
            ],
        ]);
    }

    /**
     * Mood 推導（純函數，無外部 dep）：
     *   level >= 10 → 'cheerful'
     *   level >= 3  → 'content'
     *   else        → 'sleepy'
     * outfit equipped 帶 'crown' / 'angel_wings' → 'celebrating'（覆蓋上面）
     */
    private function computeMood(\App\Models\User $u): string
    {
        $outfit = (array) ($u->outfit_state ?? []);
        $equipped = (string) ($outfit['equipped'] ?? '');
        if ($equipped !== '' && (str_contains($equipped, 'crown') || str_contains($equipped, 'angel'))) {
            return 'celebrating';
        }

        $level = (int) ($u->level ?? 1);
        return match (true) {
            $level >= 10 => 'cheerful',
            $level >= 3 => 'content',
            default => 'sleepy',
        };
    }
}
