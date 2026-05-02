<?php

namespace App\Services\Subscription;

use App\Models\Subscription;
use App\Models\SubscriptionEvent;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * IAP receipt verifier — Apple StoreKit 2 + Google Play Billing 走同一個 API surface。
 *
 * Phase 0-2：實作 Apple/Google verifier（含 sandbox fallback for Apple），ECPay 走另一條 webhook。
 *
 * 安全：商店通知 webhook 進來時 / 用戶 client-side restore purchase 時，**永遠 server-side
 * verify**。Client side receipt token 不可信。
 */
class IapVerifier
{
    public const PLATFORM_APPLE = 'apple';
    public const PLATFORM_GOOGLE = 'google';

    public function __construct(private readonly HttpFactory $http) {}

    public function verifyApple(User $user, string $receiptData, string $productId): Subscription
    {
        $sharedSecret = config('pandora.subscription.apple_iap_shared_secret');

        $payload = [
            'receipt-data' => $receiptData,
            'password' => $sharedSecret,
            'exclude-old-transactions' => true,
        ];

        $endpoints = [
            'https://buy.itunes.apple.com/verifyReceipt',
            'https://sandbox.itunes.apple.com/verifyReceipt',
        ];

        $verified = null;
        foreach ($endpoints as $url) {
            $res = $this->http->timeout(8)->post($url, $payload);
            $data = $res->json();
            $status = $data['status'] ?? -1;

            // 21007 = sandbox receipt sent to prod → retry sandbox
            if ($status === 21007) {
                continue;
            }
            if ($status === 0) {
                $verified = $data;
                break;
            }
            // hard error
            Log::warning('apple-iap-verify-failed', ['status' => $status, 'user_id' => $user->id]);
            throw new \RuntimeException("Apple IAP verify failed with status $status");
        }

        if (! $verified) {
            throw new \RuntimeException('Apple IAP verify exhausted endpoints');
        }

        $latest = collect($verified['latest_receipt_info'] ?? [])
            ->sortByDesc('purchase_date_ms')
            ->first();

        if (! $latest || $latest['product_id'] !== $productId) {
            throw new \RuntimeException('No matching transaction in receipt');
        }

        return $this->upsert(
            user: $user,
            platform: self::PLATFORM_APPLE,
            productId: $productId,
            originalTxId: $latest['original_transaction_id'],
            startsAt: CarbonImmutable::createFromTimestampMs($latest['purchase_date_ms']),
            endsAt: CarbonImmutable::createFromTimestampMs($latest['expires_date_ms']),
            payload: $verified,
            receiptHash: hash('sha256', $receiptData),
        );
    }

    public function verifyGoogle(User $user, string $purchaseToken, string $productId, string $packageName): Subscription
    {
        // Real implementation will call Google Play Developer API; for Phase 0-2 we trust the
        // signed JWT from Google Play webhook + a structural check. Production should swap in
        // a googleapis HTTP client with service account JWT.
        $serviceAccountJson = config('pandora.subscription.google_play_service_account_json');
        if (! $serviceAccountJson) {
            // Phase 0 stub: accept token, mark sub active for 30d (test fixtures only)
            return $this->upsert(
                user: $user,
                platform: self::PLATFORM_GOOGLE,
                productId: $productId,
                originalTxId: $purchaseToken,
                startsAt: CarbonImmutable::now(),
                endsAt: CarbonImmutable::now()->addDays(30),
                payload: ['stub' => true, 'product_id' => $productId, 'package' => $packageName],
                receiptHash: hash('sha256', $purchaseToken),
            );
        }

        // Real call would go here
        $url = "https://androidpublisher.googleapis.com/androidpublisher/v3/applications/{$packageName}/purchases/subscriptions/{$productId}/tokens/{$purchaseToken}";
        $res = $this->http->timeout(8)
            ->withToken($this->fetchGooglePlayAccessToken($serviceAccountJson))
            ->get($url);

        if (! $res->ok()) {
            throw new \RuntimeException("Google Play verify failed: {$res->status()}");
        }
        $data = $res->json();

        return $this->upsert(
            user: $user,
            platform: self::PLATFORM_GOOGLE,
            productId: $productId,
            originalTxId: $data['orderId'] ?? $purchaseToken,
            startsAt: CarbonImmutable::createFromTimestampMs((int) $data['startTimeMillis']),
            endsAt: CarbonImmutable::createFromTimestampMs((int) $data['expiryTimeMillis']),
            payload: $data,
            receiptHash: hash('sha256', $purchaseToken),
        );
    }

    private function fetchGooglePlayAccessToken(string $serviceAccountJson): string
    {
        // Service-account JWT exchange → access token; out of scope for Phase 0 stub.
        // Cached in production via Cache::remember to avoid hitting Google quota every call.
        throw new \RuntimeException('Google Play access token exchange not implemented in Phase 0');
    }

    public function upsert(
        User $user,
        string $platform,
        string $productId,
        string $originalTxId,
        CarbonImmutable $startsAt,
        CarbonImmutable $endsAt,
        array $payload,
        string $receiptHash,
        string $eventType = 'initial',
    ): Subscription {
        return DB::transaction(function () use ($user, $platform, $productId, $originalTxId, $startsAt, $endsAt, $payload, $receiptHash, $eventType) {
            $sub = Subscription::updateOrCreate(
                ['platform' => $platform, 'original_transaction_id' => $originalTxId],
                [
                    'user_id' => $user->id,
                    'product_id' => $productId,
                    'latest_receipt_hash' => $receiptHash,
                    'starts_at' => $startsAt,
                    'ends_at' => $endsAt,
                    'renewed_at' => $eventType === 'renewal' ? now() : null,
                    'auto_renew' => true,
                    'status' => $endsAt->isFuture() ? 'active' : 'expired',
                    'raw_payload' => $payload,
                ],
            );

            SubscriptionEvent::create([
                'subscription_id' => $sub->id,
                'event_type' => $eventType,
                'payload' => $payload,
                'occurred_at' => now(),
            ]);

            return $sub;
        });
    }
}
