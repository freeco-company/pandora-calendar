<?php

namespace App\Services\Community;

/**
 * Auto-moderation for community posts/replies.
 *
 * Two-stage decision:
 *  - BLOCK: removed at write, never visible. Used for compliance red-lines
 *    (food/drug therapeutic claims, MLM/commerce, contact info, URLs).
 *    Why blocking compliance terms hard: 食安 §28 / 健食法 §14 treats
 *    user-generated content on a hosted platform as the platform's content
 *    once the platform has notice (and we have notice — that's why this exists).
 *  - FLAG: published but score > 0.5, surfaces in admin queue. Used for
 *    self-harm signals (we publish to NOT silence cries for help, but flag for
 *    follow-up + auto-attach hotline reply) and grey-zone medical advice.
 *
 * Returns a structured decision so the controller can format friendly errors.
 */
class CommunityModerator
{
    public const ACTION_PUBLISH = 'publish';
    public const ACTION_FLAG = 'flag';
    public const ACTION_BLOCK = 'block';

    /**
     * Compliance red-line terms (subset of pandora-shared canonical list,
     * curated for community UGC context — drops some over-broad medical
     * terms that legitimate users discussing periods would naturally use,
     * keeps therapeutic/efficacy/MLM ones).
     *
     * Source: docs/group-fp-product-compliance.md §紅線清單
     */
    private const FORBIDDEN_TERMS = [
        // Therapeutic / efficacy claims
        '治療', '療效', '治癒', '根治', '痊癒',
        '排毒', '燃脂', '減重', '減脂', '瘦身',
        '抑菌', '消炎', '抗氧化', '抗病', '提升免疫力',
        '取代正餐', '代餐', '低 GI', '低GI', '高纖維', '高蛋白',
        '飽足感', '加速代謝', '排油', '修復肌膚',
        '改善經痛', '緩解經痛', '調理週期', '治經痛',
        // Commerce / MLM red flags in UGC
        '私訊我', '私訊', 'line id', '加賴', '加 line', '加我 line',
        'wechat', '微信', '@line',
        '限時優惠', '限時折扣', '免費試用', '團購價', '優惠碼',
        '加盟', '事業夥伴', '招商', '副業',
        // Honorific that violates集團 voice
        '您',
    ];

    /** Self-harm signals — flag (not block) + auto dodo reply. */
    private const SELF_HARM_TERMS = [
        '自殺', '想死', '不想活', '活不下去', '結束生命',
        '想結束', '撐不下去', '想消失', '了結自己',
    ];

    /** Medical advice patterns — flag for human review. */
    private const MEDICAL_ADVICE_PATTERNS = [
        '/妳應該吃[^。\s]{2,}/u',
        '/你應該吃[^。\s]{2,}/u',
        '/妳要去看[^。\s]{2,}醫師/u',
        '/你要去看[^。\s]{2,}醫師/u',
        '/我建議妳吃[^。\s]{2,}/u',
        '/必須服用/u',
    ];

    /** URL whitelist — government / official health sources only. */
    private const URL_WHITELIST = [
        'mohw.gov.tw',
        '1925.mohw.gov.tw',
        'cdc.gov.tw',
        'fda.gov.tw',
        'hpa.gov.tw',
    ];

    /**
     * @return array{
     *   action: string,
     *   score: float,
     *   reasons: array<int, string>,
     *   matched: array<string, mixed>,
     *   hint: ?string,
     *   needs_dodo_reply: bool
     * }
     */
    public function evaluate(string $title, string $body): array
    {
        $combined = mb_strtolower($title."\n".$body);
        $matched = [];
        $reasons = [];
        $score = 0.0;
        $needsDodoReply = false;

        // 1. Forbidden compliance terms — hard block
        $forbiddenHits = [];
        foreach (self::FORBIDDEN_TERMS as $term) {
            if (mb_stripos($combined, mb_strtolower($term)) !== false) {
                $forbiddenHits[] = $term;
            }
        }
        if ($forbiddenHits !== []) {
            $matched['forbidden_terms'] = $forbiddenHits;
            $reasons[] = 'forbidden_terms';
            $score = 1.0;
        }

        // 2. URLs (non-whitelist) — hard block
        if (preg_match_all('#https?://([^\s/]+)#i', $title."\n".$body, $m)) {
            $bad = [];
            foreach ($m[1] as $host) {
                $host = mb_strtolower($host);
                $isWhite = false;
                foreach (self::URL_WHITELIST as $w) {
                    if ($host === $w || str_ends_with($host, '.'.$w)) {
                        $isWhite = true;
                        break;
                    }
                }
                if (! $isWhite) {
                    $bad[] = $host;
                }
            }
            if ($bad !== []) {
                $matched['urls'] = $bad;
                $reasons[] = 'urls';
                $score = 1.0;
            }
        }

        // 3. Email / phone — hard block (PII / spam vector)
        if (preg_match('/[a-z0-9._%+\-]+@[a-z0-9.\-]+\.[a-z]{2,}/i', $title."\n".$body)) {
            $matched['email'] = true;
            $reasons[] = 'email';
            $score = 1.0;
        }
        // Taiwan mobile (09xx-xxx-xxx) or generic 8+ digit run
        if (preg_match('/09\d{2}[-\s]?\d{3}[-\s]?\d{3}/', $body) ||
            preg_match('/\b\d{8,}\b/', $body)) {
            $matched['phone'] = true;
            $reasons[] = 'phone';
            $score = 1.0;
        }

        // 4. Spam patterns
        // 4a. 3+ consecutive emoji (rough heuristic via Unicode pictograph range)
        if (preg_match('/(\p{So}|\p{Sk}|[\x{1F300}-\x{1FAFF}\x{2600}-\x{27BF}]){3,}/u', $body)) {
            $matched['emoji_spam'] = true;
            $reasons[] = 'emoji_spam';
            $score = max($score, 1.0);
        }
        // 4b. Repeated character runs (10+ same char) — "啊啊啊啊啊..." or "!!!!!!"
        if (preg_match('/(.)\1{9,}/u', $body)) {
            $matched['repeat_spam'] = true;
            $reasons[] = 'repeat_spam';
            $score = max($score, 1.0);
        }
        // 4c. ALL-CAPS English (>= 30 chars uppercase, mostly letters)
        if (preg_match_all('/[A-Z]/', $body, $caps) &&
            count($caps[0]) >= 30 &&
            count($caps[0]) / max(1, mb_strlen($body)) > 0.5) {
            $matched['all_caps'] = true;
            $reasons[] = 'all_caps';
            $score = max($score, 1.0);
        }

        // === If anything above triggered, BLOCK now ===
        if ($score >= 1.0) {
            return [
                'action' => self::ACTION_BLOCK,
                'score' => $score,
                'reasons' => $reasons,
                'matched' => $matched,
                'hint' => $this->buildBlockHint($reasons),
                'needs_dodo_reply' => false,
            ];
        }

        // 5. Self-harm — published + flag + dodo reply
        foreach (self::SELF_HARM_TERMS as $term) {
            if (mb_stripos($combined, $term) !== false) {
                $matched['self_harm'] = $term;
                $reasons[] = 'self_harm';
                $score = max($score, 0.7);
                $needsDodoReply = true;
                break;
            }
        }

        // 6. Medical advice — published + flag
        foreach (self::MEDICAL_ADVICE_PATTERNS as $pat) {
            if (preg_match($pat, $body) || preg_match($pat, $title)) {
                $matched['medical_advice'] = true;
                $reasons[] = 'medical_advice';
                $score = max($score, 0.6);
                break;
            }
        }

        return [
            'action' => $score >= 0.5 ? self::ACTION_FLAG : self::ACTION_PUBLISH,
            'score' => $score,
            'reasons' => $reasons,
            'matched' => $matched,
            'hint' => null,
            'needs_dodo_reply' => $needsDodoReply,
        ];
    }

    public function dodoSelfHarmReply(): string
    {
        return "看到妳的分享，很心疼。不管現在有多沉重，妳不需要一個人扛。\n\n".
            "想找人聊聊嗎？這些專線 24 小時都有人接：\n".
            "・安心專線 1925（依舊愛我）\n".
            "・生命線 1995\n".
            "・張老師專線 1980\n\n".
            '也歡迎告訴朵朵更多，朵朵會一直在這裡。';
    }

    /** @param array<int, string> $reasons */
    private function buildBlockHint(array $reasons): string
    {
        if (in_array('forbidden_terms', $reasons, true)) {
            return '這篇內容可能違反我們的社群規範（例如療效詞、推銷或違規詞）。'.
                '可以試著用「我自己的經驗是 ...」「我覺得 ...」這樣的方式分享，避免醫療效果或商業字眼喔。';
        }
        if (in_array('urls', $reasons, true) || in_array('email', $reasons, true) || in_array('phone', $reasons, true)) {
            return '社群裡先不要分享連結、Email 或電話，避免大家被打擾。如果想推薦資訊，可以用文字描述就好。';
        }
        if (in_array('emoji_spam', $reasons, true) || in_array('repeat_spam', $reasons, true) || in_array('all_caps', $reasons, true)) {
            return '看起來像是 spam 格式，請用一般文字表達妳想說的事。';
        }

        return '這篇內容暫時無法發布，可以先修一下再試試。';
    }
}
