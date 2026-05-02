<?php

namespace App\Services\Gamification;

/**
 * P5.2：calendar gamification event idempotency key 統一格式器。
 *
 * 規格：`{event}.{user_id}.{aggregate_id}.{date}`
 *  - event：含 `calendar.` prefix
 *  - user_id：本機 user.id（DB pk，stable）
 *  - aggregate_id：對應 cycle id / symptom id / 0（沒有 aggregate 時用 0）
 *  - date：YYYY-MM-DD（同 user 同 aggregate 同事件同天只發一次）
 *
 * Daily-cap 類事件（app_opened）用 date 自動每天重置。
 * Lifetime-unique（first_cycle / track_7_days / full_cycle_tracked）用固定 marker。
 */
final class IdempotencyKey
{
    public static function make(string $eventKind, int $userId, int|string $aggregateId, string $date): string
    {
        return sprintf('%s.%d.%s.%s', $eventKind, $userId, (string) $aggregateId, $date);
    }

    /**
     * 一輩子只送一次的 milestone（first_cycle, track_7_days, full_cycle_tracked）
     */
    public static function lifetime(string $eventKind, int $userId): string
    {
        return sprintf('%s.%d.lifetime', $eventKind, $userId);
    }
}
