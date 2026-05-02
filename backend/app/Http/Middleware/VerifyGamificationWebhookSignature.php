<?php

namespace App\Http\Middleware;

use App\Models\GamificationWebhookNonce;
use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * P5.3 ADR-009：py-service → calendar gamification webhook HMAC 驗證 +
 * replay 防護。
 *
 * 與 pandora-meal 的 VerifyGamificationWebhookSignature 同設計：
 *
 * Headers from py-service：
 *   X-Pandora-Timestamp: ISO-8601 UTC
 *   X-Pandora-Nonce: random hex (32 chars)
 *   X-Pandora-Signature: "sha256=" + hex(HMAC-SHA256(secret, "{timestamp}.{nonce}.{body}"))
 *
 * 失敗回 401。Replay 透過 gamification_webhook_nonces.event_id unique；
 * duplicate INSERT → 200 short-circuit（publisher 視為已投遞）。
 *
 * Secret 來源：config('gamification.webhook_secret')，預設 fallback 到
 * config('gamification.hmac_secret')（同一把）。
 */
class VerifyGamificationWebhookSignature
{
    public function handle(Request $request, Closure $next): Response
    {
        $secret = (string) config('gamification.webhook_secret');
        if ($secret === '') {
            Log::error('[GamificationWebhook] missing PANDORA_GAMIFICATION_WEBHOOK_SECRET');

            return response()->json(['error' => 'webhook secret not configured'], 500);
        }

        $timestamp = (string) $request->header('X-Pandora-Timestamp', '');
        $nonce = (string) $request->header('X-Pandora-Nonce', '');
        $signature = (string) $request->header('X-Pandora-Signature', '');

        if ($timestamp === '' || $nonce === '' || $signature === '') {
            return response()->json(['error' => 'missing signature headers'], 401);
        }

        $window = (int) config('gamification.webhook_window_seconds', 300);
        try {
            $ts = Carbon::parse($timestamp);
        } catch (\Exception) {
            return response()->json(['error' => 'invalid timestamp'], 401);
        }
        if (abs(Carbon::now()->diffInSeconds($ts, false)) > $window) {
            return response()->json(['error' => 'timestamp out of window'], 401);
        }

        $body = $request->getContent();
        $expected = 'sha256='.hash_hmac('sha256', "{$timestamp}.{$nonce}.{$body}", $secret);
        if (! hash_equals($expected, $signature)) {
            return response()->json(['error' => 'signature mismatch'], 401);
        }

        $eventId = (string) ($request->json('event_id') ?? '');
        $eventType = (string) ($request->json('event_type') ?? '');
        if ($eventId === '' || $eventType === '') {
            return response()->json(['error' => 'missing event_id or event_type'], 422);
        }

        try {
            GamificationWebhookNonce::create([
                'event_id' => $eventId,
                'event_type' => $eventType,
                'received_at' => now(),
            ]);
        } catch (QueryException $e) {
            if ($this->isUniqueViolation($e)) {
                return response()->json(['status' => 'duplicate', 'event_id' => $eventId], 200);
            }
            throw $e;
        }

        return $next($request);
    }

    private function isUniqueViolation(QueryException $e): bool
    {
        $code = (string) $e->getCode();
        $msg = $e->getMessage();

        return $code === '23000'
            || str_contains($msg, '1062')
            || str_contains($msg, 'UNIQUE constraint failed');
    }
}
