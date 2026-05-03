<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Economy\DodoCoinService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Wave 13 — DodoCoin economy endpoints。
 *
 * 紅線：
 *   - balance / history 公開（自己看自己）
 *   - spend endpoint 不對外（user-facing 走特定 action：feed / unlock chapter / equip outfit）
 *   - 朵朵幣不能買 Premium 功能（保訂閱純度）
 */
class EconomyController extends Controller
{
    public function __construct(private readonly DodoCoinService $coins) {}

    public function balance(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'data' => [
                'balance' => $this->coins->balance((int) $user->id),
            ],
        ]);
    }

    public function history(Request $request): JsonResponse
    {
        $user = $request->user();
        $limit = (int) $request->query('limit', 50);
        $limit = max(1, min(200, $limit));

        $rows = $this->coins->history((int) $user->id, $limit);

        return response()->json([
            'data' => $rows->map(fn ($t) => [
                'id' => $t->id,
                'delta' => (int) $t->delta,
                'source' => $t->source,
                'metadata' => $t->metadata,
                'balance_after' => (int) $t->balance_after,
                'created_at' => $t->created_at?->toIso8601String(),
            ])->all(),
        ]);
    }
}
