<?php

/**
 * Pet species personality matrix（集團共用 11 species；dodo 是 NPC 不算用戶寵物）。
 *
 * 為什麼放 config：
 * - 前端有自己的常數副本（frontend/src/lib/petPersonality.ts）做 UI 顯示
 * - 後端 PetPersonalityResolver 拿這份做 server-side dialog flavoring
 * - 兩邊 keys 必須一致；任一邊改就要同步另一邊
 *
 * 欄位設計（最小可用）：
 *  personality        — 內部 archetype，driver of dialog tone
 *  reaction_frequency — low / medium / high；前端 idle bounce 頻率 hint
 *  celebration_style  — subtle / warm / energetic / playful；XP / level up SFX 風格
 *  preferred_phase    — 在哪個 cycle phase 最 active（mood enhance hint）
 *  description        — 顯示在 PetOnboardingModal / Profile 寵物卡（必過合規 sanitizer）
 *  tone_keywords      — DodoCheckinResponder 撈句子時的關鍵字偏好
 */

return [
    'cat' => [
        'name' => '貓貓',
        'personality' => 'gentle_observer',
        'reaction_frequency' => 'low',
        'celebration_style' => 'subtle',
        'preferred_phase' => 'luteal',
        'tone_keywords' => ['溫柔', '安靜', '陪伴', '默默'],
        'description' => '安靜陪在妳身邊，不打擾但都看在眼裡。',
    ],
    'rabbit' => [
        'name' => '兔兔',
        'personality' => 'gentle_supporter',
        'reaction_frequency' => 'medium',
        'celebration_style' => 'warm',
        'preferred_phase' => 'follicular',
        'tone_keywords' => ['溫暖', '害羞', '抱抱', '小心翼翼'],
        'description' => '害羞但很想對妳好，會記得妳每個小變化。',
    ],
    'bear' => [
        'name' => '熊熊',
        'personality' => 'warm_hugger',
        'reaction_frequency' => 'medium',
        'celebration_style' => 'warm',
        'preferred_phase' => 'menstrual',
        'tone_keywords' => ['擁抱', '溫暖', '靠著', '別怕'],
        'description' => '大大的擁抱型，妳累了就靠著它。',
    ],
    'penguin' => [
        'name' => '企鵝',
        'personality' => 'calm_thinker',
        'reaction_frequency' => 'low',
        'celebration_style' => 'subtle',
        'preferred_phase' => 'luteal',
        'tone_keywords' => ['冷靜', '理性', '想一想', '慢慢來'],
        'description' => '冷靜不慌張，像妳的理性朋友。',
    ],
    'dog' => [
        'name' => '狗狗',
        'personality' => 'loyal_companion',
        'reaction_frequency' => 'high',
        'celebration_style' => 'playful',
        'preferred_phase' => 'ovulation',
        'tone_keywords' => ['陪伴', '搖尾巴', '一起走', '回家'],
        'description' => '黏人又忠誠，妳走到哪它跟到哪。',
    ],
    'fox' => [
        'name' => '狐狸',
        'personality' => 'curious_clever',
        'reaction_frequency' => 'medium',
        'celebration_style' => 'playful',
        'preferred_phase' => 'follicular',
        'tone_keywords' => ['機靈', '好奇', '小聰明', '眨眨眼'],
        'description' => '機靈又好奇，會發現妳沒注意到的小細節。',
    ],
    'pig' => [
        'name' => '豬豬',
        'personality' => 'cozy_foodie',
        'reaction_frequency' => 'medium',
        'celebration_style' => 'warm',
        'preferred_phase' => 'luteal',
        'tone_keywords' => ['吃飽', '舒服', '懶懶的', '一起睡'],
        'description' => '愛吃愛睡，是最會陪妳放鬆的那一個。',
    ],
    'sheep' => [
        'name' => '羊羊',
        'personality' => 'soft_dreamer',
        'reaction_frequency' => 'low',
        'celebration_style' => 'subtle',
        'preferred_phase' => 'luteal',
        'tone_keywords' => ['柔軟', '夢', '雲', '輕輕'],
        'description' => '柔軟像雲，幫妳把心情撫平。',
    ],
    'dinosaur' => [
        'name' => '小恐龍',
        'personality' => 'wild_supporter',
        'reaction_frequency' => 'high',
        'celebration_style' => 'energetic',
        'preferred_phase' => 'ovulation',
        'tone_keywords' => ['吼', '勇敢', '大步', '衝'],
        'description' => '看起來野，其實只想保護妳。',
    ],
    'tiger' => [
        'name' => '小老虎',
        'personality' => 'bold_protector',
        'reaction_frequency' => 'medium',
        'celebration_style' => 'energetic',
        'preferred_phase' => 'follicular',
        'tone_keywords' => ['勇敢', '撐住', '走', '我在'],
        'description' => '威風但溫柔，為妳擋下小麻煩。',
    ],
    'robot' => [
        'name' => '機器人',
        'personality' => 'precise_logician',
        'reaction_frequency' => 'medium',
        'celebration_style' => 'subtle',
        'preferred_phase' => 'follicular',
        'tone_keywords' => ['資料', '分析', '建議', '節奏'],
        'description' => '把妳的節奏記錄得清清楚楚，溫柔的理性派。',
    ],
];
