<?php

namespace App\Services\Gamification;

use App\Models\Achievement;
use App\Models\BbtReading;
use App\Models\Cycle;
use App\Models\CycleSymptom;
use App\Models\DodoCheckin;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * P3 — 成就檢查器。在 cycle / symptom / dodo / bbt save 之後呼叫，
 * 自動檢查所有 catalog 規則 + INSERT IGNORE 寫進 achievements 表。
 *
 * 透過 publisher 同步發 calendar.achievement_unlocked 給 py-service ledger（XP）。
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

        $newlyUnlocked = [];
        foreach (AchievementCatalog::all() as $a) {
            if (in_array($a['key'], $existing, true)) {
                continue;
            }
            if (! $this->isUnlocked($a, [
                'cycles' => $cyclesCount,
                'symptoms' => $symptomsCount,
                'checkins' => $checkinsCount,
                'bbt' => $bbtCount,
                'partner_share' => $hasPartnerShare,
                'level' => $level,
                'streak' => $streak,
            ])) {
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
     * @param  array{cycles:int, symptoms:int, checkins:int, bbt:int, partner_share:bool, level:int, streak:int}  $ctx
     */
    private function isUnlocked(array $a, array $ctx): bool
    {
        return match ($a['key']) {
            'first_cycle_logged' => $ctx['cycles'] >= 1,
            'first_symptom_logged' => $ctx['symptoms'] >= 1,
            'first_dodo_checkin' => $ctx['checkins'] >= 1,
            'first_bbt' => $ctx['bbt'] >= 1,
            'first_partner_share' => $ctx['partner_share'],
            'streak_7' => $ctx['streak'] >= 7,
            'streak_30' => $ctx['streak'] >= 30,
            'streak_90' => $ctx['streak'] >= 90,
            'cycles_3' => $ctx['cycles'] >= 3,
            'cycles_6' => $ctx['cycles'] >= 6,
            'cycles_12' => $ctx['cycles'] >= 12,
            'symptoms_30' => $ctx['symptoms'] >= 30,
            'dodo_chats_50' => $ctx['checkins'] >= 50,
            'bbt_30' => $ctx['bbt'] >= 30,
            'level_5' => $ctx['level'] >= 5,
            'level_10' => $ctx['level'] >= 10,
            'level_20' => $ctx['level'] >= 20,
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
}
