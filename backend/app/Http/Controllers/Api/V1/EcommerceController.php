<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Commerce\DeepLinkGate;
use App\Services\Commerce\MotherEcommerceConnector;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * 婕樂纖深層商品連結 endpoints（P5，極嚴守紅線）。
 *
 *   GET /v1/ecommerce/eligibility       — gate 結果（前端決定要不要 render section）
 *   GET /v1/ecommerce/recommendations   — 商品連結列表（middleware 已 gate，403 = 不 eligible）
 *
 * 主流程（月曆 / 記錄 / 朵朵 / 我的）絕對不能 call 這些 endpoint，
 * 只在「我的 → 婕樂纖會員」深層頁呼叫。
 */
class EcommerceController extends Controller
{
    public function __construct(
        private readonly DeepLinkGate $gate,
        private readonly MotherEcommerceConnector $connector,
    ) {}

    public function eligibility(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->gate->evaluate($request->user()),
        ]);
    }

    public function recommendations(Request $request): JsonResponse
    {
        // EnsureMotherCustomer middleware 已擋 fail user，這裡只負責回 list
        return response()->json([
            'data' => $this->connector->recommendationsFor($request->user()),
        ]);
    }
}
