<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Commerce\ProductLinkResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * 婕樂纖商品連結（P5+，極嚴守紅線）。
 *
 * 此 endpoint **只在「我的 → 婕樂纖會員」深層頁面**呼叫。
 * gate 不通過時返回空陣列，App 端就什麼都不顯示。
 *
 * App 主流程（月曆 / 記錄 / 朵朵 / 我的）絕對不能 call 這個 endpoint。
 */
class CommerceController extends Controller
{
    public function __construct(private readonly ProductLinkResolver $resolver) {}

    public function productLinks(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->resolver->resolveFor($request->user()),
            'gate_passed' => $this->resolver->passesGate($request->user()),
        ]);
    }
}
