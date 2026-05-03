<?php

namespace App\Http\Middleware;

use App\Models\IdentityWebhookNonce;
use App\Support\Sentry\SentryHelper;
use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * P1 ADR-007 — calendar 端 PC webhook 簽章驗證。Mirror of mother / meal middleware。
 *
 * 三道：
 *   1. timestamp ±5min
 *   2. HMAC-SHA256 over `{timestamp}.{event_id}.{body}`
 *   3. event_id UNIQUE INSERT 防 replay
 *
 * Replay → 200 (noop, 告訴 publisher 已處理可標 sent)；其餘失敗 → 401。
 */
class VerifyIdentityWebhookSignature
{
    public function handle(Request $request, Closure $next): Response
    {
        $secret = (string) config('services.pandora_core.webhook_secret');
        if ($secret === '') {
            Log::error('[IdentityWebhook] missing PANDORA_CORE_WEBHOOK_SECRET');

            return response()->json(['error' => 'webhook secret not configured'], 500);
        }

        $eventId = (string) $request->header('X-Pandora-Event-Id', '');
        $timestamp = (string) $request->header('X-Pandora-Timestamp', '');
        $signature = (string) $request->header('X-Pandora-Signature', '');

        if ($eventId === '' || $timestamp === '' || $signature === '') {
            SentryHelper::captureMessage(
                'identity webhook missing signature headers',
                'warning',
                'webhook.identity',
                ['stage' => 'header_check']
            );

            return response()->json(['error' => 'missing signature headers'], 401);
        }

        $window = (int) config('services.pandora_core.webhook_window_seconds', 300);
        if (abs(time() - (int) $timestamp) > $window) {
            SentryHelper::captureMessage(
                'identity webhook timestamp out of window',
                'warning',
                'webhook.identity',
                ['stage' => 'timestamp', 'event_id' => $eventId]
            );

            return response()->json(['error' => 'timestamp out of window'], 401);
        }

        $body = $request->getContent();
        $expected = hash_hmac('sha256', "{$timestamp}.{$eventId}.{$body}", $secret);
        if (! hash_equals($expected, $signature)) {
            SentryHelper::captureMessage(
                'identity webhook HMAC mismatch',
                'warning',
                'webhook.identity',
                ['stage' => 'hmac', 'event_id' => $eventId]
                // 不 attach body — 可能含 PC payload
            );

            return response()->json(['error' => 'signature mismatch'], 401);
        }

        try {
            IdentityWebhookNonce::create([
                'event_id' => $eventId,
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
