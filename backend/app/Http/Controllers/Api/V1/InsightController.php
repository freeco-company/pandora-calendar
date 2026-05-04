<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\AI\AICycleInsight;
use App\Services\Gamification\CalendarEventCatalog;
use App\Services\Gamification\GamificationPublisher;
use App\Services\Gamification\IdempotencyKey;
use App\Services\Subscription\FeatureGate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InsightController extends Controller
{
    public function __construct(
        private readonly AICycleInsight $ai,
        private readonly FeatureGate $gate,
        private readonly GamificationPublisher $gamification,
    ) {}

    /**
     * PMS 模式分析（freemium 放寬 2026-05-04）：
     *   - free：Top 3 症狀 + 對應建議；severity_trend = 'locked'
     *   - premium / trial：Top 5 全列表 + severity_trend
     *
     * 一律 200，不同 detail level，不破壞 frontend。
     */
    public function pms(Request $request): JsonResponse
    {
        $user = $request->user();
        $pattern = $this->ai->detectPmsPattern($user);

        if ($pattern !== null) {
            $today = now()->toDateString();
            $this->gamification->publish(
                $user,
                CalendarEventCatalog::INSIGHT_READ,
                ['kind' => 'pms', 'sample_cycles' => $pattern->sampleCycles, 'date' => $today],
                IdempotencyKey::make(CalendarEventCatalog::INSIGHT_READ, $user->id, 'pms', $today),
            );
        }

        $isPremium = $this->gate->isPremium($user);
        $tier = $this->gate->effectiveTier($user);

        if ($pattern === null) {
            return response()->json(['data' => null, 'tier' => $tier]);
        }

        $arr = $pattern->toArray();

        if (! $isPremium) {
            $top3 = array_slice($arr['top_symptoms'] ?? [], 0, 3);
            $counts = [];
            foreach ($top3 as $tag) {
                if (isset($arr['symptom_counts'][$tag])) {
                    $counts[$tag] = $arr['symptom_counts'][$tag];
                }
            }
            $suggestions = [];
            foreach ($top3 as $tag) {
                if (isset($arr['suggestions'][$tag])) {
                    $suggestions[$tag] = $arr['suggestions'][$tag];
                }
            }
            $arr['top_symptoms'] = $top3;
            $arr['symptom_counts'] = $counts;
            $arr['suggestions'] = $suggestions;
            $arr['severity_trend'] = 'locked';
            $arr['locked_features'] = ['top_4_5', 'severity_trend_detail'];
        }

        return response()->json(['data' => $arr, 'tier' => $tier]);
    }
}
