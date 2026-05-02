<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use App\Models\SubscriptionEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Google Play Real-Time Developer Notifications.
 * https://developer.android.com/google/play/billing/rtdn-reference
 *
 * payload (Pub/Sub push):
 * {
 *   "message": { "data": "<base64 json>", "messageId": "..." },
 *   "subscription": "projects/.../subscriptions/..."
 * }
 *
 * decoded data:
 * {
 *   "version": "1.0",
 *   "packageName": "com.jerosse.pandora.calendar",
 *   "subscriptionNotification": {
 *     "version": "1.0",
 *     "notificationType": 1..13,
 *     "purchaseToken": "...",
 *     "subscriptionId": "calendar.premium.monthly"
 *   }
 * }
 */
class GoogleRtdnController extends Controller
{
    private const NOTIF_PURCHASED = 4;
    private const NOTIF_RENEWED = 2;
    private const NOTIF_CANCELED = 3;
    private const NOTIF_EXPIRED = 13;
    private const NOTIF_GRACE = 6;
    private const NOTIF_REVOKED = 12;

    public function handle(Request $request): JsonResponse
    {
        $message = $request->input('message.data', '');
        if (! $message) {
            return response()->json(['error' => 'no data']);
        }

        $payload = json_decode(base64_decode($message), true) ?? [];
        $notif = $payload['subscriptionNotification'] ?? null;
        if (! $notif) {
            return response()->json(['ignored' => 'not a subscription notification']);
        }

        $sub = Subscription::where('platform', 'google')
            ->where('original_transaction_id', $notif['purchaseToken'])
            ->first();

        if (! $sub) {
            Log::warning('google-rtdn-unknown-tx', $notif);

            return response()->json(['ignored' => 'unknown']);
        }

        match ((int) ($notif['notificationType'] ?? 0)) {
            self::NOTIF_PURCHASED, self::NOTIF_RENEWED => $sub->update(['status' => 'active', 'renewed_at' => now()]),
            self::NOTIF_GRACE => $sub->update(['status' => 'grace']),
            self::NOTIF_CANCELED => $sub->update(['status' => 'cancelled', 'cancelled_at' => now(), 'auto_renew' => false]),
            self::NOTIF_EXPIRED => $sub->update(['status' => 'expired']),
            self::NOTIF_REVOKED => $sub->update(['status' => 'refunded', 'cancelled_at' => now()]),
            default => null,
        };

        SubscriptionEvent::create([
            'subscription_id' => $sub->id,
            'event_type' => 'rtdn_'.($notif['notificationType'] ?? 'unknown'),
            'payload' => $payload,
            'occurred_at' => now(),
        ]);

        return response()->json(['ok' => true]);
    }
}
