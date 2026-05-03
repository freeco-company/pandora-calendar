<?php

namespace App\Services\Pet;

/**
 * 把 species + event context 翻成 species-flavored celebration / encouragement 字串。
 *
 * 設計：
 * - matrix（config/pet-species.php）只給 framework / tone keywords
 * - dialog pool 內建 minimal sample（每 personality × event 1-3 句）
 * - 之後 narrative agent 補完整 species × event matrix 時，往這裡 append 即可
 *
 * 與 DodoDialogLibrary 的差異：
 * - DodoDialogLibrary 是 NPC「朵朵」的口吻（mentor）
 * - 這裡是「用戶寵物」的口吻（companion，跟 species 個性走）
 *
 * 合規：所有句子必過食安法 / 健食法（不寫療效詞）；後續若整 sanitizer 在這 wrap。
 */
class PetPersonalityResolver
{
    public const EVENTS = [
        'action_completed',
        'streak_3',
        'streak_7',
        'streak_30',
        'level_up',
        'achievement_unlocked',
        'cycle_logged',
        'phase_match', // pet 在自己 preferred_phase 時的問候
    ];

    /**
     * @param  string  $species         e.g. 'cat'
     * @param  string  $event           one of self::EVENTS
     * @param  array   $context         optional: ['streak' => 7, 'level' => 5, 'phase' => 'luteal']
     * @return string  spoken-style sentence；未知 event / species fallback 到通用句
     */
    public function resolve(string $species, string $event, array $context = []): string
    {
        $personality = $this->personalityOf($species);
        $pool = $this->dialogPool()[$personality][$event] ?? null;

        if ($pool === null) {
            // event 沒專屬句 → 通用 fallback（依 personality 取一句）
            $pool = $this->fallbackPool()[$personality] ?? ['好棒！'];
        }

        $template = $pool[array_rand($pool)];

        return $this->fillContext($template, $context);
    }

    public function meta(string $species): array
    {
        return config('pet-species.'.$species, []);
    }

    private function personalityOf(string $species): string
    {
        return config('pet-species.'.$species.'.personality', 'gentle_supporter');
    }

    private function fillContext(string $tpl, array $ctx): string
    {
        foreach ($ctx as $k => $v) {
            $tpl = str_replace('{'.$k.'}', (string) $v, $tpl);
        }

        return $tpl;
    }

    /**
     * 起手：每 personality × 4 個熱門 event 各 2-3 句 sample。
     * 之後 narrative agent 擴充。
     */
    private function dialogPool(): array
    {
        return [
            'gentle_observer' => [
                'action_completed' => [
                    '看到妳完成了，靜靜地給妳一個讚。',
                    '我都看在眼裡，妳辛苦了。',
                ],
                'streak_3' => [
                    '連續 3 天了，妳比想像中堅定。',
                    '妳一直記著，這份心意我感覺得到。',
                ],
                'level_up' => [
                    '我們默默又前進了一階，恭喜。',
                ],
            ],
            'gentle_supporter' => [
                'action_completed' => [
                    '抱抱妳，今天又往前一點點了。',
                    '小事也是大事，妳真的好棒。',
                ],
                'streak_3' => [
                    '連續 3 天記錄了，給妳一個小擁抱 🤗',
                ],
                'level_up' => [
                    '我們一起升級啦，繼續這樣陪妳！',
                ],
            ],
            'energetic_cheerleader' => [
                'action_completed' => [
                    '太棒啦！我為妳跳一下！',
                    '完成了完成了，撒花撒花～',
                ],
                'streak_3' => [
                    '3 天連勝！妳超猛的！',
                ],
                'streak_7' => [
                    '一整週耶！繼續衝呀～',
                ],
                'level_up' => [
                    '升級啦！我幫妳開趴！🎉',
                ],
            ],
            'warm_hugger' => [
                'action_completed' => [
                    '來，靠過來一下，妳很認真。',
                    '今天也好好照顧妳自己了，我抱抱妳。',
                ],
                'streak_3' => [
                    '連續記錄了 3 天，我以妳為榮。',
                ],
                'level_up' => [
                    '又長大一點點，給妳一個大擁抱。',
                ],
            ],
            'calm_thinker' => [
                'action_completed' => [
                    '紀錄存好了，這會讓未來的妳更了解自己。',
                    '一步一步來，妳走得很穩。',
                ],
                'streak_3' => [
                    '3 天不中斷，這是妳的節奏建立期。',
                ],
                'level_up' => [
                    '經驗值滿了，我們進到下一階段。',
                ],
            ],
            'loyal_companion' => [
                'action_completed' => [
                    '搖尾巴！跟妳一起完成超開心。',
                ],
                'streak_3' => [
                    '我陪妳 3 天了，明天也算我一份！',
                ],
                'level_up' => [
                    '一起升級！我們是最佳搭檔。',
                ],
            ],
            'curious_clever' => [
                'action_completed' => [
                    '小細節我有記下來，眨眨眼。',
                    '妳今天有點不一樣，我喜歡。',
                ],
                'streak_3' => [
                    '3 天了，我發現妳開始有自己的節奏。',
                ],
                'level_up' => [
                    '咦，等級悄悄漲了，被我發現！',
                ],
            ],
            'cozy_foodie' => [
                'action_completed' => [
                    '完成啦～來休息一下吧，我陪妳。',
                ],
                'streak_3' => [
                    '3 天耶，今天可以好好放鬆一下。',
                ],
                'level_up' => [
                    '升級了，慶祝方式：耍廢一下下。',
                ],
            ],
            'soft_dreamer' => [
                'action_completed' => [
                    '輕輕地給妳一朵雲，今天辛苦了。',
                ],
                'streak_3' => [
                    '連 3 天了，像一首溫柔的歌。',
                ],
                'level_up' => [
                    '升級了，像夢一樣輕輕地。',
                ],
            ],
            'wild_supporter' => [
                'action_completed' => [
                    '吼！完成了！',
                    '妳超勇的，我為妳大叫一聲！',
                ],
                'streak_3' => [
                    '連 3 天，氣勢出來了！',
                ],
                'level_up' => [
                    '升級！我們大步往前！',
                ],
            ],
            'bold_protector' => [
                'action_completed' => [
                    '完成了，我守在這裡。',
                ],
                'streak_3' => [
                    '3 天了，繼續保持，我看著。',
                ],
                'level_up' => [
                    '升級了，我們一起更強。',
                ],
            ],
            'precise_logician' => [
                'action_completed' => [
                    '已記錄。妳的節奏正在被建立。',
                ],
                'streak_3' => [
                    '3 / 連續資料點建立。預測準確度 +。',
                ],
                'level_up' => [
                    'Level {level} 達成。下個目標：Level {next}。',
                ],
            ],
        ];
    }

    private function fallbackPool(): array
    {
        return [
            'gentle_observer' => ['做得很好。', '我都看在眼裡。'],
            'gentle_supporter' => ['給妳抱抱。', '妳真的很棒。'],
            'energetic_cheerleader' => ['太棒啦！', '繼續衝呀～'],
            'warm_hugger' => ['抱抱妳。', '辛苦了。'],
            'calm_thinker' => ['節奏不錯。', '繼續這樣很好。'],
            'loyal_companion' => ['我陪妳！'],
            'curious_clever' => ['有點不一樣，我喜歡。'],
            'cozy_foodie' => ['一起放鬆吧～'],
            'soft_dreamer' => ['輕輕的，給妳。'],
            'wild_supporter' => ['吼！'],
            'bold_protector' => ['我守著。'],
            'precise_logician' => ['已記錄。'],
        ];
    }
}
