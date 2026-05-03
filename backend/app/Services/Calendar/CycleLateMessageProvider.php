<?php

namespace App\Services\Calendar;

/**
 * 經期延遲情緒文案
 *
 * 設計原則：
 *   - 中性、不引發焦慮、不假設妳的期待方向
 *   - 不下醫療判斷、不暗示驗孕結果
 *   - 行為建議用「不如」「試試」「找婦產科聊聊比較安心」
 *   - 過合規 sanitizer 紅線（不寫治療 / 緩解 / 改善 / 修復 / 排毒）
 *
 * 區段：
 *   1-3d / 4-7d / 8-14d / 15d+，每段 3 變體
 *
 * 使用：
 *   $provider->getMessage($daysLate, $intent)  // intent: 'wants_pregnancy' | 'avoiding_pregnancy' | 'unknown'
 *
 * intent 目前先預設 unknown 文案；未來可分支。先提供同一份中性文案，
 * 不假設用戶想要哪種結果。
 */
class CycleLateMessageProvider
{
    public const INTENT_WANTS_PREGNANCY = 'wants_pregnancy';
    public const INTENT_AVOIDING_PREGNANCY = 'avoiding_pregnancy';
    public const INTENT_UNKNOWN = 'unknown';

    /**
     * @var array<string, array<int, array{title: string, body: string, suggestion: string}>>
     */
    private const SEGMENTS = [
        // ========== 1-3 天 ==========
        '1_3' => [
            [
                'title'      => '晚了幾天，朵朵想跟妳說：先別緊張',
                'body'       => '週期本來就會浮動 ±5 天，特別是最近壓力大、睡得少、運動量改變、季節轉換，身體都會記得。晚 1-3 天還在常見的範圍內。',
                'suggestion' => '不如記錄一下這幾天的睡眠和心情，朵朵幫妳一起觀察。',
            ],
            [
                'title'      => '比預測晚了一點點',
                'body'       => '不是每個週期都會跟上次一樣準。最近作息有變化嗎？身體會用週期把這些訊號告訴妳。',
                'suggestion' => '今天先深呼吸，等等看再說。朵朵在這裡。',
            ],
            [
                'title'      => '晚 1-3 天，慢慢觀察',
                'body'       => '週期浮動是常見的，不一定代表什麼。朵朵想跟妳說：先別自己嚇自己。',
                'suggestion' => '不如記錄一下今天的身體感受（脹、累、心情⋯）給未來的自己看。',
            ],
        ],

        // ========== 4-7 天 ==========
        '4_7' => [
            [
                'title'      => '晚了一週左右',
                'body'       => '4-7 天的延遲，可能是壓力、作息、體重變化、旅行時差累積出來的。也可能是身體準備進入下一個階段的訊號。',
                'suggestion' => '如果想知道是否懷孕，驗孕試紙在月經晚 7 天後比較有參考性。朵朵在這裡。',
            ],
            [
                'title'      => '朵朵陪妳一起等',
                'body'       => '晚 5 天左右，身體可能在告訴妳一些事。可以是疲累、可以是壓力、可以是其他。先不要急著結論。',
                'suggestion' => '不如把這幾天的睡眠、心情、飲食稍微記下來，往後翻會看到模式。',
            ],
            [
                'title'      => '晚一週還沒來',
                'body'       => '這個區間最容易胡思亂想。朵朵想跟妳說：可能性有很多種，自己猜會越想越累。',
                'suggestion' => '想驗孕的話 7 天後試紙比較準。心裡有疑問，找婦產科聊聊也踏實。',
            ],
        ],

        // ========== 8-14 天 ==========
        '8_14' => [
            [
                'title'      => '已經晚一週多了',
                'body'       => '到這個區間，身體一定在說什麼。可能是懷孕、可能是荷爾蒙波動、可能是壓力或體重變化。每一種都是身體的訊號。',
                'suggestion' => '不如先驗孕試紙看看（這時間點準確度比較高）。不論結果是什麼，朵朵都陪妳。',
            ],
            [
                'title'      => '朵朵想跟妳說：先深呼吸',
                'body'       => '晚 10 天左右，自己猜真的會越想越累。專業的諮詢會讓妳踏實一點。',
                'suggestion' => '婦產科可以做超音波和抽血，比試紙更明確。要不要先預約一下？',
            ],
            [
                'title'      => '到這裡，找專業的人聊聊比較安心',
                'body'       => '不論妳期待哪種結果，超過一週的延遲值得被認真看待。朵朵不是醫生，能陪妳的有限。',
                'suggestion' => '不如先預約婦產科。朵朵想跟妳說：這不是大事化小，是好好照顧自己。',
            ],
        ],

        // ========== 15+ 天 ==========
        '15_plus' => [
            [
                'title'      => '晚兩週以上了',
                'body'       => '到這裡，朵朵建議找婦產科聊聊比較安心。可能是懷孕、可能是內分泌、可能是其他需要被看見的訊號。',
                'suggestion' => '預約婦產科看診。朵朵會在這裡，等妳回來告訴朵朵下一步。',
            ],
            [
                'title'      => '朵朵想跟妳說：請去看醫生',
                'body'       => '兩週以上的延遲不該自己猜。婦產科的檢查通常很快、不會讓妳難堪，朵朵保證。',
                'suggestion' => '今天先預約看看。不論結果是什麼，朵朵都不會走開。',
            ],
            [
                'title'      => '不論結果是什麼，朵朵都會陪妳',
                'body'       => '晚到這個程度，自己撐著是辛苦的。婦產科是夥伴，不是審判。',
                'suggestion' => '預約一下，去聊聊。回來再告訴朵朵妳的感受。',
            ],
        ],
    ];

    /**
     * @return array{title: string, body: string, suggestion: string}|null
     */
    public function getMessage(int $daysLate, string $intent = self::INTENT_UNKNOWN): ?array
    {
        if ($daysLate <= 0) {
            return null;
        }

        $bucket = match (true) {
            $daysLate >= 1 && $daysLate <= 3   => '1_3',
            $daysLate >= 4 && $daysLate <= 7   => '4_7',
            $daysLate >= 8 && $daysLate <= 14  => '8_14',
            $daysLate >= 15                    => '15_plus',
            default                            => null,
        };

        if ($bucket === null) {
            return null;
        }

        $variants = self::SEGMENTS[$bucket];

        return $variants[array_rand($variants)];
    }
}
