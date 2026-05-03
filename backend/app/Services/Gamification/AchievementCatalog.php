<?php

namespace App\Services\Gamification;

/**
 * P3 — calendar 成就 catalog（hard-coded，集團 catalog parity 由 py-service 負責）。
 *
 * 2026-05-03 擴充：17 → 38 achievements（對齊母艦 + meal 規模）。
 *
 * Schema 對齊 design-svg badges/ — 9 級設計：
 *   - first / streak / milestone × bronze / silver / gold
 *
 * tier 決定 badge SVG（共用 design-svg badge_{kind}_{tier}.svg）+ XP 獎賞。
 *
 * 文案規範：
 *   - 朵朵語氣鼓勵（不寫「治療」「療效」「改善」等療效詞，過 sanitizer）
 *   - 用「妳 / 朋友 / 夥伴」，不用「您 / 用戶」
 *   - 過繁體中文 sanitizer（`Pandora\Shared\Compliance\LegalContentSanitizer`）
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
            // === 第一次系列（first）8 個 ===
            self::entry('first_cycle_logged', '第一次記錄', '記下第一次經期', self::KIND_FIRST, self::TIER_BRONZE, 30),
            self::entry('first_symptom_logged', '第一次記症狀', '記下第一個身體訊號', self::KIND_FIRST, self::TIER_BRONZE, 10),
            self::entry('first_dodo_checkin', '初次見朵朵', '第一次 check-in 朵朵', self::KIND_FIRST, self::TIER_BRONZE, 10),
            self::entry('first_mood_logged', '第一次記心情', '第一次留下心情標記', self::KIND_FIRST, self::TIER_BRONZE, 10),
            self::entry('first_bbt', '第一次量基礎體溫', '記下第一次 BBT', self::KIND_FIRST, self::TIER_SILVER, 20),
            self::entry('first_health_sync', '健康整合啟動', '第一次連到 HealthKit / Health Connect', self::KIND_FIRST, self::TIER_SILVER, 30),
            self::entry('first_pattern_report', '第一份模式報告', '生成第一份週期 pattern report', self::KIND_FIRST, self::TIER_SILVER, 40),
            self::entry('first_partner_share', '勇敢分享', '第一次開啟伴侶分享', self::KIND_FIRST, self::TIER_GOLD, 50),

            // === 連勝系列（streak）5 個 ===
            self::entry('streak_3', '連勝 3 天', '連續 3 天有任何記錄', self::KIND_STREAK, self::TIER_BRONZE, 20, 3),
            self::entry('streak_7', '連勝 7 天', '連續 7 天有任何記錄', self::KIND_STREAK, self::TIER_BRONZE, 50, 7),
            self::entry('streak_14', '連勝 14 天', '連續兩週的陪伴', self::KIND_STREAK, self::TIER_SILVER, 100, 14),
            self::entry('streak_30', '連勝 30 天', '連續 30 天有任何記錄', self::KIND_STREAK, self::TIER_SILVER, 200, 30),
            self::entry('streak_60', '連勝 60 天', '連續兩個月的陪伴', self::KIND_STREAK, self::TIER_GOLD, 350, 60),
            self::entry('streak_90', '連勝 90 天', '連續 90 天有任何記錄', self::KIND_STREAK, self::TIER_GOLD, 500, 90),

            // === 週期里程碑（cycles）3 個 ===
            self::entry('cycles_3', '記錄 3 個週期', '完成 3 個完整週期記錄', self::KIND_MILESTONE, self::TIER_BRONZE, 100, 3),
            self::entry('cycles_6', '記錄 6 個週期', '半年的週期記錄', self::KIND_MILESTONE, self::TIER_SILVER, 300, 6),
            self::entry('cycles_12', '記錄 12 個週期', '一年的週期記錄', self::KIND_MILESTONE, self::TIER_GOLD, 800, 12),

            // === 症狀 / 心情累積 4 個 ===
            self::entry('symptoms_30', '30 次身體記錄', '記錄 30 次身體訊號', self::KIND_MILESTONE, self::TIER_BRONZE, 80, 30),
            self::entry('symptoms_100', '百次身體記錄', '累積 100 個身體訊號', self::KIND_MILESTONE, self::TIER_SILVER, 200, 100),
            self::entry('moods_30', '30 次心情記錄', '累積 30 次心情標記', self::KIND_MILESTONE, self::TIER_BRONZE, 60, 30),
            self::entry('full_phase_journey', '走過完整一輪', '單一週期記滿 4 個 phase', self::KIND_MILESTONE, self::TIER_SILVER, 150),

            // === 朵朵互動 3 個 ===
            self::entry('dodo_chats_30', '跟朵朵對話 30 次', '30 次 check-in', self::KIND_MILESTONE, self::TIER_BRONZE, 80, 30),
            self::entry('dodo_chats_50', '跟朵朵對話 50 次', '50 次 check-in', self::KIND_MILESTONE, self::TIER_SILVER, 150, 50),
            self::entry('dodo_chats_100', '跟朵朵對話 100 次', '100 次 check-in', self::KIND_MILESTONE, self::TIER_GOLD, 400, 100),

            // === BBT 達人 3 個 ===
            self::entry('bbt_30', 'BBT 30 天', '基礎體溫累積 30 天', self::KIND_MILESTONE, self::TIER_SILVER, 200, 30),
            self::entry('bbt_60', 'BBT 60 天', '基礎體溫累積 60 天', self::KIND_MILESTONE, self::TIER_GOLD, 350, 60),
            self::entry('bbt_biphasic', '雙相曲線達人', '單一週期偵測到雙相 BBT', self::KIND_MILESTONE, self::TIER_GOLD, 250),

            // === Pattern report 進階 2 個 ===
            self::entry('pattern_reports_3', '3 份模式報告', '累積 3 份 pattern report', self::KIND_MILESTONE, self::TIER_SILVER, 150, 3),
            self::entry('pattern_reports_6', '6 份模式報告', '累積 6 份 pattern report', self::KIND_MILESTONE, self::TIER_GOLD, 350, 6),

            // === Health 整合 2 個 ===
            self::entry('health_sync_30', '健康整合 30 天', '連續 30 天 Health 同步', self::KIND_MILESTONE, self::TIER_SILVER, 200, 30),

            // === Level 系列 4 個 ===
            self::entry('level_5', '達到 Lv 5', '寵物升到 Lv 5', self::KIND_MILESTONE, self::TIER_BRONZE, 0, 5),
            self::entry('level_10', '達到 Lv 10', '寵物升到 Lv 10', self::KIND_MILESTONE, self::TIER_SILVER, 0, 10),
            self::entry('level_20', '達到 Lv 20', '寵物升到 Lv 20', self::KIND_MILESTONE, self::TIER_GOLD, 0, 20),
            self::entry('level_30', '達到 Lv 30', '寵物升到 Lv 30', self::KIND_MILESTONE, self::TIER_GOLD, 0, 30),

            // === Outfit 收藏 2 個 ===
            self::entry('outfits_5', '收藏 5 件裝扮', '解鎖 5 件不同 outfit', self::KIND_MILESTONE, self::TIER_BRONZE, 50, 5),
            self::entry('outfits_15', '收藏 15 件裝扮', '解鎖 15 件不同 outfit', self::KIND_MILESTONE, self::TIER_SILVER, 200, 15),

            // === 孕期 / 進階週期 2 個 ===
            self::entry('pregnancy_mode_on', '孕期模式啟動', '開啟孕期模式（祝平安）', self::KIND_FIRST, self::TIER_GOLD, 100),
            self::entry('cycle_perfect_4_phases', '完美一輪', '單一週期 4 phase 全部都有記錄', self::KIND_MILESTONE, self::TIER_SILVER, 180),
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
