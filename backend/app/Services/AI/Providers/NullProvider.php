<?php

namespace App\Services\AI\Providers;

use App\Services\AI\LLMProvider;

/**
 * 永遠 return null 的 provider — 上層 fallback DodoDialogLibrary。
 * 預設啟用（無 LLM key 時）。
 */
class NullProvider implements LLMProvider
{
    public function complete(string $systemPrompt, string $userPrompt, array $options = []): ?string
    {
        return null;
    }

    public function name(): string
    {
        return 'null';
    }
}
