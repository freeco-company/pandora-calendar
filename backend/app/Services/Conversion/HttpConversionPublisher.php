<?php

namespace App\Services\Conversion;

use App\Models\OutboxEvent;
use App\Models\User;
use Illuminate\Http\Client\Factory as HttpFactory;

final class HttpConversionPublisher implements ConversionPublisher
{
    public function __construct(
        private readonly HttpFactory $http,
        private readonly string $baseUrl,
        private readonly string $secret,
    ) {}

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

    public function flush(OutboxEvent $event): bool
    {
        try {
            $res = $this->http
                ->withHeaders([
                    'X-Internal-Secret' => $this->secret,
                    'X-Source-App' => config('pandora.conversion.app_id', 'calendar'),
                ])
                ->timeout(8)
                ->post("{$this->baseUrl}/internal/conversion/events", $event->payload);

            if (! $res->successful()) {
                $event->update([
                    'attempts' => $event->attempts + 1,
                    'last_error' => "HTTP {$res->status()}: ".substr($res->body(), 0, 200),
                ]);

                return false;
            }

            $event->update(['published_at' => now()]);

            return true;
        } catch (\Throwable $e) {
            $event->update([
                'attempts' => $event->attempts + 1,
                'last_error' => substr($e->getMessage(), 0, 500),
            ]);

            return false;
        }
    }
}
