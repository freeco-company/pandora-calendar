<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CyclePatternReport;
use App\Services\Subscription\FeatureGate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Cycle pattern report endpoints。
 *
 * 2026-05-04 freemium 分層：
 *   - free：最近 3 份 report
 *   - premium / trial：全歷史（最多 24 筆）
 */
class PatternReportController extends Controller
{
    private const FREE_LIMIT = 3;
    private const PREMIUM_LIMIT = 24;

    public function __construct(private readonly FeatureGate $gate) {}

    public function latest(Request $request): JsonResponse
    {
        $user = $request->user();
        $report = CyclePatternReport::where('user_id', $user->id)
            ->orderByDesc('generated_at')
            ->first();

        if (! $report) {
            return response()->json([
                'data' => null,
                'message' => '還沒有完整的週期報告。等這個週期結束朵朵會幫妳整理。',
            ]);
        }

        return response()->json(['data' => $this->present($report)]);
    }

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $isPremium = $this->gate->isPremium($user);
        $limit = $isPremium ? self::PREMIUM_LIMIT : self::FREE_LIMIT;

        $reports = CyclePatternReport::where('user_id', $user->id)
            ->orderByDesc('generated_at')
            ->limit($limit)
            ->get();

        $totalCount = CyclePatternReport::where('user_id', $user->id)->count();

        return response()->json([
            'data' => $reports->map(fn ($r) => $this->present($r))->all(),
            'tier' => $this->gate->effectiveTier($user),
            'total_count' => $totalCount,
            'limit' => $limit,
            'has_more_locked' => ! $isPremium && $totalCount > $limit,
        ]);
    }

    private function present(CyclePatternReport $r): array
    {
        return [
            'id' => $r->id,
            'cycle_id' => $r->cycle_id,
            'generated_at' => $r->generated_at?->toIso8601String(),
            'phase_summary' => $r->phase_summary,
            'top_actions' => $r->top_actions,
            'vs_previous' => $r->vs_previous,
            'dodo_message' => $r->dodo_message,
        ];
    }
}
