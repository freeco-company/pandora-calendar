<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Action\ProtocolInsightSurfacer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Protocol insight — 朵朵主動報「我發現 X 對妳 work」。
 *
 * GET  /api/v1/protocol-insights/active           — 當前可顯示 1 個 insight，沒則 null
 * POST /api/v1/protocol-insights/{key}/dismiss    — dismiss 後 7 天不再顯示同個 key
 *
 * 對 free / premium 都開（insight 是 retention 槓桿，不該卡 paywall）。
 */
class ProtocolInsightController extends Controller
{
    public function __construct(
        private readonly ProtocolInsightSurfacer $surfacer,
    ) {}

    public function active(Request $request): JsonResponse
    {
        $insight = $this->surfacer->activeFor((int) $request->user()->id);

        if ($insight === null) {
            return response()->json(['data' => null]);
        }

        return response()->json([
            'data' => [
                'insight_key' => $insight['insight_key'],
                'message' => $insight['message'],
                'action_cta' => $insight['action_cta'] ?? null,
                'source' => $insight['source'],
            ],
        ]);
    }

    public function dismiss(Request $request, string $key): JsonResponse
    {
        // key 是由 surfacer 生成的形式：類別:phase:識別子；最長保護
        if (strlen($key) > 120) {
            abort(422, 'invalid insight key');
        }

        $entry = $this->surfacer->dismiss((int) $request->user()->id, $key);

        return response()->json([
            'data' => [
                'dismissed' => true,
                'insight_key' => $entry->insight_key,
                'dismissed_at' => $entry->dismissed_at?->toIso8601String(),
            ],
        ], 201);
    }
}
