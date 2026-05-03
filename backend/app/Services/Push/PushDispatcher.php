<?php

namespace App\Services\Push;

use App\Models\PushSubscription;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * 中央 dispatcher — 依 sub.platform 選 channel；管 metric + 410/404 自動清 sub。
 *
 * 設計重點：
 *  - 缺 credential → 對應 channel.isConfigured()=false → 記 metric 'noop' 不報錯
 *  - 410 / 404 / 「unregistered」reason → 自動 PushSubscription::delete()
 *  - 成功 / 失敗計數寫 cache（admin dashboard 用）
 */
class PushDispatcher
{
    public function __construct(
        private readonly PushChannel $fcm,
        private readonly PushChannel $apns,
        private readonly PushChannel $webPush,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @return array{ok: bool, status: ?int, reason: ?string, channel: string}
     */
    public function dispatch(PushSubscription $sub, string $title, string $body, array $data = []): array
    {
        $channel = $this->channelFor($sub);
        if ($channel === null) {
            return ['ok' => false, 'status' => 400, 'reason' => 'unknown_platform', 'channel' => $sub->platform ?? '?'];
        }

        $platform = $sub->platform ?? 'web';
        $result = $channel->send($sub, $title, $body, $data);
        $result['channel'] = $platform;

        // metric
        if ($result['ok']) {
            $this->incrementMetric((string) config('push.metrics.success_key'));
        } elseif ($result['reason'] !== 'not_configured') {
            // not_configured 不算失敗，那是上線前的 noop；避免污染失敗計數
            $this->incrementMetric((string) config('push.metrics.failure_key'));
        }

        // 自動清失效 sub
        if ($this->shouldPurge($result)) {
            Log::info('[PushDispatcher] purging stale subscription', [
                'sub_id' => $sub->id,
                'platform' => $platform,
                'status' => $result['status'],
                'reason' => $result['reason'],
            ]);
            $sub->delete();
        }

        return $result;
    }

    public function channelFor(PushSubscription $sub): ?PushChannel
    {
        return match ($sub->platform) {
            'ios' => $this->apns,
            'android' => $this->fcm,
            'web', null => $this->webPush,
            default => null,
        };
    }

    /**
     * @param  array{ok: bool, status: ?int, reason: ?string}  $result
     */
    private function shouldPurge(array $result): bool
    {
        if ($result['ok']) {
            return false;
        }
        if (in_array($result['status'], [404, 410], true)) {
            return true;
        }
        $reason = strtolower((string) $result['reason']);
        if ($reason === '' || $reason === 'not_configured') {
            return false;
        }

        return str_contains($reason, 'unregistered')
            || str_contains($reason, 'invalidregistration')
            || str_contains($reason, 'badtoken')
            || str_contains($reason, 'expired');
    }

    private function incrementMetric(string $key): void
    {
        $ttl = (int) config('push.metrics.ttl_seconds', 86400 * 30);
        try {
            // Cache::increment 在某些 driver 對未存在的 key 行為不一致；用 lock-free pattern
            $current = (int) Cache::get($key, 0);
            Cache::put($key, $current + 1, $ttl);
        } catch (\Throwable $e) {
            // metric 失敗不應影響主流程
        }
    }
}
