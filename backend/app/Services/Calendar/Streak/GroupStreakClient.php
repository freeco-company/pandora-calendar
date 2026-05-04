<?php

namespace App\Services\Calendar\Streak;

use App\Support\Sentry\SentryHelper;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Cache;

/**
 * Phase 5B — calendar 端 cross-App master streak client。
 *
 * py-service `/api/v1/internal/group-streak/{user_uuid}` 回傳整個 Pandora Core uuid 的
 * 集團 streak（任一 App daily_login_streak_extended event 都會 bump 這個 master streak）。
 * Calendar 把它疊在自家 streak toast 之上 — 例如「calendar 連 5 天 + FP 團隊連 12 天」。
 *
 * Fail-soft 是核心設計：py-service down / timeout / 401 / cache miss → 回 null，
 * controller 回 group=null，frontend 只顯示自家 streak。boot 路徑不能因為集團服務掛掉就卡住。
 *
 * Cache 30s（per-process）— 同一 user 連續開 App 不會 hammer py-service；TTL 短到 streak
 * bump 後最多隔 30s 才看到 — 可接受，與 py-service 端 cache 同層級。
 */
final class GroupStreakClient
{
    public function __construct(
        private readonly HttpFactory $http,
    ) {}

    /**
     * Fetch master streak snapshot for a Pandora Core uuid.
     *
     * @return array{
     *   current_streak: int,
     *   longest_streak: int,
     *   last_login_date: ?string,
     *   last_seen_app: ?string,
     *   today_in_streak: bool,
     * }|null  null 表示「不可用」（無 uuid / endpoint 掛 / 沒設 secret 等）
     */
    public function fetch(?string $userUuid): ?array
    {
        if (! is_string($userUuid) || $userUuid === '') {
            return null;
        }

        $baseUrl = (string) (config('gamification.group_streak_url') ?? '');
        $secret = (string) (config('gamification.group_streak_secret') ?? '');
        if ($baseUrl === '' || $secret === '') {
            // 未設 = 視為功能停用（dev / TestFlight build），fail-soft
            return null;
        }

        $ttl = (int) config('gamification.group_streak_cache_ttl', 30);
        $cacheKey = "group_streak:{$userUuid}";

        $cached = Cache::get($cacheKey);
        if (is_array($cached)) {
            return $cached;
        }

        $timeout = (int) config('gamification.group_streak_timeout', 5);
        $url = rtrim($baseUrl, '/').'/internal/group-streak/'.rawurlencode($userUuid);

        try {
            $res = $this->http
                ->withHeaders([
                    'X-Internal-Secret' => $secret,
                    'X-Source-App' => (string) config('gamification.app_id', 'calendar'),
                ])
                ->timeout($timeout)
                ->get($url);

            if (! $res->successful()) {
                SentryHelper::addBreadcrumb('group_streak', 'non-2xx response', [
                    'status' => $res->status(),
                    'uuid' => $userUuid,
                ]);
                return null;
            }

            $body = $res->json();
            if (! is_array($body) || ! array_key_exists('current_streak', $body)) {
                return null;
            }

            $payload = [
                'current_streak' => (int) ($body['current_streak'] ?? 0),
                'longest_streak' => (int) ($body['longest_streak'] ?? 0),
                'last_login_date' => isset($body['last_login_date']) && is_string($body['last_login_date'])
                    ? $body['last_login_date']
                    : null,
                'last_seen_app' => isset($body['last_seen_app']) && is_string($body['last_seen_app'])
                    ? $body['last_seen_app']
                    : null,
                'today_in_streak' => (bool) ($body['today_in_streak'] ?? false),
            ];

            if ($ttl > 0) {
                Cache::put($cacheKey, $payload, $ttl);
            }

            return $payload;
        } catch (\Throwable $e) {
            // fail-soft：streak overlay 不能擋 boot
            SentryHelper::addBreadcrumb('group_streak', 'fetch threw', [
                'message' => $e->getMessage(),
                'uuid' => $userUuid,
            ]);
            return null;
        }
    }
}
