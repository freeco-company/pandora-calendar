<?php

namespace App\Services\Subscription;

use App\Models\Subscription;
use App\Models\SubscriptionEvent;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * ECPay 訂閱 — 用 ECPay 「定期定額」服務做 web 端付款。
 *
 * Phase 0-2 行為：
 * - generateCheckoutForm()：產生 ECPay AIO 付款 form data（CheckMacValue 計算）
 * - handleNotify()：解 webhook，verify CheckMacValue，upsert subscription
 *
 * 月曆主走 IAP（iOS / Android），ECPay 是 web fallback / 退款處理 / 客服管道。
 */
class EcpayClient
{
    public const PRODUCT_MONTHLY = 'calendar.premium.monthly';
    public const PRODUCT_ANNUAL = 'calendar.premium.annual';

    public function __construct(
        private readonly string $merchantId,
        private readonly string $hashKey,
        private readonly string $hashIv,
    ) {}

    public static function fromConfig(): self
    {
        return new self(
            merchantId: (string) config('pandora.subscription.ecpay_merchant_id'),
            hashKey: (string) config('pandora.subscription.ecpay_hash_key'),
            hashIv: (string) config('pandora.subscription.ecpay_hash_iv'),
        );
    }

    public function generateCheckoutForm(User $user, string $productId, string $returnUrl): array
    {
        $amount = $this->amountFor($productId);
        $tradeNo = 'CAL'.str_pad((string) $user->id, 6, '0', STR_PAD_LEFT).now()->format('YmdHis');

        $params = [
            'MerchantID' => $this->merchantId,
            'MerchantTradeNo' => $tradeNo,
            'MerchantTradeDate' => now()->format('Y/m/d H:i:s'),
            'PaymentType' => 'aio',
            'TotalAmount' => $amount,
            'TradeDesc' => '潘朵拉月曆 Premium',
            'ItemName' => $this->itemNameFor($productId),
            'ReturnURL' => $returnUrl,
            'ChoosePayment' => 'Credit',
            'PeriodAmount' => $amount,
            'PeriodType' => $productId === self::PRODUCT_ANNUAL ? 'Y' : 'M',
            'Frequency' => 1,
            'ExecTimes' => $productId === self::PRODUCT_ANNUAL ? 5 : 12,
        ];

        $params['CheckMacValue'] = $this->checkMacValue($params);

        return $params;
    }

    public function handleNotify(array $payload): ?Subscription
    {
        $signature = $payload['CheckMacValue'] ?? '';
        unset($payload['CheckMacValue']);

        if (! hash_equals($this->checkMacValue($payload), $signature)) {
            throw new \RuntimeException('ECPay CheckMacValue mismatch — possible tampering');
        }

        if (($payload['RtnCode'] ?? 0) != 1) {
            return null; // failed payment
        }

        $tradeNo = $payload['MerchantTradeNo'];
        $userId = (int) ltrim(substr($tradeNo, 3, 6), '0');
        $user = User::findOrFail($userId);

        $isAnnual = ($payload['PeriodType'] ?? 'M') === 'Y';
        $productId = $isAnnual ? self::PRODUCT_ANNUAL : self::PRODUCT_MONTHLY;
        $startsAt = CarbonImmutable::parse($payload['PaymentDate'] ?? now());
        $endsAt = $isAnnual ? $startsAt->addYear() : $startsAt->addMonth();

        return DB::transaction(function () use ($user, $productId, $tradeNo, $startsAt, $endsAt, $payload) {
            $sub = Subscription::updateOrCreate(
                ['platform' => 'ecpay', 'original_transaction_id' => $tradeNo],
                [
                    'user_id' => $user->id,
                    'product_id' => $productId,
                    'starts_at' => $startsAt,
                    'ends_at' => $endsAt,
                    'auto_renew' => true,
                    'status' => 'active',
                    'raw_payload' => $payload,
                ],
            );

            SubscriptionEvent::create([
                'subscription_id' => $sub->id,
                'event_type' => 'initial',
                'payload' => $payload,
                'occurred_at' => now(),
            ]);

            return $sub;
        });
    }

    private function amountFor(string $productId): int
    {
        return $productId === self::PRODUCT_ANNUAL ? 899 : 99;
    }

    private function itemNameFor(string $productId): string
    {
        return $productId === self::PRODUCT_ANNUAL ? '潘朵拉月曆 Premium 年付' : '潘朵拉月曆 Premium 月付';
    }

    private function checkMacValue(array $params): string
    {
        ksort($params, SORT_STRING | SORT_FLAG_CASE);
        $kv = collect($params)->map(fn ($v, $k) => "{$k}={$v}")->implode('&');
        $raw = "HashKey={$this->hashKey}&{$kv}&HashIV={$this->hashIv}";
        $encoded = strtolower(urlencode($raw));
        $encoded = str_replace(
            ['%2d', '%5f', '%2e', '%21', '%2a', '%28', '%29'],
            ['-', '_', '.', '!', '*', '(', ')'],
            $encoded,
        );

        return strtoupper(hash('sha256', $encoded));
    }
}
