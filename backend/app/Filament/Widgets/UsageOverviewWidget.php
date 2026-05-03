<?php

namespace App\Filament\Widgets;

use App\Models\Feedback;
use App\Models\Subscription;
use App\Models\SubscriptionPauseRequest;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

/**
 * Subscription / churn / DAU rough metrics. Coarse on purpose — exact figures
 * live in py-service / posthog; this widget just gives the operations team a
 * pulse before they dig deeper.
 */
class UsageOverviewWidget extends StatsOverviewWidget
{
    protected ?string $heading = '訂閱 / DAU / Churn（粗略）';

    protected static bool $isLazy = false;

    protected function getStats(): array
    {
        $activeSubs = Subscription::query()
            ->where('status', 'active')
            ->count();

        // Approximate DAU: distinct users who logged something in last 24h.
        // Cycles is the cheapest table that confirms a real human action.
        $dau = DB::table('cycles')
            ->where('updated_at', '>=', now()->subDay())
            ->distinct('user_id')
            ->count('user_id');

        $totalUsers = User::query()->count();

        $top7dFeedback = Feedback::query()
            ->where('created_at', '>=', now()->subDays(7))
            ->selectRaw('category, COUNT(*) as c')
            ->groupBy('category')
            ->orderByDesc('c')
            ->limit(1)
            ->first();

        $churnTop = SubscriptionPauseRequest::query()
            ->where('created_at', '>=', now()->subDays(30))
            ->selectRaw('reason, COUNT(*) as c')
            ->groupBy('reason')
            ->orderByDesc('c')
            ->limit(1)
            ->first();

        return [
            Stat::make('訂閱中用戶', number_format($activeSubs))
                ->description('subscriptions.status = active')
                ->color('success')
                ->icon('heroicon-o-credit-card'),

            Stat::make('過去 24h DAU（粗）', number_format($dau))
                ->description("總用戶數 {$totalUsers}")
                ->color('info')
                ->icon('heroicon-o-users'),

            Stat::make('7d 反饋熱點', $top7dFeedback?->category ?? '—')
                ->description($top7dFeedback ? "共 {$top7dFeedback->c} 筆" : '本週尚無反饋')
                ->color('warning')
                ->icon('heroicon-o-fire'),

            Stat::make('30d Top 取消原因', $churnTop?->reason ?? '—')
                ->description($churnTop ? "共 {$churnTop->c} 筆" : '本月無取消')
                ->color('danger')
                ->icon('heroicon-o-arrow-trending-down'),
        ];
    }
}
