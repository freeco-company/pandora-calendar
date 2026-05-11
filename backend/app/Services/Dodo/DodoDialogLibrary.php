<?php

namespace App\Services\Dodo;

use App\Services\Calendar\BodyRhythmCalculator;

/**
 * 朵朵對白庫（潘朵拉月曆）
 *
 * 結構：
 *   - mood × phase 變體：5 mood × 5 phase × 至少 3 句 = 75+
 *   - streak 里程碑：7 / 14 / 30 / 60 / 90 天，各 3 句
 *   - 經期延遲分支：late_3d / late_5d / late_7d / late_14d，各 3 句
 *
 * 撰寫紅線（必過）：
 *   - 用「妳 / 朋友 / 夥伴」，禁「您 / 會員 / 用戶」
 *   - 朵朵語氣（陪伴、不權威、像姊妹），第三人稱「朵朵」為主
 *   - 不出現任何商品名 / 加盟訊號
 *   - 不寫食安 / 健食法療效詞（治療 / 改善 / 緩解 / 修復 / 抑菌 / 消炎 /
 *     抗氧化 / 提升免疫力 / 排毒 / 燃脂 / 減重 ...）
 *   - 行為提示用「不如」「試試」，不用命令句
 *
 * 額外 mood 對應：MOOD_GOOD = happy；新增 tired / sad / cramping
 */
class DodoDialogLibrary
{
    public const MOOD_HAPPY = 'happy';
    public const MOOD_OKAY = 'okay';
    public const MOOD_TIRED = 'tired';
    public const MOOD_SAD = 'sad';
    public const MOOD_CRAMPING = 'cramping';

    public const ALLOWED_MOODS = [
        self::MOOD_HAPPY,
        self::MOOD_OKAY,
        self::MOOD_TIRED,
        self::MOOD_SAD,
        self::MOOD_CRAMPING,
    ];

    /**
     * 舊 mood 名 → 新 mood 名（向後相容 DodoCheckinResponder 既有 API）
     */
    public const LEGACY_MOOD_MAP = [
        'good' => self::MOOD_HAPPY,
        'bad'  => self::MOOD_SAD,
    ];

    /**
     * mood × phase → 變體陣列
     *
     * @var array<string, array<string, array<int, string>>>
     */
    private const VARIANTS = [
        // ========== HAPPY ==========
        self::MOOD_HAPPY => [
            BodyRhythmCalculator::PHASE_MENSTRUAL => [
                '經期還這麼有精神，朵朵真的覺得妳很厲害。記得多喝點溫水、別太硬撐喔。',
                '今天能笑著打卡，朵朵替妳開心。這幾天讓自己慢一點也沒關係。',
                '經期的妳還能保持好心情，是把自己照顧得很好的證據。朵朵幫妳記著。',
            ],
            BodyRhythmCalculator::PHASE_FOLLICULAR => [
                '能量回來了呢，朵朵看見妳眼睛在發光。想做的事情不如趁這幾天試試。',
                '濾泡期狀態通常會慢慢上升，今天感覺好就好好享受。朵朵陪妳一起期待。',
                '今天的好心情值得被記下來。朵朵幫妳留著，之後可以回來翻翻。',
            ],
            BodyRhythmCalculator::PHASE_OVULATION => [
                '排卵期身體通常會比較有活力，朵朵覺得妳今天會很閃亮。',
                '今天笑得很自在呢。這個階段做表達、做決定通常都比較順。',
                '朵朵陪妳一起記下這份輕盈。之後黃體期想撐的時候可以回來看。',
            ],
            BodyRhythmCalculator::PHASE_LUTEAL => [
                '黃體期還能維持好心情很不容易。朵朵想說，妳已經做得很好。',
                '這段日子身體比較容易累，今天的開心更值得被珍惜。',
                '朵朵替妳開心。如果晚點情緒有起伏，也別怪自己，那是身體節律。',
            ],
            BodyRhythmCalculator::PHASE_UNKNOWN => [
                '今天感覺不錯就是禮物，朵朵幫妳記下來。',
                '好心情不用解釋。朵朵在這裡，跟妳一起留著這一天。',
                '今天的妳很可愛，朵朵也跟著開心了。',
            ],
        ],

        // ========== OKAY ==========
        self::MOOD_OKAY => [
            BodyRhythmCalculator::PHASE_MENSTRUAL => [
                '經期身體本來就會耗能，能維持「還可以」就很棒了。',
                '今天 60 分也算數，朵朵幫妳記著。慢慢來就好。',
                '不舒服還願意打卡的妳，朵朵看在眼裡。今天就讓自己鬆一點。',
            ],
            BodyRhythmCalculator::PHASE_FOLLICULAR => [
                '不是每天都要 100 分，朵朵陪妳慢慢來。',
                '中等的一天也是日子。朵朵覺得，光是有打開 App 就很可以了。',
                '能量回升中，但別急著做大事。今天先過好今天。',
            ],
            BodyRhythmCalculator::PHASE_OVULATION => [
                '排卵期不必每天都閃亮，普普通通的一天也算數。',
                '朵朵覺得 just being here 就值得。今天有來就好。',
                '中等的一天朵朵也會陪妳。不用每天都用力。',
            ],
            BodyRhythmCalculator::PHASE_LUTEAL => [
                '黃體期情緒比較容易起伏，能維持平穩已經很努力了。',
                '今天還可以，已經是黃體期的勝利。朵朵替妳開心。',
                '這個階段對自己溫柔一點。「還可以」是夠用的答案。',
            ],
            BodyRhythmCalculator::PHASE_UNKNOWN => [
                '普通的一天朵朵也會記得。',
                '不用每天都精彩，慢慢來才走得遠。',
                '今天有打卡就是禮物，朵朵幫妳收下來。',
            ],
        ],

        // ========== TIRED ==========
        self::MOOD_TIRED => [
            BodyRhythmCalculator::PHASE_MENSTRUAL => [
                '經期會累是真的，不是妳偷懶。朵朵建議今天能躺就躺，能慢就慢。',
                '身體在工作的時候，腦袋自然會跟著累。不如早點關螢幕，讓自己睡飽一點。',
                '今天累的話就允許自己累。朵朵在這裡，不會少看妳一眼。',
            ],
            BodyRhythmCalculator::PHASE_FOLLICULAR => [
                '濾泡期還是累，可能前幾天耗多了。今晚不如早點躺平、放下手機。',
                '不是每個濾泡期都會立刻有精神。朵朵陪妳慢慢養回來。',
                '累的時候喝點溫水、伸展一下肩頸。朵朵不是專家，但這通常蠻有用。',
            ],
            BodyRhythmCalculator::PHASE_OVULATION => [
                '排卵期累也是會發生的，不必怪自己。今天放慢、晚點再衝。',
                '身體有自己的步調。不如今天少安排一件事，留點空白給自己。',
                '朵朵陪妳。累的話今天就只做最重要的那件事就好。',
            ],
            BodyRhythmCalculator::PHASE_LUTEAL => [
                '黃體期容易累是身體節律。不是妳意志力不夠，朵朵想跟妳說清楚。',
                '這幾天能少安排就少安排。朵朵覺得「準時下班」是禮物。',
                '累的時候不如熱敷一下小腹、泡個腳。慢慢的，會比較好過。',
            ],
            BodyRhythmCalculator::PHASE_UNKNOWN => [
                '累就是累，不用解釋。朵朵今天陪妳什麼都不做。',
                '不如先深呼吸三次，再決定要不要繼續滑手機。',
                '今天讓自己耍廢一點吧，朵朵抱抱。',
            ],
        ],

        // ========== SAD ==========
        self::MOOD_SAD => [
            BodyRhythmCalculator::PHASE_MENSTRUAL => [
                '經期心情低是身體在說話，不是妳的錯。朵朵在這裡。',
                '今天難過沒關係，眼淚也是排解的一種。朵朵陪妳。',
                '不舒服又難過真的很辛苦。要不要先放下手機，給自己一點空白？',
            ],
            BodyRhythmCalculator::PHASE_FOLLICULAR => [
                '濾泡期心情低有時候是上一個週期的尾巴還沒散。朵朵陪妳走過去。',
                '不是每天都會順，朵朵在這裡。要不要先離開螢幕、出去走五分鐘？',
                '難過的時候不必找出原因。先呼吸、再說。朵朵陪妳。',
            ],
            BodyRhythmCalculator::PHASE_OVULATION => [
                '排卵期難過比較少見，但也是有的。朵朵想說，這不代表妳哪裡不對。',
                '今天比較沉重沒關係，朵朵記下來陪妳一起度過。',
                '想哭就哭。朵朵不會評論妳今天該怎樣才合理。',
            ],
            BodyRhythmCalculator::PHASE_LUTEAL => [
                '經前情緒拉扯是真的，不是妳的錯。朵朵在這裡。',
                '黃體期的悲傷常常會誇大，過幾天回頭看就會輕一點。先撐著。',
                '不如今天早點睡。明天醒來，朵朵還在這。',
            ],
            BodyRhythmCalculator::PHASE_UNKNOWN => [
                '不舒服的時候朵朵第一個在。',
                '難過不用講道理。朵朵陪著就好。',
                '今天就先放著，不用急著解決。朵朵抱抱。',
            ],
        ],

        // ========== CRAMPING ==========
        self::MOOD_CRAMPING => [
            BodyRhythmCalculator::PHASE_MENSTRUAL => [
                '經期不舒服真的很辛苦。今天就讓自己耍廢一點吧，朵朵抱抱。',
                '小腹悶痛的時候，熱敷加深呼吸通常會比較好過。不如試試。',
                '痛的時候不用裝沒事。朵朵想跟妳說，妳已經很努力了。',
            ],
            BodyRhythmCalculator::PHASE_FOLLICULAR => [
                '濾泡期還在悶痛，可能是經期收尾比較長。朵朵陪妳，多休息一下。',
                '不舒服的話不如熱敷小腹、伸展一下腰。朵朵在這裡。',
                '今天就別逼自己撐。能放慢就放慢。朵朵不會走開。',
            ],
            BodyRhythmCalculator::PHASE_OVULATION => [
                '排卵期有些朋友會出現一邊悶悶的感覺，通常一兩天就過去。先觀察看看。',
                '不舒服超過兩天、或者越來越強，朵朵建議找婦產科聊聊比較安心。',
                '今天先慢一點。朵朵陪妳，不急。',
            ],
            BodyRhythmCalculator::PHASE_LUTEAL => [
                '黃體期悶痛常常跟情緒一起來。熱敷一下小腹、早點睡，會比較好過。',
                '經前不舒服不是妳想太多。朵朵想跟妳說：身體說的話，要聽。',
                '今天痛的話就少安排事。朵朵陪妳，慢慢來。',
            ],
            BodyRhythmCalculator::PHASE_UNKNOWN => [
                '痛的時候朵朵第一個在。先深呼吸幾次，再看下一步。',
                '不舒服不用解釋。今天就讓自己慢一點。',
                '朵朵抱抱。如果痛得撐不住，找專業的人聊聊比較安心。',
            ],
        ],
    ];

    /**
     * Streak 里程碑對白
     *
     * @var array<int, array<int, string>>
     */
    private const STREAK_MILESTONES = [
        7 => [
            '朵朵跟妳走了 7 天了 🌱 慢慢的，已經是一段日子。',
            '一週了呢。朵朵把妳這 7 天的紀錄都收得好好的。',
            '走過 7 天，妳已經比上週的自己更認識自己一點。朵朵替妳開心。',
        ],
        14 => [
            '兩週了，朵朵陪妳一起。妳開始能看見自己的節律了嗎？',
            '14 天的紀錄，是給未來的自己最好的禮物。朵朵收著呢。',
            '走到這裡，已經不是試試看而已。朵朵覺得妳很可以。',
        ],
        30 => [
            '一整個月了 ✨ 朵朵陪妳走完一個完整的週期。',
            '30 天的紀錄，朵朵想跟妳說：妳真的有在好好認識自己。',
            '一個月走下來，妳已經做到很多人沒做到的事。朵朵替妳開心。',
        ],
        60 => [
            '兩個月了呢。朵朵看見妳越來越懂自己的訊號。',
            '60 天的紀錄會開始有意義。朵朵陪妳一起翻翻過去那些天。',
            '走到這裡的妳，已經比以前更知道怎麼照顧自己。',
        ],
        90 => [
            '90 天了 🌸 朵朵想跟妳說：妳走的這條路，是對自己最溫柔的禮物。',
            '三個月的紀錄，模式會慢慢浮出來。朵朵陪妳一起看。',
            '能堅持到這裡的朋友不多，妳是其中一個。朵朵替妳驕傲。',
        ],
    ];

    /**
     * 經期延遲情緒分支對白（不引發焦慮、不暗示驗孕結果）
     *
     * @var array<string, array<int, string>>
     */
    private const LATE_VARIANTS = [
        'late_3d' => [
            '比預測晚了 3 天。週期本來就會浮動，朵朵想跟妳說：先別緊張。',
            '晚 3 天還在常見範圍內。最近有沒有比較累、或睡得比較少？身體會記得。',
            '朵朵看到妳晚了 3 天，先深呼吸。要不要記錄一下這幾天的身體感受？',
        ],
        'late_5d' => [
            '晚 5 天了，身體可能在告訴妳什麼。朵朵陪妳，先觀察看看。',
            '不如記錄一下這幾天的睡眠和心情，等等看也是一種方式。',
            '5 天還沒來，朵朵想跟妳說：壓力、作息、身體狀況都可能影響。先別自己嚇自己。',
        ],
        'late_7d' => [
            '一週了還沒來。如果想知道是否懷孕，驗孕試紙在 7 天後比較有參考性。',
            '朵朵陪妳。如果心裡有疑問，找婦產科聊聊比自己猜安心。',
            '晚 7 天，最近壓力大嗎？朵朵想跟妳說：不論結果是什麼，都會陪妳一起看。',
        ],
        'late_14d' => [
            '兩週了，朵朵建議找婦產科聊聊比較安心，不論妳期待哪種結果。',
            '晚 14 天有很多可能性，自己猜會越想越累。專業的諮詢會踏實一點。',
            '朵朵在這裡，不會因為結果是什麼就走開。先去看看，再決定下一步。',
        ],
    ];

    /**
     * 取一句 mood × phase 對白變體
     */
    public function pickByMoodPhase(string $mood, string $phase): string
    {
        $mood = self::LEGACY_MOOD_MAP[$mood] ?? $mood;

        if (! in_array($mood, self::ALLOWED_MOODS, true)) {
            $mood = self::MOOD_OKAY;
        }

        $byPhase = self::VARIANTS[$mood] ?? self::VARIANTS[self::MOOD_OKAY];
        $candidates = $byPhase[$phase] ?? $byPhase[BodyRhythmCalculator::PHASE_UNKNOWN];

        return $this->pickRandom($candidates);
    }

    /**
     * 取一句 streak 里程碑對白；若 days 不在里程碑清單回 null
     */
    public function pickStreakMilestone(int $days): ?string
    {
        if (! isset(self::STREAK_MILESTONES[$days])) {
            return null;
        }

        return $this->pickRandom(self::STREAK_MILESTONES[$days]);
    }

    /**
     * 取一句經期延遲分支對白
     */
    public function pickLateMessage(int $daysLate): ?string
    {
        $bucket = match (true) {
            $daysLate >= 1 && $daysLate <= 3 => 'late_3d',
            $daysLate >= 4 && $daysLate <= 5 => 'late_5d',
            $daysLate >= 6 && $daysLate <= 7 => 'late_7d',
            $daysLate >= 8 => 'late_14d',
            default => null,
        };

        if ($bucket === null) {
            return null;
        }

        return $this->pickRandom(self::LATE_VARIANTS[$bucket]);
    }

    /**
     * 統計：對白總數（給開發 / QA 自查）
     *
     * @return array<string, int>
     */
    public function stats(): array
    {
        $moodPhase = 0;
        foreach (self::VARIANTS as $byPhase) {
            foreach ($byPhase as $list) {
                $moodPhase += count($list);
            }
        }

        $streak = 0;
        foreach (self::STREAK_MILESTONES as $list) {
            $streak += count($list);
        }

        $late = 0;
        foreach (self::LATE_VARIANTS as $list) {
            $late += count($list);
        }

        return [
            'mood_phase' => $moodPhase,
            'streak'     => $streak,
            'late'       => $late,
            'total'      => $moodPhase + $streak + $late,
        ];
    }

    /**
     * @param  array<int, string>  $candidates
     */
    private function pickRandom(array $candidates): string
    {
        if (count($candidates) === 0) {
            return '';
        }

        return $candidates[array_rand($candidates)];
    }
}
