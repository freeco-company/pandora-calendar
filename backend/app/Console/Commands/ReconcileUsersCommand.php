<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * P1 ADR-007 §6 — periodic delta pull from Pandora Core 確保本地 mirror
 * 與 PC 維持一致（webhook 漏接的 safety net）。
 *
 * 流程：
 *   1. 從 cache 讀 last_cursor（首次 epoch）
 *   2. GET PC /api/internal/reconcile/users?since={cursor}&limit=100
 *   3. 對每筆 upsert User by identity_uuid，寫 display_name / last_synced_at
 *   4. has_more=false 時把 next_cursor 回寫到 cache
 *
 * 排程在 routes/console.php 每 10 分鐘跑一次。
 *
 * Cursor key: identity:reconcile:cursor
 */
class ReconcileUsersCommand extends Command
{
    protected $signature = 'identity:reconcile-users
        {--reset : 重設 cursor 到 epoch（強制 full pull）}
        {--limit=100 : 每頁筆數 (max 500)}';

    protected $description = '從 Pandora Core 拉 users delta 同步本地 mirror';

    private const CURSOR_KEY = 'identity:reconcile:cursor';

    private const PAGE_HARD_LIMIT = 50;  // 一次 run 最多走幾頁，避免 runaway

    public function handle(): int
    {
        $base = (string) config('services.pandora_core.base_url', '');
        $secret = (string) config('services.pandora_core.internal_secret', '');
        if ($base === '' || $secret === '') {
            $this->error('PANDORA_CORE_BASE_URL 或 PANDORA_CORE_INTERNAL_SECRET 未設定');

            return self::FAILURE;
        }

        if ($this->option('reset')) {
            Cache::forget(self::CURSOR_KEY);
            $this->info('cursor reset');
        }

        $cursor = (string) Cache::get(self::CURSOR_KEY, '1970-01-01T00:00:00Z');
        $limit = max(1, min(500, (int) $this->option('limit')));

        $totalUpserted = 0;
        $pages = 0;

        while ($pages < self::PAGE_HARD_LIMIT) {
            $pages++;

            try {
                $resp = Http::timeout(15)
                    ->withHeaders([
                        'X-Internal-Secret' => $secret,
                        'X-Source-App' => 'pandora-calendar',
                        'Accept' => 'application/json',
                    ])
                    ->get(rtrim($base, '/').'/api/internal/reconcile/users', [
                        'since' => $cursor,
                        'limit' => $limit,
                    ]);
            } catch (\Throwable $e) {
                $this->error('reconcile fetch failed: '.$e->getMessage());
                Log::warning('[Reconcile] fetch error', ['error' => $e->getMessage()]);

                return self::FAILURE;
            }

            if (! $resp->successful()) {
                $this->error('reconcile HTTP '.$resp->status().': '.substr($resp->body(), 0, 200));

                return self::FAILURE;
            }

            $users = (array) ($resp->json('users') ?? []);
            foreach ($users as $u) {
                $uuid = (string) ($u['id'] ?? '');
                if ($uuid === '') {
                    continue;
                }
                $this->upsertMirror($uuid, $u);
                $totalUpserted++;
            }

            $hasMore = (bool) ($resp->json('has_more') ?? false);
            $nextCursor = $resp->json('next_cursor');

            if (! $hasMore || ! is_string($nextCursor) || $nextCursor === '') {
                // 沒下一頁：把 cursor 推到目前批次最後一筆 updated_at（或 now）
                $lastUpdated = end($users)['updated_at'] ?? null;
                if (is_string($lastUpdated) && $lastUpdated !== '') {
                    Cache::forever(self::CURSOR_KEY, $lastUpdated);
                }
                break;
            }

            $cursor = $nextCursor;
            Cache::forever(self::CURSOR_KEY, $cursor);
        }

        $this->info("reconciled {$totalUpserted} users in {$pages} page(s)");
        Log::info('[Reconcile] done', ['upserted' => $totalUpserted, 'pages' => $pages]);

        return self::SUCCESS;
    }

    /**
     * @param  array<string,mixed>  $payload
     */
    private function upsertMirror(string $uuid, array $payload): void
    {
        $updated = Carbon::parse((string) ($payload['updated_at'] ?? now()))->toDateTimeString();

        User::query()->updateOrCreate(
            ['identity_uuid' => $uuid],
            [
                'display_name' => $payload['display_name'] ?? null,
                'subscription_tier' => $payload['subscription_tier'] ?? null,
                'identity_synced_at' => $updated,
                'last_synced_at' => now(),
            ],
        );
    }
}
