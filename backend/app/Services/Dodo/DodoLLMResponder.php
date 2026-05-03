<?php

namespace App\Services\Dodo;

use App\Models\DodoCheckin;
use App\Models\User;
use App\Services\AI\LLMClient;
use App\Services\AI\LLMProvider;
use App\Services\Subscription\FeatureGate;
use Illuminate\Support\Facades\Cache;
use Pandora\Shared\Compliance\LegalContentSanitizer;

/**
 * 朵朵 LLM 對白回應器（pluggable，無 key 時 fallback library）。
 *
 * 流程：
 *   1. cap 檢查（per-user daily USD cap，超過 → fallback）
 *   2. 組 system prompt（朵朵語氣導師、用「妳/朋友」、不寫療效、不送 PII）
 *   3. 組 user prompt（mood / phase / cycleDay / streak / 最近 5 個 checkin context）
 *   4. LLM call → 失敗 / 空 / 命中紅線詞 → fallback library
 *   5. 成本累計（粗估 token × price）
 *
 * 隱私：
 *   - prompt 不含 user_id raw、email、name；OpenAI 'user' 欄位送 user_id hash
 *   - 最近 checkin context 只送 mood / phase / cycle_day（無內容文字）
 */
class DodoLLMResponder
{
    public function __construct(
        private readonly DodoDialogLibrary $library,
        private readonly LegalContentSanitizer $sanitizer,
        private readonly FeatureGate $gate,
        private readonly ?LLMProvider $provider = null,
    ) {}

    /**
     * @return array{text: string, source: 'llm'|'library', provider?: string}
     */
    public function respond(
        User $user,
        string $mood,
        string $phase,
        ?int $cycleDay,
        int $streakDays,
        ?int $daysLate = null,
    ): array {
        // 1. 里程碑優先（library 內容已過合規 review）
        $milestone = $this->library->pickStreakMilestone($streakDays);
        if ($milestone !== null) {
            return ['text' => $milestone, 'source' => 'library'];
        }

        // 2. 嘗試 LLM
        $llmText = $this->tryLLM($user, $mood, $phase, $cycleDay, $streakDays, $daysLate);
        if ($llmText !== null) {
            return [
                'text' => $llmText,
                'source' => 'llm',
                'provider' => (string) config('llm.provider', 'null'),
            ];
        }

        // 3. fallback library（mood × phase）
        return [
            'text' => $this->library->pickByMoodPhase($mood, $phase),
            'source' => 'library',
        ];
    }

    private function tryLLM(
        User $user,
        string $mood,
        string $phase,
        ?int $cycleDay,
        int $streakDays,
        ?int $daysLate,
    ): ?string {
        $provider = $this->provider ?? LLMClient::make();

        // NullProvider 早退（不算成本、不打 cache）
        if ($provider->name() === 'null') {
            return null;
        }

        // cap 檢查
        if ($this->isOverDailyCap($user)) {
            return null;
        }

        $systemPrompt = $this->buildSystemPrompt();
        $userPrompt = $this->buildUserPrompt($user, $mood, $phase, $cycleDay, $streakDays, $daysLate);

        // system prompt 自我檢查（防自己寫錯）
        if ($this->sanitizer->riskReport($systemPrompt) !== []) {
            return null;
        }

        $userHash = substr(hash('sha256', (string) $user->id . config('app.key', '')), 0, 32);

        $raw = $provider->complete($systemPrompt, $userPrompt, [
            'max_tokens' => 200,
            'temperature' => 0.8,
            'user_hash' => $userHash,
        ]);

        if ($raw === null || trim($raw) === '') {
            return null;
        }

        $text = trim($raw);

        // 合規檢查 1：本地紅線詞（強硬，從 config/dodo-llm-redlines.php 讀，避免在 service code embed raw 違規詞）
        foreach ((array) config('dodo-llm-redlines.terms', []) as $term) {
            if ($term !== '' && mb_stripos($text, (string) $term) !== false) {
                return null;
            }
        }

        // 合規檢查 2：集團 sanitizer riskReport（55+ 詞）
        if ($this->sanitizer->riskReport($text) !== []) {
            return null;
        }

        // 長度防呆（朵朵對白通常 30-80 字，超過 200 字疑似 LLM hallucinate）
        if (mb_strlen($text) > 200) {
            return null;
        }

        // 累計成本
        $this->accumulateCost($user, $systemPrompt, $userPrompt, $text);

        return $text;
    }

    private function buildSystemPrompt(): string
    {
        // 從外部 .txt 檔讀（避免在 service code 內 embed 描述違規詞的指令）
        return (string) config('dodo-llm-prompt.system', '');
    }

    private function buildUserPrompt(
        User $user,
        string $mood,
        string $phase,
        ?int $cycleDay,
        int $streakDays,
        ?int $daysLate,
    ): string {
        $moodLabel = match ($mood) {
            'good', 'happy' => '心情不錯',
            'okay' => '還可以',
            'tired' => '有點累',
            'bad', 'sad' => '心情低落',
            'cramping' => '身體悶痛',
            default => $mood,
        };

        $phaseLabel = match ($phase) {
            'menstrual' => '經期',
            'follicular' => '濾泡期',
            'ovulation' => '排卵期',
            'luteal' => '黃體期',
            default => '週期未知',
        };

        $context = sprintf(
            "今天的打卡狀態：%s（%s）。週期第 %s 天，連續打卡 %d 天。",
            $moodLabel,
            $phaseLabel,
            $cycleDay !== null ? (string) $cycleDay : '?',
            $streakDays,
        );

        if ($daysLate !== null && $daysLate > 0) {
            $context .= sprintf(' 經期比預測晚了 %d 天。', $daysLate);
        }

        // 最近 5 個 checkin（只送非識別資料）
        $recent = DodoCheckin::query()
            ->where('user_id', $user->id)
            ->orderByDesc('checked_on')
            ->limit(5)
            ->get(['mood', 'phase_at_checkin', 'cycle_day_at_checkin']);

        if ($recent->count() > 1) {
            $context .= "\n最近幾天：";
            foreach ($recent as $c) {
                $context .= sprintf(' [%s/%s]', $c->mood, $c->phase_at_checkin);
            }
        }

        $context .= "\n\n請以朵朵口吻，給她一段陪伴回應（30-80 字）。";

        return $context;
    }

    private function isOverDailyCap(User $user): bool
    {
        $cap = $this->gate->isPremium($user)
            ? (float) config('llm.cost_cap_premium_per_user_per_day_usd', 0.20)
            : (float) config('llm.cost_cap_per_user_per_day_usd', 0.05);

        $key = $this->costCacheKey($user);
        $spent = (float) Cache::get($key, 0.0);

        return $spent >= $cap;
    }

    private function accumulateCost(User $user, string $sys, string $userPrompt, string $output): void
    {
        $provider = (string) config('llm.provider', 'null');
        if ($provider === 'null') {
            return;
        }

        // 粗估 token：中文 ~1.5 char/token、英文 ~4 char/token；保守用 char/2
        $inputTokens = (int) ceil((mb_strlen($sys) + mb_strlen($userPrompt)) / 2);
        $outputTokens = (int) ceil(mb_strlen($output) / 2);

        $priceIn = (float) config("llm.{$provider}.price_per_1k_input_usd", 0);
        $priceOut = (float) config("llm.{$provider}.price_per_1k_output_usd", 0);

        $cost = ($inputTokens / 1000.0) * $priceIn + ($outputTokens / 1000.0) * $priceOut;

        $key = $this->costCacheKey($user);
        $current = (float) Cache::get($key, 0.0);
        // 隔日自動歸零（86400s TTL）
        Cache::put($key, $current + $cost, now()->endOfDay());
    }

    private function costCacheKey(User $user): string
    {
        return sprintf('llm:cost:%d:%s', $user->id, now()->toDateString());
    }
}
