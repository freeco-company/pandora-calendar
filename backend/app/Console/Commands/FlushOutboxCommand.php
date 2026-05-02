<?php

namespace App\Console\Commands;

use App\Models\OutboxEvent;
use App\Services\Conversion\HttpConversionPublisher;
use App\Services\Gamification\HttpGamificationPublisher;
use Illuminate\Console\Command;

/**
 * 把 outbox 待 publish 的事件 flush 到目的地（gamification / conversion / body_rhythm）。
 *
 * 排程：每分鐘跑一次，每次最多 100 筆。
 * 失敗 5 次 dead letter（attempts >= 5 不再 retry，等人工介入）。
 */
class FlushOutboxCommand extends Command
{
    protected $signature = 'pandora:outbox:flush {--limit=100}';

    protected $description = 'Flush pending outbox events to集團 services';

    public function handle(): int
    {
        $events = OutboxEvent::pending()
            ->orderBy('id')
            ->limit((int) $this->option('limit'))
            ->get();

        $sent = 0;
        $failed = 0;

        foreach ($events as $event) {
            $publisher = match ($event->destination) {
                OutboxEvent::DEST_GAMIFICATION => $this->makeGamificationPublisher(),
                OutboxEvent::DEST_CONVERSION => $this->makeConversionPublisher(),
                OutboxEvent::DEST_BODY_RHYTHM => null, // body_rhythm 走 Pandora Core 不同 endpoint，待 P3 實作
                default => null,
            };

            if (! $publisher) {
                continue;
            }

            $publisher->flush($event)
                ? $sent++
                : $failed++;
        }

        $this->info("flushed: $sent · failed: $failed · processed: ".$events->count());

        return self::SUCCESS;
    }

    private function makeGamificationPublisher(): ?HttpGamificationPublisher
    {
        // P5.1：對齊 AppServiceProvider 的 binding 邏輯（新 config + enabled flag + fallback）。
        $enabled = (bool) config('gamification.enabled', false);
        $url = config('gamification.base_url') ?: config('pandora.gamification.base_url');
        $secret = config('gamification.internal_secret')
            ?: config('gamification.hmac_secret')
            ?: config('pandora.gamification.secret');

        if (! $enabled || ! $url || ! $secret) {
            return null;
        }

        return new HttpGamificationPublisher(app('Illuminate\\Http\\Client\\Factory'), $url, $secret);
    }

    private function makeConversionPublisher(): ?HttpConversionPublisher
    {
        $url = config('pandora.conversion.base_url');
        $secret = config('pandora.conversion.secret');
        if (! $url || ! $secret) {
            return null;
        }

        return new HttpConversionPublisher(app('Illuminate\\Http\\Client\\Factory'), $url, $secret);
    }
}
