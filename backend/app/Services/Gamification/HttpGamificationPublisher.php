<?php

namespace App\Services\Gamification;

use App\Models\OutboxEvent;
use App\Models\User;
use Illuminate\Http\Client\Factory as HttpFactory;

/**
 * Outbox-then-flush publisher：呼叫 publish() 時先寫 outbox，由 worker job 真的 push 到 py-service。
 *
 * 這個 class 同時提供同步 flush 給測試 / artisan command 用。
 */
final class HttpGamificationPublisher implements GamificationPublisher
{
    public function __construct(
        private readonly HttpFactory $http,
        private readonly string $baseUrl,
        private readonly string $secret,
    ) {}

    public function publish(User $user, string $eventKind, array $context = [], ?string $idempotencyKey = null): void
    {
        if (! in_array($eventKind, CalendarEventCatalog::ALL, true)) {
            throw new \InvalidArgumentException("Unknown gamification event: $eventKind");
        }

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
                'source_app' => config('gamification.app_id') ?? config('pandora.gamification.app_id', 'pandora_calendar'),
                'idempotency_key' => $idempotencyKey,
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
                    'X-Source-App' => config('pandora.gamification.app_id', 'pandora_calendar'),
                ])
                ->timeout(8)
                ->post("{$this->baseUrl}/internal/gamification/events", $event->payload);

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
