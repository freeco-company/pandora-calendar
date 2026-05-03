<?php

namespace App\Http\Middleware;

use App\Services\Commerce\DeepLinkGate;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 確保 request 的 user 通過婕樂纖深層 gate（P5）。
 *
 * Gate 不通過 → 403 + reason，整個 controller 不會被叫到。
 * 只用在「已被 user 主動進到深層頁」之後的細部資料 endpoint，
 * 不要套到主流程任何 endpoint（紅線 1）。
 */
class EnsureMotherCustomer
{
    public function __construct(private readonly DeepLinkGate $gate) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $result = $this->gate->evaluate($user);
        if (! $result['eligible']) {
            return response()->json([
                'message' => 'Not eligible',
                'reasons' => $result['reasons'],
            ], 403);
        }

        return $next($request);
    }
}
