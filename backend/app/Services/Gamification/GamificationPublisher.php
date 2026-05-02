<?php

namespace App\Services\Gamification;

use App\Models\User;

interface GamificationPublisher
{
    /**
     * Publish a gamification event to py-service catalog (ADR-009).
     *
     * @param  string  $eventKind  e.g. calendar.first_cycle
     * @param  array  $context  freeform extra fields (cycle_id, streak_count, etc.)
     * @param  string|null  $idempotencyKey  format: calendar.{event}.{user_id}.{aggregate_id}.{date}
     *                                       同 key 重發 → 寫第一筆、其他 silent skip。
     */
    public function publish(User $user, string $eventKind, array $context = [], ?string $idempotencyKey = null): void;
}
