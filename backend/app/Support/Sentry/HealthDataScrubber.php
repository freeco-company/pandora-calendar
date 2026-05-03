<?php

declare(strict_types=1);

namespace App\Support\Sentry;

use Sentry\Breadcrumb;
use Sentry\Event;

/**
 * Pandora Calendar — Sentry health-data scrubber.
 *
 * 為什麼存在：月經 / 孕期 / BBT / PMS 是 FTC 等級的敏感資料（Flo 被罰過）。
 * 我們的紅線是：**health-related 任何 request body / response body / 路徑都不能上 Sentry**。
 *
 * 防護層次：
 *   1. URL pattern match → 整個 event 直接 drop（return null）
 *   2. request payload → 強制 strip
 *   3. user context → 只留 anonymized uuid（已是 uuid，不含 PII）
 *   4. breadcrumb → URL 對到 health 路徑就 redact
 *
 * 注意：寧可漏報 error，也不能漏資料。任何疑慮 → drop event。
 */
final class HealthDataScrubber
{
    /**
     * URL path 含這些 segment 一律視為 health 資料路徑 → 整個 event drop。
     */
    private const HEALTH_PATH_SEGMENTS = [
        'cycles',
        'symptoms',
        'symptom-tags',
        'bbt',
        'pms',
        'pregnancy',
        'body-rhythm',
        'bodyrhythm',
        'dodo/checkin',
        'insights',
        'onboarding',
    ];

    /**
     * Request / response / extra 中這些 key 一律 redact。
     */
    private const SENSITIVE_KEYS = [
        'email',
        'phone',
        'address',
        'password',
        'password_hash',
        'token',
        'access_token',
        'refresh_token',
        'authorization',
        'cookie',
        'cycle_start',
        'cycle_end',
        'flow',
        'symptoms',
        'mood',
        'bbt',
        'temperature',
        'weight',
        'height',
        'pregnancy_status',
        'last_period',
        'period_length',
        'cycle_length',
    ];

    public static function scrub(Event $event): ?Event
    {
        $request = $event->getRequest();
        if (is_array($request) && isset($request['url']) && self::isHealthPath((string) $request['url'])) {
            // health 路徑直接 drop，不送
            return null;
        }

        // request body / query / cookies / headers 全 strip
        if (is_array($request) && $request !== []) {
            unset($request['data'], $request['cookies'], $request['env']);
            if (isset($request['headers']) && is_array($request['headers'])) {
                $request['headers'] = self::redactArray($request['headers']);
            }
            if (isset($request['query_string']) && is_string($request['query_string'])) {
                $request['query_string'] = '[Filtered]';
            }
            $event->setRequest($request);
        }

        // user context → 只留 uuid id，其他抹掉
        $user = $event->getUser();
        if ($user !== null) {
            $userArr = $user->toArray();
            $allowed = ['id' => $userArr['id'] ?? null];
            $event->setUser(\Sentry\UserDataBag::createFromArray(array_filter($allowed, fn ($v) => $v !== null)));
        }

        // extra / context redact
        $extra = $event->getExtra();
        if (! empty($extra)) {
            $event->setExtra(self::redactArray($extra));
        }

        // 註：Sentry\Event 沒有 setContexts() bulk setter；contexts 主要是 runtime / os
        // 等系統 metadata（不含 user data），先不動。若未來需要 redact 個別 context
        // 走 setRuntimeContext / setOsContext 等 typed setter。

        return $event;
    }

    public static function scrubTransaction(Event $event): ?Event
    {
        // transaction 名稱本身可能含 health route
        $name = $event->getTransaction();
        if ($name !== null && self::isHealthPath($name)) {
            return null;
        }

        return self::scrub($event);
    }

    public static function scrubBreadcrumb(Breadcrumb $breadcrumb): ?Breadcrumb
    {
        $data = $breadcrumb->getMetadata();

        // breadcrumb URL 命中 health → drop
        if (isset($data['url']) && is_string($data['url']) && self::isHealthPath($data['url'])) {
            return null;
        }

        // SQL query breadcrumb 含 cycles / symptoms 等表名 → drop
        if ($breadcrumb->getCategory() === 'sql.query' && isset($data['sql']) && is_string($data['sql'])) {
            $sql = strtolower($data['sql']);
            if (str_contains($sql, 'cycles') || str_contains($sql, 'symptoms') || str_contains($sql, 'bbt') || str_contains($sql, 'dodo_checkins')) {
                return null;
            }
        }

        // 其他 breadcrumb 的 data 做欄位級 redact（withMetadata 一次只能設一個 key）
        if (! empty($data)) {
            $clean = self::redactArray($data);
            foreach ($clean as $k => $v) {
                $breadcrumb = $breadcrumb->withMetadata((string) $k, $v);
            }
        }

        return $breadcrumb;
    }

    private static function isHealthPath(string $url): bool
    {
        $lower = strtolower($url);
        foreach (self::HEALTH_PATH_SEGMENTS as $seg) {
            if (str_contains($lower, $seg)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private static function redactArray(array $data): array
    {
        foreach ($data as $key => $value) {
            $lk = strtolower((string) $key);
            foreach (self::SENSITIVE_KEYS as $bad) {
                if (str_contains($lk, $bad)) {
                    $data[$key] = '[Filtered]';

                    continue 2;
                }
            }
            if (is_array($value)) {
                $data[$key] = self::redactArray($value);
            }
        }

        return $data;
    }
}
