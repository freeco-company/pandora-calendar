<?php

namespace App\Filament\Widgets;

use App\Models\User;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

/**
 * 用戶健康概覽：DAU / WAU / MAU、留存、新註冊、onboarding 完成率。
 *
 * 「活躍」定義：在窗口內有任一 cycle log 動作（cycles.updated_at），
 * 對齊 UsageOverviewWidget 已有的 DAU 估算。為了簡化（且 cycle 是月曆 App 最 root
 * 的人類動作信號）只看 cycles 表；要更精準的 DAU 應走 posthog。
 *
 * onboarding 完成率 = pet_onboarded_at IS NOT NULL / total users。
 */
class UserHealthWidget extends StatsOverviewWidget
{
    protected ?string $heading = '用戶健康度';

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    private function activeUsersSince(\Carbon\Carbon $since): int
    {
        return DB::table('cycles')
            ->where('updated_at', '>=', $since)
            ->distinct('user_id')
            ->count('user_id');
    }

    protected function getStats(): array
    {
        $now = now();
        $dau = $this->activeUsersSince($now->copy()->subDay());
        $wau = $this->activeUsersSince($now->copy()->subDays(7));
        $mau = $this->activeUsersSince($now->copy()->subDays(30));

        $newSignups7d = User::query()
            ->where('created_at', '>=', $now->copy()->subDays(7))
            ->count();

        $newSignupsActive7d = DB::table('users')
            ->where('users.created_at', '>=', $now->copy()->subDays(7))
            ->whereExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('cycles')
                    ->whereColumn('cycles.user_id', 'users.id')
                    ->where('cycles.updated_at', '>=', now()->subDays(7));
            })
            ->count();

        $signupActivationRate = $newSignups7d > 0
            ? round($newSignupsActive7d / $newSignups7d * 100, 1)
            : null;

        $totalUsers = User::query()->count();
        $onboarded = User::query()->whereNotNull('pet_onboarded_at')->count();
        $onboardRate = $totalUsers > 0 ? round($onboarded / $totalUsers * 100, 1) : 0;

        // Multi-day continuous use proxy：count distinct days a user logged in last 7d.
        // 用 DATE(updated_at) 估「用幾天」；user 用 7 天中 ≥ 7 天 = 連用。
        $continuous7d = DB::table('cycles')
            ->where('updated_at', '>=', $now->copy()->subDays(7))
            ->select('user_id')
            ->groupBy('user_id')
            ->havingRaw('COUNT(DISTINCT DATE(updated_at)) >= 7')
            ->get()
            ->count();

        return [
            Stat::make('DAU', number_format($dau))
                ->description('過去 24h 有 cycle log 動作')
                ->color('success')
                ->icon('heroicon-o-bolt'),

            Stat::make('WAU', number_format($wau))
                ->description('過去 7 天活躍')
                ->color('info')
                ->icon('heroicon-o-users'),

            Stat::make('MAU', number_format($mau))
                ->description('過去 30 天活躍')
                ->color('info')
                ->icon('heroicon-o-globe-alt'),

            Stat::make('新註冊 7d', number_format($newSignups7d))
                ->description($signupActivationRate !== null ? "啟用率 {$signupActivationRate}%" : '本週無新用戶')
                ->color('warning')
                ->icon('heroicon-o-user-plus'),

            Stat::make('連用 7 天', number_format($continuous7d))
                ->description('過去 7d 每天都登錄')
                ->color('success')
                ->icon('heroicon-o-fire'),

            Stat::make('Onboarding 完成率', "{$onboardRate}%")
                ->description("{$onboarded} / {$totalUsers} 用戶已挑寵物")
                ->color($onboardRate >= 70 ? 'success' : 'warning')
                ->icon('heroicon-o-check-badge'),
        ];
    }
}
