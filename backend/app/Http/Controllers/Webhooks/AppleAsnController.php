<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use App\Models\SubscriptionEvent;
use App\Services\Subscription\Apple\AppleJwsVerifier;
use App\Services\Subscription\Apple\AppleJwsVerifyException;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Apple App Store Server Notifications V2 receiver.
 * https://developer.apple.com/documentation/appstoreservernotifications
 *
 * Verification (P2 上線就緒):
 *   AppleJwsVerifier 完整 verify ES256 signature + x5c cert chain to Apple Root CA G3.
 *   Verifier reject 時 → 401，避免被偽造 webhook 觸發訂閱狀態變動。
 *
 * Notification handling:
 *   SUBSCRIBED / DID_RENEW → upsert + extend ends_at
 *   DID_FAIL_TO_RENEW → status = grace
 *   EXPIRED → status = expired
 *   REFUND → status = refunded
 *   CONSUMPTION_REQUEST / GRACE_PERIOD_EXPIRED → 紀錄但不變狀態
 */
class AppleAsnController extends Controller
{
    public function __construct(private readonly AppleJwsVerifier $verifier) {}

    public function handle(Request $request): JsonResponse
    {
        $signedPayload = $request->input('signedPayload', '');
        if (! $signedPayload) {
            return response()->json(['error' => 'missing signedPayload'], 400);
        }

        try {
            $body = $this->verifier->verifyAndDecode($signedPayload);
            $body = $this->verifier->decodeNestedTransaction($body);
        } catch (AppleJwsVerifyException $e) {
            Log::warning('apple-asn-verify-rejected', ['err' => $e->getMessage()]);

            return response()->json(['error' => 'verify_failed', 'detail' => $e->getMessage()], 401);
        }

        $notificationType = $body['notificationType'] ?? '';
        $data = $body['data'] ?? [];

        $originalTxId = $data['renewalInfo']['originalTransactionId']
            ?? $data['transactionInfo']['originalTransactionId']
            ?? null;

        if (! $originalTxId) {
            return response()->json(['ignored' => 'no originalTransactionId']);
        }

        $sub = Subscription::where('platform', 'apple')
            ->where('original_transaction_id', $originalTxId)
            ->first();

        if (! $sub) {
            Log::warning('apple-asn-unknown-tx', ['tx' => $originalTxId, 'type' => $notificationType]);

            return response()->json(['ignored' => 'unknown transaction']);
        }

        match ($notificationType) {
            'SUBSCRIBED', 'DID_RENEW' => $this->extend($sub, $data, $notificationType),
            'DID_FAIL_TO_RENEW' => $sub->update(['status' => 'grace']),
            'EXPIRED' => $sub->update(['status' => 'expired']),
            'REFUND' => $sub->update(['status' => 'refunded', 'cancelled_at' => now()]),
            'DID_CHANGE_RENEWAL_STATUS' => $sub->update([
                'auto_renew' => (bool) ($data['renewalInfo']['autoRenewStatus'] ?? false),
            ]),
            default => null,
        };

        SubscriptionEvent::create([
            'subscription_id' => $sub->id,
            'event_type' => strtolower($notificationType ?: 'unknown'),
            'payload' => $body,
            'occurred_at' => now(),
        ]);

        return response()->json(['ok' => true]);
    }

    private function extend(Subscription $sub, array $data, string $type): void
    {
        $expiresMs = $data['transactionInfo']['expiresDate'] ?? null;
        if ($expiresMs) {
            $sub->ends_at = CarbonImmutable::createFromTimestampMs($expiresMs);
        }
        $sub->status = 'active';
        $sub->renewed_at = $type === 'DID_RENEW' ? now() : null;
        $sub->save();
    }
}
