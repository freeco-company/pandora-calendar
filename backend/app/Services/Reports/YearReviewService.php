<?php

namespace App\Services\Reports;

use App\Models\Achievement;
use App\Models\Cycle;
use App\Models\CycleSymptom;
use App\Models\DodoCheckin;
use App\Models\User;
use App\Services\Calendar\CyclePredictor;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Pandora\Shared\Compliance\LegalContentSanitizer;

/**
 * 年度回顧 — 統計 + 套文案模板（Spotify Wrapped 風格）。
 *
 * config/year-review-templates.php 是 narrative-designer canonical schema：flat key → template string，
 * placeholder 用 {{var_name}}。
 *
 * Output schema：['cards' => [{ id, title, subtitle, body, emoji, sort }], 'stats' => {...}]
 *
 * 樣本不足 (< 2 cycles) 仍出 cover + closing 兩張卡，避免空頁。
 */
class YearReviewService
{
    /**
     * Card 順序與每張對應的 template key + emoji。
     * sort 由前端順序播放；body 是該 template 文案；title / subtitle 走簡短規則。
     */
    private const CARDS = [
        ['id' => 'cover', 'sort' => 1, 'emoji' => '🌙', 'tpl' => 'cover'],
        ['id' => 'cycle_count', 'sort' => 2, 'emoji' => '📅', 'tpl' => 'cycle_count'],
        ['id' => 'phase_distribution', 'sort' => 3, 'emoji' => '🌸', 'tpl' => 'phase_distribution'],
        ['id' => 'top_mood', 'sort' => 4, 'emoji' => '💗', 'tpl' => 'top_mood'],
        ['id' => 'streak_record', 'sort' => 5, 'emoji' => '🔥', 'tpl' => 'streak_record'],
        ['id' => 'top_symptom', 'sort' => 6, 'emoji' => '🌿', 'tpl' => 'top_symptom'],
        ['id' => 'milestone_unlocked', 'sort' => 7, 'emoji' => '🏅', 'tpl' => 'milestone_unlocked'],
        ['id' => 'pet_growth', 'sort' => 8, 'emoji' => '🐾', 'tpl' => 'pet_growth'],
        ['id' => 'dodo_checkins', 'sort' => 9, 'emoji' => '💬', 'tpl' => 'dodo_checkins'],
        ['id' => 'self_awareness', 'sort' => 10, 'emoji' => '🌷', 'tpl' => 'self_awareness'],
        ['id' => 'avg_cycle_length', 'sort' => 11, 'emoji' => '🌀', 'tpl' => 'avg_cycle_length'],
        ['id' => 'closing', 'sort' => 12, 'emoji' => '✨', 'tpl' => 'closing'],
    ];

    public function __construct(
        private readonly LegalContentSanitizer $sanitizer,
        private readonly CyclePredictor $predictor,
    ) {}

    /**
     * @return array{cards: array<int, array<string, mixed>>, stats: array<string, mixed>}
     */
    public function generate(int $userId, int $year): array
    {
        $user = User::findOrFail($userId);
        $start = CarbonImmutable::create($year, 1, 1, 0, 0, 0);
        $end = CarbonImmutable::create($year, 12, 31, 23, 59, 59);

        $cycles = Cycle::where('user_id', $userId)
            ->whereBetween('start_date', [$start->toDateString(), $end->toDateString()])
            ->orderBy('start_date')
            ->get();

        $symptoms = CycleSymptom::where('user_id', $userId)
            ->whereBetween('logged_on', [$start->toDateString(), $end->toDateString()])
            ->get();

        $checkins = DodoCheckin::where('user_id', $userId)
            ->whereBetween('checked_on', [$start->toDateString(), $end->toDateString()])
            ->get();

        $stats = $this->buildStats($user, $cycles, $symptoms, $checkins, $year);

        // 樣本不足：只出 cover + closing
        $cardDefs = $cycles->count() < 2
            ? array_filter(self::CARDS, fn ($c) => in_array($c['id'], ['cover', 'closing'], true))
            : self::CARDS;

        $cards = [];
        foreach ($cardDefs as $def) {
            $tpl = (string) (config('year-review-templates.'.$def['tpl']) ?? '');
            $body = $this->render($tpl, $stats);
            if ($body === '') {
                continue;
            }
            $cards[] = [
                'id' => $def['id'],
                'sort' => $def['sort'],
                'emoji' => $def['emoji'],
                'title' => $this->cardTitle($def['id'], $stats),
                'subtitle' => '',
                'body' => $body,
            ];
        }

        usort($cards, fn ($a, $b) => $a['sort'] <=> $b['sort']);

        return ['cards' => $cards, 'stats' => $stats];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildStats(User $user, Collection $cycles, Collection $symptoms, Collection $checkins, int $year): array
    {
        // avg cycle length（n cycles → n-1 個 length）
        $cycleLengths = collect();
        $sorted = $cycles->sortBy('start_date')->values();
        for ($i = 1; $i < $sorted->count(); $i++) {
            $cycleLengths->push(
                CarbonImmutable::parse($sorted[$i - 1]->start_date)
                    ->diffInDays(CarbonImmutable::parse($sorted[$i]->start_date))
            );
        }
        $avgCycleLength = $cycleLengths->isNotEmpty()
            ? (int) round($cycleLengths->avg())
            : (int) CyclePredictor::DEFAULT_CYCLE_LENGTH;

        // top mood 從 dodo checkins
        $topMood = $checkins->countBy('mood')->sortDesc()->keys()->first();

        // top 3 symptom tags
        $allTags = collect();
        foreach ($symptoms as $s) {
            foreach ($s->tags ?? [] as $tag) {
                $allTags->push($tag);
            }
        }
        $topSymptoms = $allTags->countBy()->sortDesc()->take(3);
        $topSymptom = $topSymptoms->keys()->first();

        // streak（連續 logged_on 最長）
        $loggedDates = $symptoms->pluck('logged_on')
            ->merge($checkins->pluck('checked_on'))
            ->filter()
            ->map(fn ($d) => $d->toDateString())
            ->unique()
            ->sort()
            ->values();
        $maxStreak = $this->longestStreak($loggedDates);

        // phase distribution — 用 dodo checkin 的 phase_at_checkin 近似
        $phaseDist = $checkins
            ->whereNotNull('phase_at_checkin')
            ->countBy('phase_at_checkin');

        // luteal symptoms count（黃體期內症狀記錄）
        $lutealSymptomCount = 0;
        foreach ($cycles as $c) {
            $start = CarbonImmutable::parse($c->start_date);
            $lutealSymptomCount += $symptoms->filter(function ($s) use ($start) {
                return $s->logged_on >= $start->subDays(14)
                    && $s->logged_on < $start->subDays(4);
            })->count();
        }

        // achievement count
        $achievementCount = Achievement::query()
            ->where('user_id', $user->id)
            ->whereNotNull('unlocked_at')
            ->count();

        // pet level — 用 user.level 當 end，start 估
        $petLevelEnd = (int) ($user->level ?? 1);
        $petLevelStart = max(1, $petLevelEnd - max(1, (int) floor($cycles->count() / 2)));

        // insights read（從 user.preferences 讀計數，沒有就 0）
        $insightCount = (int) ($user->preferences['year_insight_read_count'] ?? 0);

        return [
            'user_first_name' => $this->extractFirstName($user),
            'pet_name' => $user->pet_nickname ?? '小夥伴',
            'year' => $year,
            'count' => $cycles->count(),
            'cycle_count' => $cycles->count(),
            'avg_length' => $avgCycleLength,
            'avg_cycle_length' => $avgCycleLength,
            'top_phase' => $this->phaseLabel($phaseDist->keys()->first() ?? 'follicular'),
            'menstrual_days' => (int) ($phaseDist['menstrual'] ?? 0),
            'follicular_days' => (int) ($phaseDist['follicular'] ?? 0),
            'ovulation_days' => (int) ($phaseDist['ovulation'] ?? 0),
            'luteal_days' => (int) ($phaseDist['luteal'] ?? 0),
            'top_mood' => $this->moodLabel($topMood ?? 'okay'),
            'max_streak' => $maxStreak,
            'top_3_symptoms' => $topSymptoms->keys()->all(),
            'top_symptom' => $this->symptomLabel($topSymptom ?? '—'),
            'achievement_count' => $achievementCount,
            'start_level' => $petLevelStart,
            'end_level' => $petLevelEnd,
            'pet_level_start' => $petLevelStart,
            'pet_level_end' => $petLevelEnd,
            'checkin_count' => $checkins->count(),
            'insight_count' => $insightCount,
            'luteal_symptoms' => $lutealSymptomCount,
            'meal_photos' => 0, // 跨 App 之後從集團 profile 補
            'step_count' => 0,
            'phase_distribution' => $phaseDist->all(),
        ];
    }

    private function longestStreak(Collection $sortedDates): int
    {
        if ($sortedDates->isEmpty()) {
            return 0;
        }
        $best = 1;
        $cur = 1;
        $prev = CarbonImmutable::parse($sortedDates->first());
        foreach ($sortedDates->slice(1) as $d) {
            $today = CarbonImmutable::parse($d);
            if ($prev->addDay()->isSameDay($today)) {
                $cur++;
                $best = max($best, $cur);
            } else {
                $cur = 1;
            }
            $prev = $today;
        }

        return $best;
    }

    private function extractFirstName(User $user): string
    {
        return $user->display_name ?? $user->name ?? '朋友';
    }

    private function phaseLabel(string $phase): string
    {
        return match ($phase) {
            'menstrual' => '經期',
            'follicular' => '卵泡期',
            'ovulation' => '排卵期',
            'luteal' => '黃體期',
            default => '日常',
        };
    }

    private function moodLabel(string $mood): string
    {
        return match ($mood) {
            'great', 'good' => '愉快',
            'okay' => '平靜',
            'low', 'sad' => '低落',
            'irritable' => '煩躁',
            'anxious' => '焦慮',
            'tired' => '疲憊',
            'cramping' => '不舒服',
            'bad' => '低落',
            default => $mood,
        };
    }

    private function symptomLabel(string $symptom): string
    {
        return match ($symptom) {
            'cramp' => '經痛',
            'headache' => '頭痛',
            'fatigue' => '疲倦',
            'bloating' => '腹脹',
            'breast_tender' => '胸脹',
            'acne' => '冒痘',
            'mood_swing' => '情緒起伏',
            'craving_sweet' => '想吃甜',
            'craving_salty' => '想吃鹹',
            'insomnia' => '失眠',
            'back_pain' => '腰痠',
            'anxious' => '焦慮',
            'irritable' => '煩躁',
            'low_mood' => '心情低',
            default => $symptom,
        };
    }

    /**
     * Card 標題（短句，前端可直接顯示在 slide 頂）
     *
     * @param  array<string, mixed>  $stats
     */
    private function cardTitle(string $cardId, array $stats): string
    {
        return match ($cardId) {
            'cover' => $stats['user_first_name'].' 的 '.$stats['year'],
            'cycle_count' => '今年的週期',
            'phase_distribution' => '妳走過的每個階段',
            'top_mood' => '今年最常的心情',
            'streak_record' => '最長連續記錄',
            'top_symptom' => '身體最常給的訊號',
            'milestone_unlocked' => '解鎖的成就',
            'pet_growth' => '小夥伴的成長',
            'dodo_checkins' => '與朵朵的對話',
            'self_awareness' => '對自己的認識',
            'avg_cycle_length' => '妳的節奏',
            'closing' => '謝謝妳',
            default => '',
        };
    }

    /**
     * 套 placeholder ({{var}}) + 過 sanitizer
     *
     * @param  array<string, mixed>  $stats
     */
    private function render(string $template, array $stats): string
    {
        if ($template === '') {
            return '';
        }
        $out = $template;
        foreach ($stats as $k => $v) {
            if (is_scalar($v) || $v === null) {
                $out = str_replace('{{'.$k.'}}', (string) $v, $out);
            }
        }

        return $this->sanitizer->sanitize($out);
    }
}
