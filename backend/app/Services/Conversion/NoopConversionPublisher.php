<?php

namespace App\Services\Conversion;

use App\Models\OutboxEvent;
use App\Models\User;

final class NoopConversionPublisher implements ConversionPublisher
{
    public function publish(User $user, string $eventKind, array $context = []): void
    {
        if (! in_array($eventKind, LifecycleEventCatalog::ALL, true)) {
            throw new \InvalidArgumentException("Unknown conversion event: $eventKind");
        }

        OutboxEvent::create([
            'aggregate_type' => 'user',
            'aggregate_id' => $user->id,
            'event_kind' => $eventKind,
            'destination' => OutboxEvent::DEST_CONVERSION,
            'payload' => [
                'event_kind' => $eventKind,
                'user_uuid' => $user->identity_uuid,
                'source_app' => config('pandora.conversion.app_id', 'calendar'),
                'context' => $context,
            ],
            'occurred_at' => now(),
        ]);
    }
}
