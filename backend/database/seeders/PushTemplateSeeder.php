<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;

/**
 * PushTemplateSeeder — 內部測試用：把 config/push-templates.php 的所有分支 + 變體
 *   pre-warm 到 cache，並印一份 welcome schedule 一覽，方便內測時人工觸發。
 *
 * 使用：
 *   php artisan db:seed --class=PushTemplateSeeder
 *
 * 注意：本 seeder 不寫 DB（push 文案目前 source-of-truth 在 config 檔），
 *   它的目的是：
 *   1. 列出當前所有 push 分支與變體數，方便 PM / QA 對稿
 *   2. 把「welcome push payload」放進 cache，PushSendDailyReminders 在 dry-run
 *      時可以讀出來模擬「新用戶第一次看到的內容」
 */
class PushTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $branches = config('push-templates.branches', []);
        $totalVariants = 0;

        $this->command?->info('Push template summary:');
        foreach ($branches as $key => $branch) {
            $variantCount = count($branch['variants'] ?? []);
            $inclusiveCount = count($branch['variants_inclusive'] ?? []);
            $totalVariants += $variantCount + $inclusiveCount;
            $enabled = ($branch['enabled'] ?? false) ? 'on ' : 'off';
            $this->command?->line(sprintf(
                '  [%s] %-28s send=%s cooldown=%dh  variants=%d (+%d inclusive)',
                $enabled,
                $key,
                $branch['send_time'] ?? '--:--',
                $branch['cooldown_hours'] ?? 0,
                $variantCount,
                $inclusiveCount,
            ));
        }
        $this->command?->info(sprintf('Total branches=%d variants=%d', count($branches), $totalVariants));

        // Welcome push（新註冊用戶 24h 內第一次 push 的 payload）
        $welcome = [
            'title' => '朵朵在這 💛',
            'body' => '朵朵會幫妳記著週期。第一次點一下心情，朵朵就會回妳。',
        ];
        Cache::put('pandora_calendar:welcome_push', $welcome, now()->addDays(30));
        $this->command?->info('Cached welcome push payload at key=pandora_calendar:welcome_push (30d)');
    }
}
