<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Feedback;
use App\Models\SubscriptionPauseRequest;
use App\Services\Subscription\EcpayClient;
use App\Services\Subscription\EntitlementResolver;
use App\Services\Subscription\IapVerifier;
use App\Support\Sentry\SentryHelper;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

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

    /**
     * 暫停訂閱（IAP 平台無 native pause；這裡記 user 意願 + grant 內部 pause window，
     * 期間 entitlement 仍 active 但下個 renewal 用戶會在 Apple/Google 端自己取消）
     */
    public function pause(Request $request): JsonResponse
    {
        $data = $request->validate([
            'months' => ['required', 'integer', 'min:1', 'max:3'],
            'reason' => ['nullable', 'string', 'max:64'],
        ]);

        try {
            $req = SubscriptionPauseRequest::create([
                'user_id' => $request->user()->id,
                'reason' => $data['reason'] ?? null,
                'pause_months' => $data['months'],
                'granted_pause_until' => CarbonImmutable::today()->addMonths($data['months'])->toDateString(),
            ]);
        } catch (\Throwable $e) {
            SentryHelper::captureException($e, 'subscription', [
                'action' => 'pause',
                'user_uuid' => $request->user()->identity_uuid,
                'months' => $data['months'],
            ]);
            throw $e;
        }

        return response()->json([
            'data' => [
                'id' => $req->id,
                'pause_until' => $req->granted_pause_until?->toDateString(),
                'months' => $req->pause_months,
            ],
            'message' => '收到了，朵朵會等妳回來。',
        ], 201);
    }

    /**
     * 取消挽留紀錄 — 純 log，不擋 IAP；實際取消用戶在 Apple/Google 端做。
     */
    public function cancelFeedback(Request $request): JsonResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:64'],
            'message' => ['nullable', 'string', 'max:2000'],
        ]);

        Log::info('subscription.cancel_feedback', [
            'user_id' => $request->user()->id,
            'reason' => $data['reason'],
            'message' => $data['message'] ?? null,
        ]);

        // 也寫一份 feedback（content 類）方便 PM 看
        Feedback::create([
            'user_id' => $request->user()->id,
            'category' => 'content',
            'message' => '[cancel-feedback] reason='.$data['reason']."\n".($data['message'] ?? ''),
        ]);

        return response()->json([
            'message' => '謝謝妳告訴朵朵。',
        ], 201);
    }

    /**
     * 取消挽留 UI 文案 — 從 config/churn-intercept.php 讀
     */
    public function churnIntercept(): JsonResponse
    {
        return response()->json([
            'data' => [
                'reasons' => config('churn-intercept.reasons', []),
                'pause_options' => config('churn-intercept.pause_options', []),
                'win_back' => config('churn-intercept.win_back', ''),
            ],
        ]);
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
