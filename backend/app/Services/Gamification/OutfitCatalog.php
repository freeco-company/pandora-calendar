<?php

namespace App\Services\Gamification;

/**
 * P3 — calendar 寵物 outfit 解鎖規則 catalog（hard-coded）。
 *
 * 2026-05-03 擴充：12 → 36 outfits（對齊 meal 規模 + 加 calendar 專屬 phase / 節氣 / pregnancy）
 *
 * outfit codes 對齊 frontend lib/character.ts OUTFITS（同一份 SVG overlay 用）。
 *
 * 解鎖路徑：
 *   - level：寵物等級 ≥ N
 *   - streak：連勝 ≥ N 天
 *   - achievement：完成 achievement_key
 *   - premium：訂閱 premium tier
 *   - season：當前月份在指定區間 [from..to]（1-12，含端點）
 *   - cycles：完整週期數 ≥ N（懷孕 / 長期使用獎勵）
 *
 * 集團共用 outfit overlay SVG 在 public/character/outfits/。
 * 新加 outfit 必補對應 SVG，否則 frontend 顯示 broken image。
 */
final class OutfitCatalog
{
    public const RARITY_COMMON = 'common';

    public const RARITY_RARE = 'rare';

    public const RARITY_EPIC = 'epic';

    public const RARITY_LEGENDARY = 'legendary';

    /**
     * @return list<array{
     *   code:string, name:string, hint:string, rarity:string, icon:string,
     *   unlock:array{type:string, value:mixed}
     * }>
     */
    public static function all(): array
    {
        return [
            // === 起手禮（人人有，等級 1）===
            self::entry('ribbon', '蝴蝶結', '陪伴妳的第一個小裝飾', self::RARITY_COMMON, '🎀', 'level', 1),

            // === Level 解鎖階梯 9 件 ===
            self::entry('straw_hat', '夏日草帽', '寵物 Lv 3 解鎖', self::RARITY_COMMON, '👒', 'level', 3),
            self::entry('flower_crown', '花環', '寵物 Lv 4 解鎖', self::RARITY_COMMON, '🌷', 'level', 4),
            self::entry('sunglasses', '酷酷墨鏡', '寵物 Lv 5 解鎖', self::RARITY_RARE, '🕶️', 'level', 5),
            self::entry('headband', '運動髮帶', '寵物 Lv 6 解鎖', self::RARITY_RARE, '🎽', 'level', 6),
            self::entry('chef_apron', '小廚師圍裙', '寵物 Lv 8 解鎖', self::RARITY_RARE, '👨‍🍳', 'level', 8),
            self::entry('winter_scarf', '冬日圍巾', '寵物 Lv 10 解鎖', self::RARITY_RARE, '🧣', 'level', 10),
            self::entry('graduate_cap', '畢業帽', '寵物 Lv 12 解鎖', self::RARITY_EPIC, '🎓', 'level', 12),
            self::entry('detective_hat', '偵探帽', '寵物 Lv 15 解鎖', self::RARITY_EPIC, '🕵️', 'level', 15),
            self::entry('rainbow_cape', '彩虹披風', '寵物 Lv 25 解鎖', self::RARITY_EPIC, '🌈', 'level', 25),

            // === Streak 解鎖 5 件 ===
            self::entry('sparkle_pin', '閃亮髮夾', '連勝 3 天解鎖', self::RARITY_COMMON, '✨', 'streak', 3),
            self::entry('sakura', '櫻花瓣', '連勝 7 天解鎖', self::RARITY_RARE, '🌸', 'streak', 7),
            self::entry('star_clip', '星星髮夾', '連勝 14 天解鎖', self::RARITY_RARE, '⭐', 'streak', 14),
            self::entry('starry_cape', '星光斗篷', '連勝 30 天解鎖', self::RARITY_EPIC, '⭐', 'streak', 30),
            self::entry('moon_tiara', '月光冠冕', '連勝 60 天解鎖', self::RARITY_EPIC, '🌙', 'streak', 60),
            self::entry('angel_wings', '天使翅膀', '連勝 90 天解鎖', self::RARITY_LEGENDARY, '👼', 'streak', 90),

            // === Achievement 解鎖 5 件 ===
            self::entry('witch_hat', '小巫女帽', '解開「初次見朵朵」', self::RARITY_RARE, '🧙', 'achievement', 'first_dodo_checkin'),
            self::entry('thermometer_charm', '溫度計掛飾', '解開「第一次量基礎體溫」', self::RARITY_RARE, '🌡️', 'achievement', 'first_bbt'),
            self::entry('pearl_necklace', '珍珠項鍊', '解開「記錄 3 個週期」', self::RARITY_EPIC, '🦪', 'achievement', 'cycles_3'),
            self::entry('sage_robe', '智者長袍', '解開「記錄 12 個週期」', self::RARITY_LEGENDARY, '🪷', 'achievement', 'cycles_12'),
            self::entry('partner_bracelet', '伴侶手鍊', '解開「勇敢分享」', self::RARITY_EPIC, '💍', 'achievement', 'first_partner_share'),

            // === 季節限定 4 件（節氣對應，月份範圍）===
            self::entry('cherry_blossom_kimono', '櫻花和服', '春天限定（3-4 月）', self::RARITY_EPIC, '🌸', 'season', [3, 4]),
            self::entry('summer_yukata', '夏日浴衣', '夏天限定（6-8 月）', self::RARITY_EPIC, '🎐', 'season', [6, 8]),
            self::entry('autumn_maple', '楓葉披肩', '秋天限定（10-11 月）', self::RARITY_EPIC, '🍁', 'season', [10, 11]),
            self::entry('lunar_new_year', '新春紅披風', '農曆新年限定（1-2 月）', self::RARITY_LEGENDARY, '🧧', 'season', [1, 2]),
            self::entry('mid_autumn', '中秋兔耳', '中秋限定（9 月）', self::RARITY_LEGENDARY, '🌕', 'season', [9, 9]),

            // === 週期里程碑解鎖（cycles count）3 件 ===
            self::entry('moon_phase_charm', '月相吊飾', '完成 6 個週期記錄', self::RARITY_EPIC, '🌗', 'cycles', 6),
            self::entry('zodiac_robe', '黃道十二宮袍', '完成 12 個週期記錄', self::RARITY_LEGENDARY, '♈', 'cycles', 12),

            // === Premium 限定 5 件 ===
            self::entry('fp_crown', 'FP 皇冠', 'Premium 訂閱專屬', self::RARITY_LEGENDARY, '👑', 'premium', true),
            self::entry('fp_apron_premium', 'FP 限定圍裙', 'Premium 訂閱專屬', self::RARITY_EPIC, '✨', 'premium', true),
            self::entry('fp_chef', 'FP 主廚帽', 'Premium 訂閱專屬', self::RARITY_EPIC, '🎩', 'premium', true),
            self::entry('fp_scarf_silk', 'FP 絲絨圍巾', 'Premium 訂閱專屬', self::RARITY_EPIC, '🧣', 'premium', true),
            self::entry('fp_diamond_collar', 'FP 鑽石項圈', 'Premium 訂閱專屬', self::RARITY_LEGENDARY, '💎', 'premium', true),
        ];
    }

    /**
     * @param  array<string,mixed>  $context  ['level' => int, 'streak' => int, 'achievements' => string[],
     *                                          'is_premium' => bool, 'cycles' => int, 'month' => int (1-12)]
     * @return list<string> 解鎖的 outfit code
     */
    public static function unlockedFor(array $context): array
    {
        $owned = [];
        foreach (self::all() as $o) {
            if (self::isUnlocked($o, $context)) {
                $owned[] = $o['code'];
            }
        }

        return $owned;
    }

    /**
     * @param  array{code:string, unlock:array{type:string, value:mixed}}  $outfit
     * @param  array<string,mixed>  $context
     */
    public static function isUnlocked(array $outfit, array $context): bool
    {
        $u = $outfit['unlock'];

        return match ($u['type']) {
            'level' => ((int) ($context['level'] ?? 0)) >= ((int) $u['value']),
            'streak' => ((int) ($context['streak'] ?? 0)) >= ((int) $u['value']),
            'achievement' => in_array((string) $u['value'], (array) ($context['achievements'] ?? []), true),
            'premium' => (bool) ($context['is_premium'] ?? false) === ((bool) $u['value']),
            'cycles' => ((int) ($context['cycles'] ?? 0)) >= ((int) $u['value']),
            'season' => self::isSeasonActive($u['value'], (int) ($context['month'] ?? (int) date('n'))),
            default => false,
        };
    }

    /**
     * 月份區間判定。value = [from, to]（含端點，1-12）。
     * 跨年（例如 [11, 2]）支援：to < from 時 wrap。
     *
     * @param  mixed  $value
     */
    private static function isSeasonActive(mixed $value, int $month): bool
    {
        if (! is_array($value) || count($value) !== 2) {
            return false;
        }
        [$from, $to] = [(int) $value[0], (int) $value[1]];
        if ($from <= $to) {
            return $month >= $from && $month <= $to;
        }
        // wrap year-end (e.g. [11, 2] = Nov-Dec + Jan-Feb)
        return $month >= $from || $month <= $to;
    }

    /**
     * @return array{
     *   code:string, name:string, hint:string, rarity:string, icon:string,
     *   unlock:array{type:string, value:mixed}
     * }
     */
    private static function entry(string $code, string $name, string $hint, string $rarity, string $icon, string $type, mixed $value): array
    {
        return [
            'code' => $code,
            'name' => $name,
            'hint' => $hint,
            'rarity' => $rarity,
            'icon' => $icon,
            'unlock' => ['type' => $type, 'value' => $value],
        ];
    }
}
