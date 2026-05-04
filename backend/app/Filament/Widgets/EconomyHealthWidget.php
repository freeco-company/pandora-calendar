<?php

namespace App\Filament\Widgets;

use App\Models\DodoCoinTransaction;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

/**
 * 朵朵幣經濟健康度（過去 7 天）。
 *
 * 直接 query dodo_coin_transactions：
 *   - 已 indexed 在 (user_id, created_at) + source；7d 範圍 query 不需 cache
 *   - delta > 0 = earn / delta < 0 = spend
 *   - balance_after 是 snapshot，平均持有 balance 改取「每用戶最新 balance_after 平均」
 *
 * 紅線：朵朵幣只能賺不能買。若看到極端 earn 偏離全域中位數 → 可能 abuse / bug。
 */
class EconomyHealthWidget extends StatsOverviewWidget
{
    protected ?string $heading = '朵朵幣經濟健康度（7d）';

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    protected function getStats(): array
    {
        $since = now()->subDays(7);

        $earn = (int) DodoCoinTransaction::query()
            ->where('created_at', '>=', $since)
            ->where('delta', '>', 0)
            ->sum('delta');

        $spend = (int) abs(DodoCoinTransaction::query()
            ->where('created_at', '>=', $since)
            ->where('delta', '<', 0)
            ->sum('delta'));

        $topEarn = DodoCoinTransaction::query()
            ->where('created_at', '>=', $since)
            ->where('delta', '>', 0)
            ->selectRaw('source, SUM(delta) as total')
            ->groupBy('source')
            ->orderByDesc('total')
            ->limit(1)
            ->first();

        $topSpend = DodoCoinTransaction::query()
            ->where('created_at', '>=', $since)
            ->where('delta', '<', 0)
            ->selectRaw('source, SUM(ABS(delta)) as total')
            ->groupBy('source')
            ->orderByDesc('total')
            ->limit(1)
            ->first();

        // Average balance held = mean of each user's latest balance_after
        // (subquery: latest tx per user, then AVG)。資料量小時直接 query 即可。
        $avgBalance = DB::table('dodo_coin_transactions as t1')
            ->whereRaw('t1.id = (SELECT MAX(id) FROM dodo_coin_transactions t2 WHERE t2.user_id = t1.user_id)')
            ->avg('balance_after');

        return [
            Stat::make('7d 總賺', number_format($earn))
                ->description('所有 earn source 加總')
                ->color('success')
                ->icon('heroicon-o-arrow-trending-up'),

            Stat::make('7d 總花', number_format($spend))
                ->description('所有 spend source 加總')
                ->color('warning')
                ->icon('heroicon-o-arrow-trending-down'),

            Stat::make('Top earn source', $topEarn?->source ?? '—')
                ->description($topEarn ? "+{$topEarn->total}" : '本週尚無 earn')
                ->color('info')
                ->icon('heroicon-o-star'),

            Stat::make('Top spend source', $topSpend?->source ?? '—')
                ->description($topSpend ? "-{$topSpend->total}" : '本週尚無 spend')
                ->color('info')
                ->icon('heroicon-o-shopping-cart'),

            Stat::make('平均持有 balance', $avgBalance !== null ? number_format((float) $avgBalance, 0) : '—')
                ->description('每用戶最新 balance_after 平均')
                ->color('gray')
                ->icon('heroicon-o-scale'),
        ];
    }
}
