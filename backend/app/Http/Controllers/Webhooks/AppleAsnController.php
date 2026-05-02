<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use App\Models\SubscriptionEvent;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Apple App Store Server Notifications V2 receiver.
 * https://developer.apple.com/documentation/appstoreservernotifications
 *
 * Phase 0-2 處理 notificationType:
 * - SUBSCRIBED / DID_RENEW → upsert + extend ends_at
 * - DID_FAIL_TO_RENEW → status = grace
 * - EXPIRED → status = expired
 * - REFUND → status = refunded
 *
 * 完整 payload signed by Apple JWT；prod 必須 verify x5c chain（jose-php package）。
 * Phase 0 為求 demo 完整性，先 trust + log；P2 上架前必補 JWT verify。
 */
class AppleAsnController extends Controller
{
    public function handle(Request $request): JsonResponse
    {
        $signedPayload = $request->input('signedPayload', '');
        if (! $signedPayload) {
            return response()->json(['error' => 'missing signedPayload'], 400);
        }

        // Phase 0 stub: parse JWT body without verification.
        // P2 production: verify with Apple's x5c cert chain via firebase/php-jwt or jose-php.
        $body = $this->parseJwtBody($signedPayload);
        $notificationType = $body['notificationType'] ?? '';
        $data = $body['data'] ?? [];
        $originalTxId = $data['signedRenewalInfo']['originalTransactionId']
            ?? $data['signedTransactionInfo']['originalTransactionId']
            ?? null;

        if (! $originalTxId) {
            return response()->json(['error' => 'no originalTransactionId']);
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
            default => null,
        };

        SubscriptionEvent::create([
            'subscription_id' => $sub->id,
            'event_type' => strtolower($notificationType),
            'payload' => $body,
            'occurred_at' => now(),
        ]);

        return response()->json(['ok' => true]);
    }

    private function extend(Subscription $sub, array $data, string $type): void
    {
        $expiresMs = $data['signedTransactionInfo']['expiresDate'] ?? null;
        if ($expiresMs) {
            $sub->ends_at = CarbonImmutable::createFromTimestampMs($expiresMs);
        }
        $sub->status = 'active';
        $sub->renewed_at = $type === 'DID_RENEW' ? now() : null;
        $sub->save();
    }

    private function parseJwtBody(string $jwt): array
    {
        $parts = explode('.', $jwt);
        if (count($parts) < 2) {
            return [];
        }

        return json_decode(base64_decode(strtr($parts[1], '-_', '+/')), true) ?? [];
    }
}
