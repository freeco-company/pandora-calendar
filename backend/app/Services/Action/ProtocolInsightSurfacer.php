<?php

namespace App\Services\Action;

use App\Models\ActionFeedback;
use App\Models\CycleSymptom;
use App\Models\DailyActionRecommendation;
use App\Models\ProtocolInsightDismissed;
use App\Models\UserActionProtocol;
use Carbon\CarbonImmutable;
use Pandora\Shared\Compliance\LegalContentSanitizer;

/**
 * ProtocolInsightSurfacer — 從 user 累積的 protocol / feedback / symptom 資料中
 * 找出值得告訴用戶的 insight，讓朵朵主動報「我發現 X 對妳 work」。
 *
 * 三類 insight（按優先順序）：
 *   1. specific_action_works    → 同 (phase, action_key) sample_size ≥ 5 + score ≥ 0.7
 *   2. type_responds            → 同 type 累積 ≥ 8 helpful feedback
 *   3. recurring_phase_symptom  → 連續 3 cycle 同 phase 都記錄到某 symptom
 *
 * 規則：
 *   - 每 user 同時只 surface 1 個 insight（取最高 score）
 *   - dismissed 後 7 天不再 surface 同個 key
 *   - 文案必過 sanitizer，紅線命中跳到下個候選
 */
class ProtocolInsightSurfacer
{
    private const DISMISS_COOLDOWN_DAYS = 7;
    private const TYPE_LABELS = [
        'sleep' => '睡眠類',
        'move' => '輕運動類',
        'eat' => '飲食類',
        'relax' => '放鬆類',
        'track' => '紀錄類',
        'learn' => '衛教類',
        'connect' => '陪伴類',
    ];

    private const PHASE_LABELS = [
        'menstrual' => '經期',
        'follicular' => '濾泡期',
        'ovulation' => '排卵期',
        'luteal' => '黃體期',
    ];

    private const SYMPTOM_LABELS = [
        'cramp' => '經痛',
        'headache' => '頭痛',
        'fatigue' => '疲倦',
        'bloating' => '腹脹',
        'breast_tender' => '胸脹',
        'acne' => '冒痘',
        'mood_swing' => '情緒起伏',
        'craving_sweet' => '想吃甜',
        'insomnia' => '失眠',
        'back_pain' => '腰痠',
    ];

    public function __construct(
        private readonly LegalContentSanitizer $sanitizer,
    ) {}

    /**
     * @return array{insight_key:string,message:string,action_cta:?string,source:string,score:float}|null
     */
    public function activeFor(int $userId, ?CarbonImmutable $now = null): ?array
    {
        $now ??= CarbonImmutable::now();

        $candidates = [];
        foreach ($this->collectSpecificActionInsights($userId) as $c) {
            $candidates[] = $c;
        }
        foreach ($this->collectTypeResponseInsights($userId) as $c) {
            $candidates[] = $c;
        }
        foreach ($this->collectRecurringSymptomInsights($userId) as $c) {
            $candidates[] = $c;
        }

        if (empty($candidates)) {
            return null;
        }

        usort($candidates, fn ($a, $b) => $b['score'] <=> $a['score']);

        $dismissedKeys = $this->dismissedWithinCooldown($userId, $now);

        foreach ($candidates as $c) {
            if (in_array($c['insight_key'], $dismissedKeys, true)) {
                continue;
            }
            if ($this->sanitizer->riskReport($c['message']) !== []) {
                continue;
            }

            return $c;
        }

        return null;
    }

    public function dismiss(int $userId, string $insightKey, ?CarbonImmutable $now = null): ProtocolInsightDismissed
    {
        $now ??= CarbonImmutable::now();

        return ProtocolInsightDismissed::create([
            'user_id' => $userId,
            'insight_key' => $insightKey,
            'dismissed_at' => $now,
        ]);
    }

    /**
     * @return array<string>
     */
    private function dismissedWithinCooldown(int $userId, CarbonImmutable $now): array
    {
        $cutoff = $now->subDays(self::DISMISS_COOLDOWN_DAYS);

        return ProtocolInsightDismissed::where('user_id', $userId)
            ->where('dismissed_at', '>=', $cutoff)
            ->pluck('insight_key')
            ->all();
    }

    /**
     * @return array<int, array{insight_key:string,message:string,action_cta:?string,source:string,score:float}>
     */
    private function collectSpecificActionInsights(int $userId): array
    {
        $rows = UserActionProtocol::where('user_id', $userId)
            ->where('sample_size', '>=', 5)
            ->where('effectiveness_score', '>=', 0.7)
            ->get();

        $cards = (array) config('daily-actions', []);
        $out = [];
        foreach ($rows as $row) {
            $title = (string) ($cards[$row->action_key]['title'] ?? $row->action_key);
            $phaseLabel = self::PHASE_LABELS[$row->phase] ?? $row->phase;
            $message = "朵朵發現「{$title}」對妳特別 work（{$phaseLabel}試了 {$row->sample_size} 次都有感）";

            $out[] = [
                'insight_key' => 'action_works:'.$row->phase.':'.$row->action_key,
                'message' => $message,
                'action_cta' => $row->action_key,
                'source' => 'specific_action_works',
                'score' => 1.0 + $row->effectiveness_score + min($row->sample_size, 20) * 0.01,
            ];
        }

        return $out;
    }

    /**
     * @return array<int, array{insight_key:string,message:string,action_cta:?string,source:string,score:float}>
     */
    private function collectTypeResponseInsights(int $userId): array
    {
        $rows = ActionFeedback::where('user_id', $userId)
            ->where('feedback', 'helpful')
            ->get(['recommendation_id']);
        if ($rows->isEmpty()) {
            return [];
        }

        $recIds = $rows->pluck('recommendation_id')->all();
        $recs = DailyActionRecommendation::whereIn('id', $recIds)->get(['action_key']);

        $cards = (array) config('daily-actions', []);
        $byType = [];
        foreach ($recs as $r) {
            $type = (string) ($cards[$r->action_key]['type'] ?? '');
            if ($type === '') {
                continue;
            }
            $byType[$type] = ($byType[$type] ?? 0) + 1;
        }

        $out = [];
        foreach ($byType as $type => $count) {
            if ($count < 8) {
                continue;
            }
            $label = self::TYPE_LABELS[$type] ?? $type;
            $message = "妳對{$label}比較有反應（最近累積 {$count} 次都標記有感），朵朵之後會多推這類給妳。";

            $out[] = [
                'insight_key' => 'type_responds:'.$type,
                'message' => $message,
                'action_cta' => null,
                'source' => 'type_responds',
                'score' => 0.8 + min($count, 30) * 0.005,
            ];
        }

        return $out;
    }

    /**
     * @return array<int, array{insight_key:string,message:string,action_cta:?string,source:string,score:float}>
     */
    private function collectRecurringSymptomInsights(int $userId): array
    {
        // 取近 120 天 symptoms，按 logged_on 排序、依 phase 推估
        $rows = CycleSymptom::where('user_id', $userId)
            ->where('logged_on', '>=', CarbonImmutable::today()->subDays(120)->toDateString())
            ->orderBy('logged_on')
            ->get();
        if ($rows->isEmpty()) {
            return [];
        }

        // 用 DailyActionRecommendation 的 phase 作為當天 phase 來源（已 cache）；
        // 沒有對應 rec 則用 PredictionPhase 推估太貴 → 用 rec 反查近似。
        $recsByDate = DailyActionRecommendation::where('user_id', $userId)
            ->where('recommended_on', '>=', CarbonImmutable::today()->subDays(120)->toDateString())
            ->get(['recommended_on', 'phase'])
            ->keyBy(fn ($r) => $r->recommended_on?->toDateString());

        // 統計：每組 (phase, symptom) → 落在「不同 cycle」的天數
        // 簡化作法：每月 (year-month) 視為一個 cycle 鬆耦合判斷
        $stats = []; // [phase][symptom] => set<year-month>
        foreach ($rows as $sym) {
            $iso = $sym->logged_on?->toDateString();
            if (! $iso) {
                continue;
            }
            $rec = $recsByDate->get($iso);
            $phase = $rec?->phase;
            if (! $phase || ! isset(self::PHASE_LABELS[$phase])) {
                continue;
            }
            $tags = (array) ($sym->tags ?? []);
            $month = substr($iso, 0, 7);
            foreach ($tags as $tag) {
                if (! isset(self::SYMPTOM_LABELS[$tag])) {
                    continue;
                }
                $stats[$phase][$tag][$month] = true;
            }
        }

        $out = [];
        foreach ($stats as $phase => $bySymptom) {
            foreach ($bySymptom as $symptom => $months) {
                $cycleCount = count($months);
                if ($cycleCount < 3) {
                    continue;
                }
                $phaseLabel = self::PHASE_LABELS[$phase];
                $symptomLabel = self::SYMPTOM_LABELS[$symptom];
                $message = "朵朵注意到妳每個月{$phaseLabel}都會出現{$symptomLabel}，連續 {$cycleCount} 個週期了。";

                $out[] = [
                    'insight_key' => 'recurring:'.$phase.':'.$symptom,
                    'message' => $message,
                    'action_cta' => null,
                    'source' => 'recurring_phase_symptom',
                    'score' => 0.6 + min($cycleCount, 12) * 0.02,
                ];
            }
        }

        return $out;
    }
}
