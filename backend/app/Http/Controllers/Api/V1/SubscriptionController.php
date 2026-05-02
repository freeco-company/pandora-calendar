<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Subscription\EcpayClient;
use App\Services\Subscription\EntitlementResolver;
use App\Services\Subscription\IapVerifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubscriptionController extends Controller
{
    public function __construct(
        private readonly IapVerifier $iap,
        private readonly EntitlementResolver $entitlements,
    ) {}

    public function me(Request $request): JsonResponse
    {
        $entitlements = $this->entitlements->resolve($request->user());

        return response()->json(['data' => $entitlements->toArray()]);
    }

    public function products(): JsonResponse
    {
        return response()->json([
            'data' => [
                [
                    'id' => 'calendar.premium.monthly',
                    'title' => 'Premium 月付',
                    'price_twd' => 99,
                    'period' => 'month',
                    'discount' => null,
                ],
                [
                    'id' => 'calendar.premium.annual',
                    'title' => 'Premium 年付',
                    'price_twd' => 899,
                    'period' => 'year',
                    'discount' => '24% off',
                    'monthly_equivalent' => 75,
                ],
            ],
            'currency' => 'TWD',
            'features' => [
                '朵朵 check-in 無限次',
                '多年週期歷史',
                'PMS 模式分析',
                '孕期模式',
                '跨產品同步（meal / 肌膚）',
                '每週朵朵 PDF 報告',
                'HealthKit / Health Connect',
            ],
        ]);
    }

    public function verifyApple(Request $request): JsonResponse
    {
        $data = $request->validate([
            'receipt_data' => ['required', 'string'],
            'product_id' => ['required', 'string'],
        ]);

        $sub = $this->iap->verifyApple($request->user(), $data['receipt_data'], $data['product_id']);

        return response()->json([
            'data' => [
                'subscription_id' => $sub->id,
                'status' => $sub->status,
                'ends_at' => $sub->ends_at?->toAtomString(),
            ],
        ], 201);
    }

    public function verifyGoogle(Request $request): JsonResponse
    {
        $data = $request->validate([
            'purchase_token' => ['required', 'string'],
            'product_id' => ['required', 'string'],
            'package_name' => ['required', 'string'],
        ]);

        $sub = $this->iap->verifyGoogle(
            $request->user(),
            $data['purchase_token'],
            $data['product_id'],
            $data['package_name'],
        );

        return response()->json([
            'data' => [
                'subscription_id' => $sub->id,
                'status' => $sub->status,
                'ends_at' => $sub->ends_at?->toAtomString(),
            ],
        ], 201);
    }

    public function ecpayCheckout(Request $request): JsonResponse
    {
        $data = $request->validate([
            'product_id' => ['required', 'in:calendar.premium.monthly,calendar.premium.annual'],
            'return_url' => ['required', 'url'],
        ]);

        $form = EcpayClient::fromConfig()->generateCheckoutForm(
            $request->user(),
            $data['product_id'],
            $data['return_url'],
        );

        return response()->json([
            'data' => [
                'action_url' => 'https://payment.ecpay.com.tw/Cashier/AioCheckOut/V5',
                'form_params' => $form,
            ],
        ]);
    }
}
