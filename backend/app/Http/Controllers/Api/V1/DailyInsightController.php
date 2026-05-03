<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Calendar\BodyRhythmCalculator;
use App\Services\Calendar\CyclePredictor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * GET /api/v1/insights/today?phase=luteal&day_offset=3
 *
 * 若 phase / day_offset 沒給，從 user 當前 body rhythm 推。
 * 找不到精確 day_offset 時 fallback 同 phase 中最接近、再不行回 phase day_offset 0。
 */
class DailyInsightController extends Controller
{
    public function __construct(
        private readonly CyclePredictor $predictor,
        private readonly BodyRhythmCalculator $rhythm,
    ) {}

    public function today(Request $request): JsonResponse
    {
        $data = $request->validate([
            'phase' => ['nullable', 'string', 'in:menstrual,follicular,ovulation,luteal,late'],
            'day_offset' => ['nullable', 'integer', 'min:0', 'max:60'],
        ]);

        $phase = $data['phase'] ?? null;
        $offset = $data['day_offset'] ?? null;

        if ($phase === null || $offset === null) {
            $prediction = $this->predictor->predict($request->user()->id);
            $rhythm = $this->rhythm->compute($prediction);
            $phase ??= $rhythm->phase === 'unknown' ? 'follicular' : $rhythm->phase;

            // cycleDay 1-base；day_offset 在 phase 內的 0-base 用 cycleDay-1（粗算）
            $offset ??= max(0, ($rhythm->cycleDay ?? 1) - 1);
        }

        $row = DB::table('daily_insights')
            ->where('phase', $phase)
            ->where('day_offset', '<=', $offset)
            ->orderByDesc('day_offset')
            ->first();

        if (! $row) {
            $row = DB::table('daily_insights')->where('phase', $phase)->orderBy('day_offset')->first();
        }

        if (! $row) {
            return response()->json([
                'data' => null,
                'message' => '今天還沒有對應的洞察文章。',
            ]);
        }

        return response()->json([
            'data' => [
                'phase' => $row->phase,
                'day_offset' => $row->day_offset,
                'title' => $row->title,
                'body' => $row->body,
                'cta_label' => $row->cta_label,
                'cta_route' => $row->cta_route,
                'source' => $row->source,
            ],
        ]);
    }
}
