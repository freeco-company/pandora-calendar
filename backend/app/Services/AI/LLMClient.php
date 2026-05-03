<?php

namespace App\Services\AI;

use App\Services\AI\Providers\ClaudeProvider;
use App\Services\AI\Providers\NullProvider;
use App\Services\AI\Providers\OpenAIProvider;

/**
 * LLM provider 抽象介面 + factory
 *
 * 設計原則：
 *   - env-driven：config('llm.provider') 切 openai / claude / null
 *   - 失敗永遠 return null，讓上層 fallback 到 DodoDialogLibrary
 *   - 不擋上架（無 key → NullProvider → library）
 */
interface LLMProvider
{
    /**
     * @param  array<string, mixed>  $options
     *   max_tokens (int), temperature (float), user_hash (string for OpenAI 反濫用)
     */
    public function complete(string $systemPrompt, string $userPrompt, array $options = []): ?string;

    /** Provider 名稱（log / debug 用） */
    public function name(): string;
}

class LLMClient
{
    /**
     * 從 config 切 provider 實例。
     *
     * 注意：每次呼叫都 new 一個（stateless），不做 singleton — 方便測試 mock。
     */
    public static function make(): LLMProvider
    {
        $provider = (string) config('llm.provider', 'null');

        return match ($provider) {
            'openai' => new OpenAIProvider(),
            'claude' => new ClaudeProvider(),
            default => new NullProvider(),
        };
    }
}
