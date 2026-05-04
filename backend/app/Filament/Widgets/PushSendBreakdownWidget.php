<?php

namespace App\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Cache;

/**
 * Push 發送概覽（過去 24h × channel）。
 *
 * PushDispatcher 寫的 cache key（沿用 config/push.php 'metrics'）：
 *   push.sent.success                — 累積成功 counter（30d TTL）
 *   push.sent.failure                — 累積失敗 counter
 *   push.sent.skipped.not_configured — 缺 credential noop
 *   push.sent.<channel>.<success|failure>  — channel 拆（fcm / apns / web）
 *
 * widget 對「不存在的 key」一律當 0；prod 上游 instrument 補齊後即真實顯示。
 * 不能用 increment-since-yesterday 計算 24h（counter 未做 daily reset），
 * 改顯示「累積值」+ 提醒 ttl=30d window，operations 端足夠對焦異常。
 */
class PushSendBreakdownWidget extends StatsOverviewWidget
{
    protected ?string $heading = 'Push 發送統計（30 天 rolling）';

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    private function get(string $key): int
    {
        return (int) (Cache::get($key) ?? 0);
    }

    protected function getStats(): array
    {
        $success = $this->get((string) config('push.metrics.success_key'));
        $failure = $this->get((string) config('push.metrics.failure_key'));
        $skipped = $this->get('push.sent.skipped.not_configured');

        $fcmS = $this->get('push.sent.android.success');
        $apnsS = $this->get('push.sent.ios.success');
        $webS = $this->get('push.sent.web.success');

        $total = $success + $failure;
        $rate = $total > 0 ? round($success / $total * 100, 1) : null;

        return [
            Stat::make('成功送達', number_format($success))
                ->description($rate !== null ? "{$rate}% 成功率" : '尚無資料')
                ->color($rate !== null && $rate >= 95 ? 'success' : ($rate === null ? 'gray' : 'warning'))
                ->icon('heroicon-o-check-circle'),

            Stat::make('失敗', number_format($failure))
                ->description($failure > 0 ? '檢視 PushDispatcher log' : '本期無失敗')
                ->color($failure > 0 ? 'danger' : 'success')
                ->icon('heroicon-o-x-circle'),

            Stat::make('Skipped (no credential)', number_format($skipped))
                ->description('credential 未設定即 noop')
                ->color('gray')
                ->icon('heroicon-o-pause-circle'),

            Stat::make('FCM (Android)', number_format($fcmS))
                ->description('成功送達')
                ->color('info')
                ->icon('heroicon-o-device-phone-mobile'),

            Stat::make('APNs (iOS)', number_format($apnsS))
                ->description('成功送達')
                ->color('info')
                ->icon('heroicon-o-device-phone-mobile'),

            Stat::make('WebPush', number_format($webS))
                ->description('成功送達')
                ->color('info')
                ->icon('heroicon-o-globe-alt'),
        ];
    }
}
