<?php

namespace App\Services\Gamification;

use App\Models\OutboxEvent;
use App\Models\User;

/**
 * Phase 0：py-service 還沒接通；改寫 outbox 但不真 push（attempts=0、published_at=null）。
 *
 * 上 P3 時把 NoopGamificationPublisher 換成 HttpGamificationPublisher，背景 worker
 * 自動把 outbox queue 全部 catch up。
 */
final class NoopGamificationPublisher implements GamificationPublisher
{
    public function publish(User $user, string $eventKind, array $context = [], ?string $idempotencyKey = null): void
    {
        if (! in_array($eventKind, CalendarEventCatalog::ALL, true)) {
            throw new \InvalidArgumentException("Unknown gamification event: $eventKind");
        }

        // 給定 idempotency key 已存在 → silent skip（雙寫保護）
        if ($idempotencyKey && OutboxEvent::where('idempotency_key', $idempotencyKey)->exists()) {
            return;
        }

        OutboxEvent::create([
            'aggregate_type' => 'user',
            'aggregate_id' => $user->id,
            'event_kind' => $eventKind,
            'idempotency_key' => $idempotencyKey,
            'destination' => OutboxEvent::DEST_GAMIFICATION,
            'payload' => [
                'event_kind' => $eventKind,
                'user_uuid' => $user->identity_uuid,
                'source_app' => config('gamification.app_id') ?? config('pandora.gamification.app_id', 'calendar'),
                'idempotency_key' => $idempotencyKey,
                'context' => $context,
            ],
            'occurred_at' => now(),
        ]);
    }
}
