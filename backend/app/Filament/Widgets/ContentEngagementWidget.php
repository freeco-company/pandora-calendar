<?php

namespace App\Filament\Widgets;

use App\Models\ActionFeedback;
use App\Models\CommunityPost;
use App\Models\CommunityReply;
use App\Models\CyclePatternReport;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

/**
 * 內容互動概覽（過去 7 天）。
 *
 * 「文章開啟次數」走 cache key（上游 InsightController 應記）：
 *   daily_insight.view.<insight_id>.count
 * 若上游尚未 instrument，這欄顯示「—」不擋。
 *
 * 「Top action」直接 query action_feedback (helpful) — 該 schema 在 wave 11。
 * Pattern report 從 cycle_pattern_reports count。
 * 社群活動量 community_posts + community_replies。
 */
class ContentEngagementWidget extends StatsOverviewWidget
{
    protected ?string $heading = '內容互動（7d）';

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    protected function getStats(): array
    {
        $since = now()->subDays(7);

        $newPosts = CommunityPost::query()
            ->where('created_at', '>=', $since)
            ->count();

        $newReplies = CommunityReply::query()
            ->where('created_at', '>=', $since)
            ->count();

        // top action by helpful feedback in past 7d
        $topAction = ActionFeedback::query()
            ->join('daily_action_recommendations as r', 'action_feedback.recommendation_id', '=', 'r.id')
            ->where('action_feedback.submitted_at', '>=', $since)
            ->where('action_feedback.feedback', 'helpful')
            ->selectRaw('r.action_key as action_key, COUNT(*) as c')
            ->groupBy('r.action_key')
            ->orderByDesc('c')
            ->limit(1)
            ->first();

        $patternReports7d = CyclePatternReport::query()
            ->where('created_at', '>=', $since)
            ->count();

        // Top viewed daily insight — 從 cache aggregated key 讀
        // 上游若沒寫，fallback 顯示 —
        $topInsightTitle = '—';
        $topInsightCount = 0;
        try {
            $insights = DB::table('daily_insights')->select('id', 'title')->get();
            $best = null;
            foreach ($insights as $ins) {
                $c = (int) (\Illuminate\Support\Facades\Cache::get("daily_insight.view.{$ins->id}.count") ?? 0);
                if ($best === null || $c > $best['c']) {
                    $best = ['title' => $ins->title, 'c' => $c];
                }
            }
            if ($best !== null && $best['c'] > 0) {
                $topInsightTitle = mb_strimwidth($best['title'], 0, 24, '…');
                $topInsightCount = $best['c'];
            }
        } catch (\Throwable $e) {
            // daily_insights 表沒建好 / cache 異常 — fallback
        }

        return [
            Stat::make('社群新貼文', number_format($newPosts))
                ->description('過去 7 天')
                ->color('info')
                ->icon('heroicon-o-pencil-square'),

            Stat::make('社群新回覆', number_format($newReplies))
                ->description('過去 7 天')
                ->color('info')
                ->icon('heroicon-o-chat-bubble-left'),

            Stat::make('Top helpful action', $topAction?->action_key ?? '—')
                ->description($topAction ? "獲讚 {$topAction->c} 次" : '本週尚無回饋')
                ->color('success')
                ->icon('heroicon-o-hand-thumb-up'),

            Stat::make('Pattern reports', number_format($patternReports7d))
                ->description('過去 7 天生成')
                ->color('warning')
                ->icon('heroicon-o-document-chart-bar'),

            Stat::make('Top 衛教文章', $topInsightTitle)
                ->description($topInsightCount > 0 ? "{$topInsightCount} 次開啟" : 'view 計數待 instrument')
                ->color('gray')
                ->icon('heroicon-o-book-open'),
        ];
    }
}
