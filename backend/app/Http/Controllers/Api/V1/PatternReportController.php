<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CyclePatternReport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Cycle pattern report endpoints — free 開放（升級槓桿在 protocol 完整 view，不在 report）。
 */
class PatternReportController extends Controller
{
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
        $reports = CyclePatternReport::where('user_id', $user->id)
            ->orderByDesc('generated_at')
            ->limit(24)
            ->get();

        return response()->json([
            'data' => $reports->map(fn ($r) => $this->present($r))->all(),
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
