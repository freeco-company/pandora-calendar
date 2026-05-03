<?php

namespace App\Services\Gamification;

/**
 * P3 — calendar 成就 catalog（hard-coded，集團 catalog parity 由 py-service 負責）。
 *
 * Schema 對齊 design-svg badges/ — 9 級設計：
 *   - first / streak / milestone × bronze / silver / gold
 *
 * tier 決定 badge SVG（共用 design-svg badge_{kind}_{tier}.svg）+ XP 獎賞。
 */
final class AchievementCatalog
{
    public const TIER_BRONZE = 'bronze';

    public const TIER_SILVER = 'silver';

    public const TIER_GOLD = 'gold';

    public const KIND_FIRST = 'first';

    public const KIND_STREAK = 'streak';

    public const KIND_MILESTONE = 'milestone';

    /**
     * @return list<array{
     *   key:string, name:string, hint:string, kind:string, tier:string,
     *   badge:string, xp:int, target:?int
     * }>
     */
    public static function all(): array
    {
        return [
            // --- 第一次系列（first）---
            self::entry('first_cycle_logged', '第一次記錄', '記下第一次經期', self::KIND_FIRST, self::TIER_BRONZE, 30),
            self::entry('first_symptom_logged', '第一次記症狀', '記下第一個身體訊號', self::KIND_FIRST, self::TIER_BRONZE, 10),
            self::entry('first_dodo_checkin', '初次見朵朵', '第一次 check-in 朵朵', self::KIND_FIRST, self::TIER_BRONZE, 10),
            self::entry('first_bbt', '第一次量基礎體溫', '記下第一次 BBT', self::KIND_FIRST, self::TIER_SILVER, 20),
            self::entry('first_partner_share', '勇敢分享', '第一次開啟伴侶分享', self::KIND_FIRST, self::TIER_GOLD, 50),

            // --- 連勝系列（streak）---
            self::entry('streak_7', '連勝 7 天', '連續 7 天有任何記錄', self::KIND_STREAK, self::TIER_BRONZE, 50, 7),
            self::entry('streak_30', '連勝 30 天', '連續 30 天有任何記錄', self::KIND_STREAK, self::TIER_SILVER, 200, 30),
            self::entry('streak_90', '連勝 90 天', '連續 90 天有任何記錄', self::KIND_STREAK, self::TIER_GOLD, 500, 90),

            // --- 里程碑系列（milestone）---
            self::entry('cycles_3', '記錄 3 個週期', '完成 3 個完整週期記錄', self::KIND_MILESTONE, self::TIER_BRONZE, 100, 3),
            self::entry('cycles_6', '記錄 6 個週期', '半年的週期記錄', self::KIND_MILESTONE, self::TIER_SILVER, 300, 6),
            self::entry('cycles_12', '記錄 12 個週期', '一年的週期記錄', self::KIND_MILESTONE, self::TIER_GOLD, 800, 12),
            self::entry('symptoms_30', '30 天身體記錄', '記錄 30 次身體訊號', self::KIND_MILESTONE, self::TIER_BRONZE, 80, 30),
            self::entry('dodo_chats_50', '跟朵朵對話 50 次', '50 次 check-in', self::KIND_MILESTONE, self::TIER_SILVER, 150, 50),
            self::entry('bbt_30', 'BBT 30 天', '基礎體溫累積 30 天', self::KIND_MILESTONE, self::TIER_SILVER, 200, 30),

            // --- Level 系列 ---
            self::entry('level_5', '達到 Lv 5', '寵物升到 Lv 5', self::KIND_MILESTONE, self::TIER_BRONZE, 0, 5),
            self::entry('level_10', '達到 Lv 10', '寵物升到 Lv 10', self::KIND_MILESTONE, self::TIER_SILVER, 0, 10),
            self::entry('level_20', '達到 Lv 20', '寵物升到 Lv 20', self::KIND_MILESTONE, self::TIER_GOLD, 0, 20),
        ];
    }

    /**
     * @return ?array{key:string, name:string, hint:string, kind:string, tier:string, badge:string, xp:int, target:?int}
     */
    public static function find(string $key): ?array
    {
        foreach (self::all() as $a) {
            if ($a['key'] === $key) {
                return $a;
            }
        }

        return null;
    }

    /**
     * @return array{key:string, name:string, hint:string, kind:string, tier:string, badge:string, xp:int, target:?int}
     */
    private static function entry(string $key, string $name, string $hint, string $kind, string $tier, int $xp, ?int $target = null): array
    {
        return [
            'key' => $key,
            'name' => $name,
            'hint' => $hint,
            'kind' => $kind,
            'tier' => $tier,
            'badge' => "badge_{$kind}_{$tier}",
            'xp' => $xp,
            'target' => $target,
        ];
    }
}
