<?php

namespace App\Filament\Widgets;

use App\Models\CommunityPost;
use App\Models\CommunityReport;
use App\Models\Feedback;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Top-of-dashboard at-a-glance numbers for moderators.
 *
 * isLazy=false so Pest tests can assert the values without a Livewire
 * hydration round-trip (mirror pandora-meal FunnelOverviewWidget pattern).
 */
class ModerationQueueWidget extends StatsOverviewWidget
{
    protected ?string $heading = '今日營運摘要';

    protected static bool $isLazy = false;

    protected function getStats(): array
    {
        $pendingReports = CommunityReport::query()->whereNull('resolved_at')->count();
        $newPosts24h = CommunityPost::query()->where('created_at', '>=', now()->subDay())->count();
        $unprocessedFeedback = Feedback::query()->whereNull('processed_at')->count();

        return [
            Stat::make('待審檢舉', (string) $pendingReports)
                ->description('community_reports 未 resolved')
                ->color($pendingReports > 0 ? 'danger' : 'success')
                ->icon('heroicon-o-flag'),

            Stat::make('過去 24h 新貼文', (string) $newPosts24h)
                ->description('含 hidden / removed')
                ->color('info')
                ->icon('heroicon-o-chat-bubble-left-right'),

            Stat::make('未處理反饋', (string) $unprocessedFeedback)
                ->description('processed_at IS NULL')
                ->color($unprocessedFeedback > 10 ? 'warning' : 'gray')
                ->icon('heroicon-o-megaphone'),
        ];
    }
}
