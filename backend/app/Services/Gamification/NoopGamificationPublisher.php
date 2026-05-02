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
    public function publish(User $user, string $eventKind, array $context = []): void
    {
        if (! in_array($eventKind, CalendarEventCatalog::ALL, true)) {
            throw new \InvalidArgumentException("Unknown gamification event: $eventKind");
        }

        OutboxEvent::create([
            'aggregate_type' => 'user',
            'aggregate_id' => $user->id,
            'event_kind' => $eventKind,
            'destination' => OutboxEvent::DEST_GAMIFICATION,
            'payload' => [
                'event_kind' => $eventKind,
                'user_uuid' => $user->identity_uuid,
                'source_app' => config('pandora.gamification.app_id', 'pandora_calendar'),
                'context' => $context,
            ],
            'occurred_at' => now(),
        ]);
    }
}
