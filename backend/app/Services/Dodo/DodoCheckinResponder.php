<?php

namespace App\Services\Dodo;

use App\Services\Calendar\BodyRhythm;
use App\Services\Calendar\BodyRhythmCalculator;

class DodoCheckinResponder
{
    public const MOOD_GOOD = 'good';
    public const MOOD_OKAY = 'okay';
    public const MOOD_BAD = 'bad';

    public const ALLOWED_MOODS = [self::MOOD_GOOD, self::MOOD_OKAY, self::MOOD_BAD];

    /**
     * Hard-coded mood × phase 對白 mapping. Phase 0：不接 LLM。
     * 全部對白：朵朵語氣（陪伴 / 朋友 / 妳），且不踩食安/健食法療效詞。
     */
    private const RESPONSES = [
        self::MOOD_GOOD => [
            BodyRhythmCalculator::PHASE_MENSTRUAL => '經期還這麼有精神，朵朵很佩服妳。記得多喝溫水、別太累喔。',
            BodyRhythmCalculator::PHASE_FOLLICULAR => '能量回來了！這個階段特別適合計畫新事情，朵朵陪妳一起期待。',
            BodyRhythmCalculator::PHASE_OVULATION => '排卵期狀態通常最好，朵朵覺得妳今天會發光。',
            BodyRhythmCalculator::PHASE_LUTEAL => '黃體期還能保持好心情很厲害，記得不要對自己太嚴格。',
            BodyRhythmCalculator::PHASE_UNKNOWN => '今天感覺不錯就是禮物，朵朵幫妳記下來。',
        ],
        self::MOOD_OKAY => [
            BodyRhythmCalculator::PHASE_MENSTRUAL => '經期身體本來就會耗能，能維持「還可以」就很棒了。',
            BodyRhythmCalculator::PHASE_FOLLICULAR => '不是每天都要 100 分，朵朵陪妳慢慢來。',
            BodyRhythmCalculator::PHASE_OVULATION => '中等的一天也算數，朵朵覺得 just being here 就值得。',
            BodyRhythmCalculator::PHASE_LUTEAL => '黃體期情緒比較容易起伏，能維持平穩已經很努力了。',
            BodyRhythmCalculator::PHASE_UNKNOWN => '普通的一天朵朵也會記得。',
        ],
        self::MOOD_BAD => [
            BodyRhythmCalculator::PHASE_MENSTRUAL => '經期不舒服真的很辛苦。今天就讓自己耍廢一點吧，朵朵抱抱。',
            BodyRhythmCalculator::PHASE_FOLLICULAR => '不是每天都會順，朵朵在這裡。要不要先離開螢幕走走？',
            BodyRhythmCalculator::PHASE_OVULATION => '今天比較沉重沒關係，朵朵記下來陪妳一起度過。',
            BodyRhythmCalculator::PHASE_LUTEAL => '經前情緒拉扯是真的，不是妳的錯。朵朵在這裡。',
            BodyRhythmCalculator::PHASE_UNKNOWN => '不舒服的時候朵朵第一個在。',
        ],
    ];

    public function respond(string $mood, BodyRhythm $rhythm): string
    {
        if (! in_array($mood, self::ALLOWED_MOODS, true)) {
            $mood = self::MOOD_OKAY;
        }

        return self::RESPONSES[$mood][$rhythm->phase] ?? self::RESPONSES[$mood][BodyRhythmCalculator::PHASE_UNKNOWN];
    }
}
