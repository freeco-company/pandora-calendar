<?php

declare(strict_types=1);

namespace App\Support\Sentry;

use Sentry\Breadcrumb;
use Sentry\Severity;
use Sentry\State\Scope;
use Throwable;

/**
 * Pandora Calendar — 統一 Sentry 介面（hot path observability，wave 5）。
 *
 * 為什麼需要這層 wrapper：
 *   1. **DSN 沒設 / SDK 沒裝 → noop**（dev / test / 未配置環境）
 *   2. **module tag 強制**：每個 captureException 必帶 module，方便 Sentry inbox grouping
 *      （iap / oauth / llm / webhook / subscription / dodo）
 *   3. **二次 PII / health 防護**：HealthDataScrubber 已 hook 在 beforeSend / beforeBreadcrumb，
 *      但本層在送入 Sentry 之前再 scrub 一次 context，避免「呼叫端不小心傳 health metadata」漏網
 *
 * 紅線：
 *   - context 任何 key 命中 SENSITIVE_KEYS（cycle / symptom / mood / bbt / pms / pregnancy /
 *     temperature / weight / height / email / phone / address / token / password）→ '[Filtered]'
 *   - URL-like value 命中 health path → '[health-route]'
 *   - exception message 不過濾（Sentry 收到後 HealthDataScrubber 那層仍會擋路徑）
 */
final class SentryHelper
{
    /** @var array<int,string> */
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
        'symptom',
        'symptoms',
        'mood',
        'bbt',
        'temperature',
        'weight',
        'height',
        'pregnancy',
        'pregnancy_status',
        'last_period',
        'period_length',
        'cycle_length',
        'note',
        'notes',
    ];

    /** @var array<int,string> */
    private const HEALTH_PATH_SEGMENTS = [
        '/cycles',
        '/symptoms',
        '/symptom-tags',
        '/bbt',
        '/pms',
        '/pregnancy',
        '/body-rhythm',
        '/bodyrhythm',
        '/dodo/checkin',
        '/insights',
        '/onboarding',
    ];

    /**
     * Capture an exception with module tag + scrubbed context.
     *
     * @param  array<string,mixed>  $context
     */
    public static function captureException(Throwable $e, string $module, array $context = []): void
    {
        if (! self::available()) {
            return;
        }

        $clean = self::scrubArray($context);

        \Sentry\withScope(function (Scope $scope) use ($e, $module, $clean) {
            $scope->setTag('module', $module);
            if ($clean !== []) {
                $scope->setContext('module_data', $clean);
            }
            \Sentry\captureException($e);
        });
    }

    /**
     * @param  array<string,mixed>  $context
     */
    public static function captureMessage(string $message, string $level, string $module, array $context = []): void
    {
        if (! self::available()) {
            return;
        }

        $sev = self::severity($level);
        $clean = self::scrubArray($context);

        \Sentry\withScope(function (Scope $scope) use ($message, $sev, $module, $clean) {
            $scope->setTag('module', $module);
            if ($clean !== []) {
                $scope->setContext('module_data', $clean);
            }
            \Sentry\captureMessage($message, $sev);
        });
    }

    /**
     * Add a breadcrumb (auto-scrubbed). Categories used:
     *   - llm.fail / llm.fallback
     *   - iap.verify
     *   - webhook.identity / webhook.gamification
     *   - oauth.exchange
     *
     * @param  array<string,mixed>  $data
     */
    public static function addBreadcrumb(string $category, string $message, array $data = []): void
    {
        if (! self::available()) {
            return;
        }

        $clean = self::scrubArray($data);

        \Sentry\addBreadcrumb(new Breadcrumb(
            level: Breadcrumb::LEVEL_INFO,
            type: Breadcrumb::TYPE_DEFAULT,
            category: $category,
            message: $message,
            metadata: $clean,
        ));
    }

    private static function available(): bool
    {
        // class_exists check 沿用 wave 4 AppServiceProvider 模式：DSN 未設時 SDK 仍存在
        // 但 captureException 會 silent noop（hub 內 client === null），所以這層只擋
        // 「composer 完全沒裝」的極端 dev 情境。
        return class_exists(\Sentry\SentrySdk::class);
    }

    private static function severity(string $level): Severity
    {
        return match (strtolower($level)) {
            'debug' => Severity::debug(),
            'info' => Severity::info(),
            'warning', 'warn' => Severity::warning(),
            'error' => Severity::error(),
            'fatal', 'critical' => Severity::fatal(),
            default => Severity::info(),
        };
    }

    /**
     * @param  array<string,mixed>  $data
     * @return array<string,mixed>
     */
    private static function scrubArray(array $data): array
    {
        foreach ($data as $key => $value) {
            $lk = strtolower((string) $key);

            // key 命中 → redact
            foreach (self::SENSITIVE_KEYS as $bad) {
                if (str_contains($lk, $bad)) {
                    $data[$key] = '[Filtered]';

                    continue 2;
                }
            }

            if (is_array($value)) {
                $data[$key] = self::scrubArray($value);

                continue;
            }

            // string value 看起來像 URL → 命中 health 就標記
            if (is_string($value) && self::isHealthPath($value)) {
                $data[$key] = '[health-route]';
            }
        }

        return $data;
    }

    private static function isHealthPath(string $value): bool
    {
        $lower = strtolower($value);
        foreach (self::HEALTH_PATH_SEGMENTS as $seg) {
            if (str_contains($lower, $seg)) {
                return true;
            }
        }

        return false;
    }
}
