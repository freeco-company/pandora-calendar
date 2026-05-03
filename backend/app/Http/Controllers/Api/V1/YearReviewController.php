<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Reports\YearReviewService;
use App\Services\Subscription\FeatureGate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class YearReviewController extends Controller
{
    public function __construct(
        private readonly YearReviewService $service,
        private readonly FeatureGate $gate,
    ) {}

    public function show(Request $request, int $year): JsonResponse
    {
        // 限制範圍避免抓未來 / 太久遠（資料只有 ≤ 2 年）
        $current = (int) now()->year;
        abort_if($year < $current - 5 || $year > $current, 404, 'year out of range');

        // Premium gate（年度回顧是 Premium 限定）
        if (! $this->gate->isPremium($request->user())) {
            return response()->json([
                'error' => 'premium_required',
                'message' => '年度回顧是 Premium 功能。',
                'paywall_redirect' => '/subscription',
            ], 402);
        }

        $result = $this->service->generate($request->user()->id, $year);

        return response()->json(['data' => $result]);
    }
}
