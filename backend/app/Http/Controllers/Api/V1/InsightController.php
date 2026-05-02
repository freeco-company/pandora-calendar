<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\AI\AICycleInsight;
use App\Services\Subscription\FeatureGate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InsightController extends Controller
{
    public function __construct(
        private readonly AICycleInsight $ai,
        private readonly FeatureGate $gate,
    ) {}

    public function pms(Request $request): JsonResponse
    {
        if (! $this->gate->isPremium($request->user())) {
            return response()->json([
                'error' => 'premium_required',
                'message' => 'PMS 模式分析是 Premium 功能。',
            ], 402);
        }

        $pattern = $this->ai->detectPmsPattern($request->user());

        return response()->json([
            'data' => $pattern?->toArray(),
        ]);
    }
}
