<?php

namespace App\Services\AI;

/**
 * LLM provider 抽象介面（pluggable）
 *
 * 設計原則：
 *   - env-driven：config('llm.provider') 切 openai / claude / null
 *   - 失敗永遠 return null，讓上層 fallback（dodo → DodoDialogLibrary、qna → safe response）
 *   - 不擋上架（無 key → NullProvider → fallback）
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
