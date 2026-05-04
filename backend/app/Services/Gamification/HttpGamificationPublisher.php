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
                'pandora_user_uuid' => $user->identity_uuid,
                'source_app' => config('gamification.app_id') ?? config('pandora.gamification.app_id', 'calendar'),
                'event_kind' => $eventKind,
                'idempotency_key' => $idempotencyKey,
                'occurred_at' => now()->toIso8601String(),
                // py-service 期望 metadata 是 dict；空 array 在 JSON 會變 [] 觸發 422，強制 object
                'metadata' => empty($context) ? (object) [] : $context,
            ],
            'occurred_at' => now(),
        ]);
    }

    public function flush(OutboxEvent $event): bool
    {
        try {
            $body = $event->payload;
            // metadata 從 DB JSON load 回來時，空 array 會是 [] 而非 {}，py-service 422
            if (! isset($body['metadata']) || (is_array($body['metadata']) && empty($body['metadata']))) {
                $body['metadata'] = (object) [];
            }
            $res = $this->http
                ->withHeaders([
                    'X-Internal-Secret' => $this->secret,
                    'X-Source-App' => config('pandora.gamification.app_id', 'calendar'),
                ])
                ->timeout(8)
                ->post("{$this->baseUrl}/internal/gamification/events", $body);

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
