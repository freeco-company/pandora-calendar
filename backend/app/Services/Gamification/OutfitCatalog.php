<?php

namespace App\Services\Gamification;

/**
 * P3 — calendar 寵物 outfit 解鎖規則 catalog（hard-coded）。
 *
 * outfit codes 對齊 frontend lib/character.ts OUTFITS（同一份 SVG overlay 用）。
 *
 * 解鎖路徑：
 *   - 等級門檻（Lv N）
 *   - 連勝門檻（streak N 天）
 *   - 達成 achievement（解了某個 achievement_key 順手送 outfit）
 *   - Premium 限定（subscription_tier=premium）
 *   - 季節限定（節氣 / 月份，未來補）
 *
 * 集團共用 outfit overlay SVG 在 public/character/outfits/。
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
            // --- 起手 ---
            self::entry('ribbon', '蝴蝶結', '陪伴妳的第一個小裝飾', self::RARITY_COMMON, '🎀', 'level', 1),

            // --- Level 解鎖 ---
            self::entry('straw_hat', '夏日草帽', '寵物 Lv 3 解鎖', self::RARITY_COMMON, '👒', 'level', 3),
            self::entry('sunglasses', '酷酷墨鏡', '寵物 Lv 5 解鎖', self::RARITY_RARE, '🕶️', 'level', 5),
            self::entry('chef_apron', '小廚師圍裙', '寵物 Lv 8 解鎖', self::RARITY_RARE, '👨‍🍳', 'level', 8),
            self::entry('winter_scarf', '冬日圍巾', '寵物 Lv 10 解鎖', self::RARITY_RARE, '🧣', 'level', 10),

            // --- Streak 解鎖 ---
            self::entry('sakura', '櫻花瓣', '連勝 7 天解鎖', self::RARITY_RARE, '🌸', 'streak', 7),
            self::entry('starry_cape', '星光斗篷', '連勝 30 天解鎖', self::RARITY_EPIC, '⭐', 'streak', 30),
            self::entry('angel_wings', '天使翅膀', '連勝 90 天解鎖', self::RARITY_LEGENDARY, '👼', 'streak', 90),

            // --- Achievement 解鎖 ---
            self::entry('witch_hat', '小巫女帽', '解開「初次見朵朵」', self::RARITY_RARE, '🧙', 'achievement', 'first_dodo_checkin'),

            // --- Premium 限定 ---
            self::entry('fp_crown', 'FP 皇冠', 'Premium 訂閱專屬', self::RARITY_LEGENDARY, '👑', 'premium', true),
            self::entry('fp_apron_premium', 'FP 限定圍裙', 'Premium 訂閱專屬', self::RARITY_EPIC, '✨', 'premium', true),
            self::entry('fp_chef', 'FP 主廚帽', 'Premium 訂閱專屬', self::RARITY_EPIC, '🎩', 'premium', true),
        ];
    }

    /**
     * @param  array<string,mixed>  $context  ['level' => int, 'streak' => int, 'achievements' => string[], 'is_premium' => bool]
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
            default => false,
        };
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
