<?php

namespace App\Services\Reports;

use App\Models\ActionFeedback;
use App\Models\Cycle;
use App\Models\CyclePatternReport;
use App\Models\CycleSymptom;
use App\Models\DailyActionRecommendation;
use App\Models\DodoCheckin;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Pandora\Shared\Compliance\LegalContentSanitizer;

/**
 * 為單一 cycle 生成 pattern report（top actions / symptoms / vs_previous + 朵朵口吻 message）。
 *
 * Idempotent：同 user_id + cycle_id 已存在 → 直接 return 既存 row（不覆寫）。
 *
 * 文案組合：從 config('dodo-action-replies') 抽 4-6 句模板，依資料豐富度決定走 strong /
 * weak signal 開頭，最後過 sanitizer。
 */
class PatternReportGenerator
{
    public function __construct(
        private readonly LegalContentSanitizer $sanitizer,
    ) {}

    public function generateForCycle(int $userId, int $cycleId): CyclePatternReport
    {
        $existing = CyclePatternReport::where('user_id', $userId)
            ->where('cycle_id', $cycleId)
            ->first();
        if ($existing) {
            return $existing;
        }

        $cycle = Cycle::where('user_id', $userId)->findOrFail($cycleId);
        $start = CarbonImmutable::parse($cycle->start_date);

        // cycle 結束日：下一個 cycle 的 start - 1，沒有就用 now
        $nextCycle = Cycle::where('user_id', $userId)
            ->where('start_date', '>', $cycle->start_date)
            ->orderBy('start_date')
            ->first();
        $end = $nextCycle
            ? CarbonImmutable::parse($nextCycle->start_date)->subDay()
            : CarbonImmutable::today();

        $cycleDayCount = $start->diffInDays($end) + 1;

        // 拉資料
        $recsInCycle = DailyActionRecommendation::where('user_id', $userId)
            ->whereBetween('recommended_on', [$start->toDateString(), $end->toDateString()])
            ->get();

        $feedbacks = $recsInCycle->isEmpty()
            ? collect()
            : ActionFeedback::whereIn('recommendation_id', $recsInCycle->pluck('id'))->get();

        $symptoms = CycleSymptom::where('user_id', $userId)
            ->whereBetween('logged_on', [$start->toDateString(), $end->toDateString()])
            ->get();

        $checkins = DodoCheckin::where('user_id', $userId)
            ->whereBetween('checked_on', [$start->toDateString(), $end->toDateString()])
            ->get();

        // top 3 helpful / unhelpful
        $topActions = $this->topActions($recsInCycle, $feedbacks);

        // phase summary
        $phaseSummary = $this->phaseSummary($recsInCycle, $symptoms, $checkins, $cycleDayCount);

        // vs previous
        $vsPrevious = $this->vsPrevious($userId, $cycle, $symptoms);

        // 朵朵 message
        $cards = (array) config('daily-actions', []);
        $message = $this->buildMessage(
            cycleDayCount: $cycleDayCount,
            topActions: $topActions,
            vsPrevious: $vsPrevious,
            cards: $cards,
        );

        return CyclePatternReport::create([
            'user_id' => $userId,
            'cycle_id' => $cycleId,
            'phase_summary' => $phaseSummary,
            'top_actions' => $topActions,
            'vs_previous' => $vsPrevious,
            'dodo_message' => $message,
            'generated_at' => CarbonImmutable::now(),
        ]);
    }

    /**
     * @return array{top_helpful: array<int, array{action_key: string, score: float, sample: int}>, top_unhelpful: array<int, array{action_key: string, score: float, sample: int}>}
     */
    private function topActions(Collection $recs, Collection $feedbacks): array
    {
        if ($feedbacks->isEmpty()) {
            return ['top_helpful' => [], 'top_unhelpful' => []];
        }

        $byKey = [];
        $recById = $recs->keyBy('id');
        foreach ($feedbacks as $fb) {
            $rec = $recById->get($fb->recommendation_id);
            if (! $rec) {
                continue;
            }
            $key = $rec->action_key;
            $byKey[$key] ??= ['sum' => 0.0, 'count' => 0];
            $byKey[$key]['sum'] += match ($fb->feedback) {
                'helpful' => 1.0,
                'neutral' => 0.5,
                default => 0.0,
            };
            $byKey[$key]['count']++;
        }

        $scored = [];
        foreach ($byKey as $key => $agg) {
            if ($agg['count'] === 0) {
                continue;
            }
            $scored[] = [
                'action_key' => $key,
                'score' => round($agg['sum'] / $agg['count'], 3),
                'sample' => $agg['count'],
            ];
        }

        // top 3 helpful（score desc）
        $helpful = $scored;
        usort($helpful, fn ($a, $b) => $b['score'] <=> $a['score']);
        $topHelpful = array_slice(
            array_filter($helpful, fn ($r) => $r['score'] >= 0.5),
            0, 3,
        );

        // top 3 unhelpful（score asc，且 < 0.5）
        $unhelpful = $scored;
        usort($unhelpful, fn ($a, $b) => $a['score'] <=> $b['score']);
        $topUnhelpful = array_slice(
            array_filter($unhelpful, fn ($r) => $r['score'] < 0.5),
            0, 3,
        );

        return [
            'top_helpful' => array_values($topHelpful),
            'top_unhelpful' => array_values($topUnhelpful),
        ];
    }

    private function phaseSummary(Collection $recs, Collection $symptoms, Collection $checkins, int $totalDays): array
    {
        // 各 phase 在此 cycle 累積行動數
        $byPhase = $recs->groupBy('phase')->map->count();

        // top symptoms（從 tags array flatten）
        $tagCount = [];
        foreach ($symptoms as $s) {
            foreach (((array) $s->tags) as $tag) {
                if (! is_string($tag) || $tag === '') {
                    continue;
                }
                $tagCount[$tag] = ($tagCount[$tag] ?? 0) + 1;
            }
        }
        arsort($tagCount);
        $topSymptoms = array_slice($tagCount, 0, 3, true);

        return [
            'total_days' => $totalDays,
            'recommendations_by_phase' => $byPhase->toArray(),
            'top_symptoms' => array_map(
                fn ($tag, $count) => ['tag' => $tag, 'count' => $count],
                array_keys($topSymptoms),
                array_values($topSymptoms),
            ),
            'symptom_log_count' => $symptoms->count(),
            'dodo_checkin_count' => $checkins->count(),
        ];
    }

    private function vsPrevious(int $userId, Cycle $cycle, Collection $currentSymptoms): ?array
    {
        $prev = Cycle::where('user_id', $userId)
            ->whereKeyNot($cycle->id)
            ->where('start_date', '<', $cycle->start_date->toDateString())
            ->orderByDesc('start_date')
            ->first();
        if (! $prev) {
            return null;
        }

        $prevStart = CarbonImmutable::parse($prev->start_date);
        $prevEnd = CarbonImmutable::parse($cycle->start_date)->subDay();

        $prevSymptomCount = CycleSymptom::where('user_id', $userId)
            ->whereBetween('logged_on', [$prevStart->toDateString(), $prevEnd->toDateString()])
            ->count();

        $currCount = $currentSymptoms->count();

        $diff = $currCount - $prevSymptomCount;
        $trend = match (true) {
            $diff < -1 => 'better',
            $diff > 1 => 'worse',
            default => 'same',
        };

        return [
            'previous_cycle_id' => $prev->id,
            'symptom_count_change' => $diff,
            'symptom_trend' => $trend,
            'previous_symptom_count' => $prevSymptomCount,
            'current_symptom_count' => $currCount,
        ];
    }

    /**
     * 組合 4-6 句朵朵 cycle 回顧 message。
     *
     * v2 dodo-action-replies schema 是 type-keyed（feedback_helpful.sleep / move ...）
     * + streak_N + first_completion + pattern_emerging。
     * cycle 回顧文案以 hard-coded 開頭 / 結尾 + pattern_emerging（top action）+ feedback_unhelpful（worst action）
     * 的 type bucket 拼成。
     */
    private function buildMessage(int $cycleDayCount, array $topActions, ?array $vsPrevious, array $cards): string
    {
        $lines = [];

        // 開頭（hard-coded — v2 replies 沒給 cycle-level intro，現場寫穩定 1 句）
        $sampleSize = collect($topActions['top_helpful'] ?? [])->sum('sample')
            + collect($topActions['top_unhelpful'] ?? [])->sum('sample');
        $lines[] = $sampleSize >= 3
            ? "這個週期妳記了 {$cycleDayCount} 天，朵朵看到一些妳的樣子了。"
            : '這個週期資料還不多，朵朵先輕輕說我看到的。';

        // top helpful → 用 pattern_emerging 模板（已含 {{pattern_action}}）
        $topHelpful = $topActions['top_helpful'][0] ?? null;
        if ($topHelpful) {
            $title = (string) ($cards[$topHelpful['action_key']]['title'] ?? $topHelpful['action_key']);
            $lines[] = $this->pick('pattern_emerging', ['pattern_action' => $title]);
        }

        // worst → 借 feedback_unhelpful 同 type bucket（朵朵語氣一致），找不到就現場寫
        $topUnhelpful = $topActions['top_unhelpful'][0] ?? null;
        if ($topUnhelpful) {
            $title = (string) ($cards[$topUnhelpful['action_key']]['title'] ?? $topUnhelpful['action_key']);
            $type = (string) ($cards[$topUnhelpful['action_key']]['type'] ?? '');
            $bucket = (array) config('dodo-action-replies.feedback_unhelpful.'.$type, []);
            if (! empty($bucket)) {
                $tpl = (string) $bucket[array_rand($bucket)];
                $lines[] = str_replace('{{action_title}}', $title, $tpl);
            } else {
                $lines[] = "「{$title}」對妳這個月幫助不大，下次我們換一個試試。";
            }
        }

        // symptom trend（v2 沒給對應模板，hard-coded 一致語氣）
        if ($vsPrevious) {
            $lines[] = match ($vsPrevious['symptom_trend']) {
                'better' => '比起上個週期，身體狀態好一點了。妳有照顧到它。',
                'worse' => '這個月辛苦了一些，下個月我們慢一點、輕一點。',
                default => '節奏跟上個月差不多，穩定也是一種好。',
            };
        }

        // 結尾
        $lines[] = '下個週期，朵朵繼續陪妳。';

        $message = implode("\n", array_filter($lines));

        return $this->sanitizer->sanitize($message);
    }

    private function pick(string $key, array $vars = []): string
    {
        $bucket = (array) config('dodo-action-replies.'.$key, []);
        if (empty($bucket) || ! array_is_list($bucket)) {
            return '';
        }
        $tpl = (string) $bucket[array_rand($bucket)];

        foreach ($vars as $k => $v) {
            $tpl = str_replace('{{'.$k.'}}', (string) $v, $tpl);
        }

        return $tpl;
    }
}
