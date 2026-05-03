<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\BbtReading;
use App\Services\Health\BbtAnalyzer;
use App\Services\Subscription\FeatureGate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * P2-8 — 基礎體溫 (BBT)。每日 1 筆，給想懷孕族群看排卵雙相曲線。
 *
 * Gate：
 * - 記錄 / 看清單：免費（基本記錄不擋，鼓勵留資料）
 * - biphasic 雙相偵測分析：Premium-only（這是 insight 層）
 */
class BbtController extends Controller
{
    public function __construct(
        private readonly BbtAnalyzer $analyzer,
        private readonly FeatureGate $gate,
    ) {}

    public function biphasic(Request $request): JsonResponse
    {
        if (! $this->gate->isPremium($request->user())) {
            return response()->json([
                'error' => 'premium_required',
                'message' => 'BBT 雙相偵測是 Premium 功能。',
                'paywall_redirect' => '/subscription',
            ], 402);
        }

        return response()->json([
            'data' => $this->analyzer->detectBiphasicShift($request->user()->id),
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $from = $request->query('from', now()->subDays(60)->toDateString());
        $to = $request->query('to', now()->toDateString());

        $rows = BbtReading::query()
            ->where('user_id', $request->user()->id)
            ->whereBetween('measured_on', [$from, $to])
            ->orderBy('measured_on')
            ->get(['id', 'measured_on', 'temperature_c', 'note']);

        return response()->json([
            'data' => $rows,
            'avg_low' => round($rows->where('temperature_c', '<', 36.7)->avg('temperature_c') ?? 0, 2),
            'avg_high' => round($rows->where('temperature_c', '>=', 36.7)->avg('temperature_c') ?? 0, 2),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'measured_on' => ['required', 'date'],
            'temperature_c' => ['required', 'numeric', 'between:35.0,38.5'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $row = BbtReading::query()->updateOrCreate(
            ['user_id' => $request->user()->id, 'measured_on' => $data['measured_on']],
            ['temperature_c' => $data['temperature_c'], 'note' => $data['note'] ?? null],
        );

        return response()->json(['data' => $row], 201);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $row = BbtReading::query()->where('user_id', $request->user()->id)->findOrFail($id);
        $row->delete();

        return response()->json(['status' => 'ok']);
    }
}
