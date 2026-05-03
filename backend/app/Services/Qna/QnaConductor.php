<?php

namespace App\Services\Qna;

use App\Models\QnaQuestion;
use App\Models\User;
use App\Services\AI\LLMClient;
use App\Services\AI\LLMProvider;
use App\Services\Calendar\BodyRhythmCalculator;
use App\Services\Calendar\CyclePredictor;
use App\Services\Subscription\FeatureGate;
use Illuminate\Support\Carbon;
use Pandora\Shared\Compliance\LegalContentSanitizer;

/**
 * QnaConductor — 朵朵開放問答（P4 含金量功能）
 *
 * 三層守門：
 *   Layer 1  redline filter（自殺 / 自殘 → 立刻給 1925 安心專線、不送 LLM、flag moderator）
 *   Layer 2  LLM call 包系統 prompt + RAG context（5 篇衛教文章 by daily_insights）
 *   Layer 3  輸出過 sanitizer（命中食安 / 健食法紅線 → fallback safe response）
 *
 * 紀律：
 *   - **禁醫療建議**（system prompt 已強制；輸出再 sanitize 一次）
 *   - LLM disabled (provider=null) → 直接 fallback「看 FAQ / 衛教文章」
 *   - 寫 qna_questions table 留 audit trail（含 safety_flag）
 *   - prompt 不送 PII（不傳 email / name），user 識別只用 hash
 */
class QnaConductor
{
    private const ANSWER_MAX_CHARS = 2000;

    /** 自殘 / 自殺 redline 詞（轉介 1925 而非 LLM） */
    private const SELF_HARM_TERMS = [
        '自殺', '想死', '不想活', '自殘', '了結自己', '結束生命', '輕生', '殺死自己',
    ];

    public function __construct(
        private readonly QnaRetriever $retriever,
        private readonly LegalContentSanitizer $sanitizer,
        private readonly FeatureGate $gate,
        private readonly CyclePredictor $predictor,
        private readonly BodyRhythmCalculator $rhythmCalc,
        private readonly ?LLMProvider $provider = null,
    ) {}

    /**
     * @return array{
     *   answer: string,
     *   sources: array<int, int>,
     *   provider: string,
     *   safety_flag: ?string,
     *   record_id: int
     * }
     */
    public function ask(User $user, string $question): array
    {
        $started = microtime(true);

        // ── Layer 1: self-harm redline ────────────────────────────
        if ($this->isSelfHarm($question)) {
            $answer = $this->selfHarmResponse();
            $record = $this->record($user, $question, $answer, [], 'blocked', null, 'redline_self_harm', $started);
            return [
                'answer' => $answer,
                'sources' => [],
                'provider' => 'blocked',
                'safety_flag' => 'redline_self_harm',
                'record_id' => $record->id,
            ];
        }

        // ── Layer 2: RAG retrieve ─────────────────────────────────
        $currentPhase = $this->resolvePhase($user);
        $sources = $this->retriever->retrieve($question, $currentPhase);
        $sourceIds = array_map(fn ($s) => $s['id'], $sources);

        // ── Layer 2b: LLM call ────────────────────────────────────
        $provider = $this->provider ?? LLMClient::make();
        if ($provider->name() === 'null') {
            $answer = trans('qna_offline_fallback_text', [], 'en')
                ?: '我目前不能回答開放問答，但妳可以看看 FAQ 與衛教文章，那裡有很多資訊。';
            $record = $this->record($user, $question, $answer, $sourceIds, 'null', null, null, $started);
            return [
                'answer' => $answer,
                'sources' => $sourceIds,
                'provider' => 'null',
                'safety_flag' => null,
                'record_id' => $record->id,
            ];
        }

        $systemPrompt = $this->buildSystemPrompt();
        $userPrompt = $this->buildUserPrompt($question, $sources);

        $userHash = substr(hash('sha256', (string) $user->id . config('app.key', '')), 0, 32);

        $raw = $provider->complete($systemPrompt, $userPrompt, [
            'max_tokens' => 400,
            'temperature' => 0.5, // 較保守，降低偏題與療效詞
            'user_hash' => $userHash,
        ]);

        $answer = is_string($raw) ? trim($raw) : '';

        // ── Layer 3: 合規 sanitize ────────────────────────────────
        $flag = null;
        if ($answer === '' || mb_strlen($answer) > self::ANSWER_MAX_CHARS) {
            $answer = $this->safeFallback();
            $flag = 'redline_compliance';
        } elseif ($this->sanitizer->riskReport($answer) !== []) {
            $answer = $this->safeFallback();
            $flag = 'redline_compliance';
        }

        $record = $this->record(
            $user, $question, $answer, $sourceIds,
            (string) config('llm.provider', $provider->name()),
            null, $flag, $started,
        );

        return [
            'answer' => $answer,
            'sources' => $sourceIds,
            'provider' => (string) config('llm.provider', $provider->name()),
            'safety_flag' => $flag,
            'record_id' => $record->id,
        ];
    }

    public function dailyRemainingFor(User $user): ?int
    {
        if ($this->gate->isPremium($user)) {
            return null; // unlimited
        }
        $cap = (int) config('qna.free_daily_cap', 3);
        $today = Carbon::today()->toDateString();
        $used = QnaQuestion::query()
            ->where('user_id', $user->id)
            ->whereDate('created_at', $today)
            ->count();
        return max(0, $cap - $used);
    }

    public function hasQuotaToday(User $user): bool
    {
        $remaining = $this->dailyRemainingFor($user);
        return $remaining === null || $remaining > 0;
    }

    private function isSelfHarm(string $q): bool
    {
        $lower = mb_strtolower($q);
        foreach (self::SELF_HARM_TERMS as $term) {
            if (mb_stripos($lower, $term) !== false) {
                return true;
            }
        }
        return false;
    }

    private function selfHarmResponse(): string
    {
        // 不寫療效 / 不假裝醫師 / 直接給專線 + 同理一句
        return "我聽到妳了。妳現在感覺很辛苦，這不是妳的錯。\n\n"
             . "可以撥【1925 安心專線】（24 小時免付費），那裡的人會聽妳說。\n"
             . "也歡迎妳找信任的朋友、家人，或就近的婦產 / 身心科。\n\n"
             . "我會在這裡，等妳回來。";
    }

    private function safeFallback(): string
    {
        return '這個問題我沒辦法給妳專業建議。如果妳有具體的身體不適或情緒困擾，建議諮詢婦產科醫師，'
             . '或撥打衛福部【1925 安心專線】。妳也可以看看 FAQ 與衛教文章，那裡有很多資訊。';
    }

    private function buildSystemPrompt(): string
    {
        // 從外部 .txt 讀（避免在 PHP code 內 embed 違規詞描述）
        $txt = (string) config('qna.system_prompt', '');
        if ($txt !== '') {
            return $txt;
        }

        // fallback minimal prompt（不含任何違規詞 / 醫療建議引導）
        return "妳是潘朵拉月曆的朵朵，回答女性健康問題的陪伴者。\n"
             . "重要原則：\n"
             . "- 妳不是醫師，禁止提供醫療建議與診斷。\n"
             . "- 遇到具體症狀（疼痛、出血、發炎、藥物）一律引導到婦產科。\n"
             . "- 回答 100-200 字，用「妳 / 朋友」口吻。\n"
             . "- 禁止寫療效、改善、緩解、調理、排毒、抗病等違規詞。\n"
             . "- 問題若超出女性健康範圍，禮貌轉移話題。\n"
             . "- 若 context 提供衛教文章，可引用其內容但不可超譯。";
    }

    /** @param array<int, array{id:int, title:string, body:string, phase:string, day_offset:int}> $sources */
    private function buildUserPrompt(string $question, array $sources): string
    {
        $ctx = '';
        if ($sources !== []) {
            $ctx .= "[衛教參考資料]\n";
            foreach ($sources as $idx => $s) {
                $ctx .= sprintf("(%d) %s\n%s\n\n", $idx + 1, $s['title'], $s['body']);
            }
        }

        $ctx .= "[使用者問題]\n" . $question . "\n\n";
        $ctx .= "請以朵朵口吻給她一段陪伴回應（100-200 字）。如有引用上面的參考資料，請自然融入，不要列引用標號。";
        return $ctx;
    }

    private function resolvePhase(User $user): ?string
    {
        try {
            $prediction = $this->predictor->predict($user->id);
            $rhythm = $this->rhythmCalc->compute($prediction);
            $phase = $rhythm->phase;
            return in_array($phase, ['menstrual', 'follicular', 'ovulation', 'luteal'], true) ? $phase : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function record(
        User $user,
        string $question,
        string $answer,
        array $sourceIds,
        string $provider,
        ?int $unused,
        ?string $flag,
        float $startedAt,
    ): QnaQuestion {
        return QnaQuestion::create([
            'user_id' => $user->id,
            'question' => mb_substr($question, 0, 500),
            'answer' => mb_substr($answer, 0, self::ANSWER_MAX_CHARS),
            'sources' => $sourceIds,
            'llm_provider' => $provider,
            'response_time_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            'safety_flag' => $flag,
        ]);
    }
}
