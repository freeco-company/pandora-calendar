<?php

namespace App\Services\Gamification;

use App\Models\Achievement;
use App\Models\BbtReading;
use App\Models\Cycle;
use App\Models\CyclePatternReport;
use App\Models\CycleSymptom;
use App\Models\DodoCheckin;
use App\Models\HealthSample;
use App\Models\Pregnancy;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * P3 — 成就檢查器。在 cycle / symptom / dodo / bbt save 之後呼叫，
 * 自動檢查所有 catalog 規則 + INSERT IGNORE 寫進 achievements 表。
 *
 * 透過 publisher 同步發 calendar.achievement_unlocked 給 py-service ledger（XP）。
 *
 * 2026-05-03 擴充：搭配 AchievementCatalog 17 → 38，補：
 *   - mood_logs（CycleSymptom 有 mood column 視為 mood log）
 *   - pattern_reports（CyclePatternReport count）
 *   - health_samples（HealthSample 同步天數 / 啟動）
 *   - bbt_biphasic（單一週期內偵測到雙相 — 簡化用 BbtReading 連續 6 天 ≥ 0.3°C 上升判定）
 *   - pregnancy_mode（Pregnancy 表有任何 record）
 *   - full_phase / cycle_perfect_4_phases（單一週期 phase 計數）
 *   - outfits 解鎖數（讀 OutfitCatalog 套同 context）
 */
final class AchievementChecker
{
    public function __construct(private GamificationPublisher $publisher) {}

    /**
     * 檢查 user 所有可能解鎖的成就，回傳本次新解鎖的 keys。
     *
     * @return list<string>
     */
    public function checkAll(User $user): array
    {
        $existing = Achievement::query()
            ->where('user_id', $user->id)
            ->pluck('achievement_key')
            ->all();

        $cyclesCount = Cycle::query()->where('user_id', $user->id)->count();
        $symptomsCount = CycleSymptom::query()->where('user_id', $user->id)->count();
        $checkinsCount = DodoCheckin::query()->where('user_id', $user->id)->count();
        $bbtCount = BbtReading::query()->where('user_id', $user->id)->count();
        $hasPartnerShare = $user->partner_share_enabled_at !== null;
        $level = (int) ($user->level ?? 1);
        $streak = $this->computeStreak($user->id);

        // 2026-05-03 新增 signals
        $moodCount = $this->countMoodLogs($user->id);
        $patternReports = Schema::hasTable('cycle_pattern_reports')
            ? CyclePatternReport::query()->where('user_id', $user->id)->count()
            : 0;
        $healthSamples = Schema::hasTable('health_samples')
            ? HealthSample::query()->where('user_id', $user->id)->count()
            : 0;
        $healthSyncDays = $this->countHealthSyncDays($user->id);
        $hasPregnancy = Schema::hasTable('pregnancies')
            ? Pregnancy::query()->where('user_id', $user->id)->exists()
            : false;
        $hasBiphasic = $this->detectBiphasic($user->id);
        $maxPhasesInOneCycle = $this->maxPhasesCoveredInOneCycle($user->id);
        $unlockedOutfits = count(OutfitCatalog::unlockedFor([
            'level' => $level,
            'streak' => $streak,
            'achievements' => $existing,
            'is_premium' => ($user->subscription_tier ?? null) === 'premium',
            'cycles' => $cyclesCount,
            'month' => (int) date('n'),
        ]));

        $ctx = [
            'cycles' => $cyclesCount,
            'symptoms' => $symptomsCount,
            'checkins' => $checkinsCount,
            'bbt' => $bbtCount,
            'partner_share' => $hasPartnerShare,
            'level' => $level,
            'streak' => $streak,
            'moods' => $moodCount,
            'pattern_reports' => $patternReports,
            'health_samples' => $healthSamples,
            'health_sync_days' => $healthSyncDays,
            'has_pregnancy' => $hasPregnancy,
            'has_biphasic' => $hasBiphasic,
            'max_phases_in_one_cycle' => $maxPhasesInOneCycle,
            'unlocked_outfits' => $unlockedOutfits,
        ];

        $newlyUnlocked = [];
        foreach (AchievementCatalog::all() as $a) {
            if (in_array($a['key'], $existing, true)) {
                continue;
            }
            if (! $this->isUnlocked($a, $ctx)) {
                continue;
            }

            try {
                Achievement::query()->create([
                    'user_id' => $user->id,
                    'achievement_key' => $a['key'],
                    'unlocked_at' => now(),
                ]);
                $newlyUnlocked[] = $a['key'];
                // 不另發 publisher event：成就 XP 是 conceptual layer，本機判定即可。
                // 用戶 XP 已經透過原本的 cycle_logged / symptom_logged / dodo_checkin 等
                // event 進 py-service ledger 累積。
            } catch (\Throwable) {
                // race / unique constraint — silent
            }
        }

        return $newlyUnlocked;
    }

    /**
     * @param  array{key:string, kind:string, target:?int}  $a
     * @param  array<string,mixed>  $ctx
     */
    private function isUnlocked(array $a, array $ctx): bool
    {
        return match ($a['key']) {
            // First 系列
            'first_cycle_logged' => $ctx['cycles'] >= 1,
            'first_symptom_logged' => $ctx['symptoms'] >= 1,
            'first_dodo_checkin' => $ctx['checkins'] >= 1,
            'first_mood_logged' => $ctx['moods'] >= 1,
            'first_bbt' => $ctx['bbt'] >= 1,
            'first_health_sync' => $ctx['health_samples'] >= 1,
            'first_pattern_report' => $ctx['pattern_reports'] >= 1,
            'first_partner_share' => $ctx['partner_share'],

            // Streak 系列
            'streak_3' => $ctx['streak'] >= 3,
            'streak_7' => $ctx['streak'] >= 7,
            'streak_14' => $ctx['streak'] >= 14,
            'streak_30' => $ctx['streak'] >= 30,
            'streak_60' => $ctx['streak'] >= 60,
            'streak_90' => $ctx['streak'] >= 90,

            // Cycles
            'cycles_3' => $ctx['cycles'] >= 3,
            'cycles_6' => $ctx['cycles'] >= 6,
            'cycles_12' => $ctx['cycles'] >= 12,

            // Symptom / Mood
            'symptoms_30' => $ctx['symptoms'] >= 30,
            'symptoms_100' => $ctx['symptoms'] >= 100,
            'moods_30' => $ctx['moods'] >= 30,
            'full_phase_journey' => $ctx['max_phases_in_one_cycle'] >= 4,
            'cycle_perfect_4_phases' => $ctx['max_phases_in_one_cycle'] >= 4,

            // Dodo
            'dodo_chats_30' => $ctx['checkins'] >= 30,
            'dodo_chats_50' => $ctx['checkins'] >= 50,
            'dodo_chats_100' => $ctx['checkins'] >= 100,

            // BBT
            'bbt_30' => $ctx['bbt'] >= 30,
            'bbt_60' => $ctx['bbt'] >= 60,
            'bbt_biphasic' => $ctx['has_biphasic'],

            // Pattern report
            'pattern_reports_3' => $ctx['pattern_reports'] >= 3,
            'pattern_reports_6' => $ctx['pattern_reports'] >= 6,

            // Health
            'health_sync_30' => $ctx['health_sync_days'] >= 30,

            // Level
            'level_5' => $ctx['level'] >= 5,
            'level_10' => $ctx['level'] >= 10,
            'level_20' => $ctx['level'] >= 20,
            'level_30' => $ctx['level'] >= 30,

            // Outfit collection
            'outfits_5' => $ctx['unlocked_outfits'] >= 5,
            'outfits_15' => $ctx['unlocked_outfits'] >= 15,

            // Pregnancy
            'pregnancy_mode_on' => $ctx['has_pregnancy'],

            default => false,
        };
    }

    private function computeStreak(int $userId): int
    {
        $dates = DB::table('cycles')->where('user_id', $userId)->pluck('start_date')
            ->merge(DB::table('cycle_symptoms')->where('user_id', $userId)->pluck('logged_on'))
            ->merge(DB::table('dodo_checkins')->where('user_id', $userId)->pluck('checked_on'))
            ->merge(DB::table('bbt_readings')->where('user_id', $userId)->pluck('measured_on'))
            ->map(fn ($d) => is_string($d) ? substr($d, 0, 10) : (string) $d)
            ->unique()
            ->sort()
            ->values();

        if ($dates->isEmpty()) {
            return 0;
        }

        $streak = 0;
        $cursor = \Carbon\Carbon::today();
        while ($dates->contains($cursor->toDateString())) {
            $streak++;
            $cursor->subDay();
        }

        return $streak;
    }

    /**
     * mood log = CycleSymptom 有 mood / mood_value column 的 row。
     * 若 schema 沒有該欄位則 fallback 為「symptom 全部都算 mood log」（極簡近似）。
     */
    private function countMoodLogs(int $userId): int
    {
        if (! Schema::hasTable('cycle_symptoms')) {
            return 0;
        }
        $hasMoodCol = Schema::hasColumn('cycle_symptoms', 'mood')
            || Schema::hasColumn('cycle_symptoms', 'mood_value')
            || Schema::hasColumn('cycle_symptoms', 'mood_score');
        $q = CycleSymptom::query()->where('user_id', $userId);
        if (! $hasMoodCol) {
            return 0;
        }
        if (Schema::hasColumn('cycle_symptoms', 'mood')) {
            return (int) $q->whereNotNull('mood')->count();
        }
        if (Schema::hasColumn('cycle_symptoms', 'mood_value')) {
            return (int) $q->whereNotNull('mood_value')->count();
        }

        return (int) $q->whereNotNull('mood_score')->count();
    }

    /**
     * 連續 N 天有 health sample 的天數（取最近的連續區段）。
     */
    private function countHealthSyncDays(int $userId): int
    {
        if (! Schema::hasTable('health_samples')) {
            return 0;
        }
        $dateCol = Schema::hasColumn('health_samples', 'recorded_on')
            ? 'recorded_on'
            : (Schema::hasColumn('health_samples', 'sample_date') ? 'sample_date' : null);
        if ($dateCol === null) {
            // fallback 用 created_at date 估算
            $dates = DB::table('health_samples')
                ->where('user_id', $userId)
                ->selectRaw('DATE(created_at) as d')
                ->distinct()
                ->pluck('d');
        } else {
            $dates = DB::table('health_samples')
                ->where('user_id', $userId)
                ->pluck($dateCol)
                ->map(fn ($d) => is_string($d) ? substr($d, 0, 10) : (string) $d)
                ->unique();
        }

        return (int) $dates->count();
    }

    /**
     * 雙相 BBT 偵測（簡化版）：在最近 30 天內找一段「連續 ≥ 6 天」其平均比前 6 天平均高 ≥ 0.3°C。
     * 真實偵測在 BodyRhythmCalculator 做，這裡只給成就用。
     */
    private function detectBiphasic(int $userId): bool
    {
        if (! Schema::hasTable('bbt_readings')) {
            return false;
        }
        $rows = BbtReading::query()
            ->where('user_id', $userId)
            ->orderBy('measured_on')
            ->get(['measured_on', 'temperature_c']);

        if ($rows->count() < 12) {
            return false;
        }

        $temps = $rows->pluck('temperature_c')->map(fn ($t) => (float) $t)->all();
        $n = count($temps);
        for ($i = 6; $i + 6 <= $n; $i++) {
            $pre = array_slice($temps, $i - 6, 6);
            $post = array_slice($temps, $i, 6);
            $preAvg = array_sum($pre) / 6;
            $postAvg = array_sum($post) / 6;
            if ($postAvg - $preAvg >= 0.3) {
                return true;
            }
        }

        return false;
    }

    /**
     * 找出單一週期內覆蓋的 phase 數最大值（menstrual / follicular / ovulation / luteal）。
     * Phase 由 Cycle 起始日 + 標準週期天數推算。
     * 簡化：每個 cycle window 內，看 symptom logged_on 落在哪 phase。
     */
    private function maxPhasesCoveredInOneCycle(int $userId): int
    {
        if (! Schema::hasTable('cycles') || ! Schema::hasTable('cycle_symptoms')) {
            return 0;
        }
        $cycles = Cycle::query()
            ->where('user_id', $userId)
            ->orderBy('start_date')
            ->get(['id', 'start_date']);
        if ($cycles->isEmpty()) {
            return 0;
        }

        $maxPhases = 0;
        foreach ($cycles as $idx => $c) {
            $start = \Carbon\Carbon::parse($c->start_date);
            // 用下一個 cycle 起始 - 當前 cycle 起始 算 length；最後一個 fallback 28
            $next = $cycles[$idx + 1] ?? null;
            $length = $next
                ? max(1, (int) \Carbon\Carbon::parse($next->start_date)->diffInDays($start))
                : 28;
            $end = $start->copy()->addDays($length - 1);
            $logs = CycleSymptom::query()
                ->where('user_id', $userId)
                ->whereBetween('logged_on', [$start->toDateString(), $end->toDateString()])
                ->pluck('logged_on');
            $phases = [];
            foreach ($logs as $d) {
                $day = (int) \Carbon\Carbon::parse($d)->diffInDays($start);
                $phase = match (true) {
                    $day <= 5 => 'menstrual',
                    $day <= 13 => 'follicular',
                    $day <= 16 => 'ovulation',
                    default => 'luteal',
                };
                $phases[$phase] = true;
            }
            // 加上週期起始日本身就算 menstrual phase 已有訊號
            $phases['menstrual'] = $phases['menstrual'] ?? true;
            $count = count($phases);
            if ($count > $maxPhases) {
                $maxPhases = $count;
            }
        }

        return $maxPhases;
    }
}
