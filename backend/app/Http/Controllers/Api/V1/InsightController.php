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

    public function pms(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $this->gate->isPremium($user)) {
            return response()->json([
                'error' => 'premium_required',
                'message' => 'PMS 模式分析是 Premium 功能。',
                'paywall_redirect' => '/subscription',
            ], 402);
        }

        $pattern = $this->ai->detectPmsPattern($user);

        // P5.2：成功讀到一個 pattern → calendar.insight_read（每天每 user 算一次）
        if ($pattern !== null) {
            $today = now()->toDateString();
            $this->gamification->publish(
                $user,
                CalendarEventCatalog::INSIGHT_READ,
                ['kind' => 'pms', 'sample_cycles' => $pattern->sampleCycles, 'date' => $today],
                IdempotencyKey::make(CalendarEventCatalog::INSIGHT_READ, $user->id, 'pms', $today),
            );
        }

        return response()->json([
            'data' => $pattern?->toArray(),
        ]);
    }
}
