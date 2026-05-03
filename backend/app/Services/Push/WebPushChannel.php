<?php

namespace App\Services\Push;

use App\Models\PushSubscription;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription as WebPushSubscription;
use Minishlink\WebPush\WebPush;
use Throwable;

/**
 * Web Push channel via minishlink/web-push (VAPID, browser-side Push API)。
 *
 * 缺 WEBPUSH_VAPID_* → isConfigured()=false → noop。
 *
 * 注意：minishlink/web-push 是 batched flush 設計；本實作對單一 sub 同步 send 並 flush。
 *      若要批次效能優化，可改用 dispatcher 收集後一次 flush（暫不需要，每用戶 sub 數量少）。
 */
class WebPushChannel implements PushChannel
{
    public function __construct(
        private readonly string $subject = '',
        private readonly string $publicKey = '',
        private readonly string $privateKey = '',
    ) {}

    public function isConfigured(): bool
    {
        return $this->subject !== '' && $this->publicKey !== '' && $this->privateKey !== '';
    }

    public function send(PushSubscription $sub, string $title, string $body, array $data = []): array
    {
        if (! $this->isConfigured()) {
            Log::info('[WebPushChannel] not configured; skipping (noop)');

            return ['ok' => false, 'status' => null, 'reason' => 'not_configured'];
        }

        if (empty($sub->endpoint) || empty($sub->p256dh) || empty($sub->auth)) {
            return ['ok' => false, 'status' => 400, 'reason' => 'incomplete_webpush_keys'];
        }

        try {
            $webPush = new WebPush([
                'VAPID' => [
                    'subject' => $this->subject,
                    'publicKey' => $this->publicKey,
                    'privateKey' => $this->privateKey,
                ],
            ]);

            $payload = json_encode([
                'title' => $title,
                'body' => $body,
                'data' => $data,
            ]);

            $webPush->queueNotification(
                WebPushSubscription::create([
                    'endpoint' => $sub->endpoint,
                    'publicKey' => $sub->p256dh,
                    'authToken' => $sub->auth,
                ]),
                $payload,
            );

            $statusOut = 200;
            $reasonOut = null;
            $okOut = true;
            foreach ($webPush->flush() as $report) {
                if (! $report->isSuccess()) {
                    $okOut = false;
                    $statusOut = (int) ($report->getResponse()?->getStatusCode() ?? 500);
                    $reasonOut = (string) $report->getReason();
                }
            }

            return ['ok' => $okOut, 'status' => $statusOut, 'reason' => $reasonOut];
        } catch (Throwable $e) {
            return ['ok' => false, 'status' => 500, 'reason' => $e->getMessage()];
        }
    }
}
