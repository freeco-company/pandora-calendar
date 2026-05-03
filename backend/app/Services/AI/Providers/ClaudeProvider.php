<?php

namespace App\Services\AI\Providers;

use App\Services\AI\LLMProvider;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Anthropic Messages API provider（claude-haiku-4-5 預設，最便宜）。
 *
 * 失敗策略：log 後 return null。
 */
class ClaudeProvider implements LLMProvider
{
    public function complete(string $systemPrompt, string $userPrompt, array $options = []): ?string
    {
        $apiKey = (string) config('llm.claude.api_key', '');
        if ($apiKey === '') {
            return null;
        }

        $baseUrl = rtrim((string) config('llm.claude.base_url'), '/');
        $model = (string) config('llm.claude.model');
        $timeout = (int) config('llm.timeout_seconds', 8);
        $retries = (int) config('llm.max_retries', 1);

        $payload = [
            'model' => $model,
            'system' => $systemPrompt,
            'max_tokens' => (int) ($options['max_tokens'] ?? 200),
            'temperature' => (float) ($options['temperature'] ?? 0.8),
            'messages' => [
                ['role' => 'user', 'content' => $userPrompt],
            ],
        ];

        try {
            $response = Http::withHeaders([
                'x-api-key' => $apiKey,
                'anthropic-version' => '2023-06-01',
                'content-type' => 'application/json',
            ])
                ->timeout($timeout)
                ->retry($retries, 200, throw: false)
                ->post("{$baseUrl}/messages", $payload);

            if (! $response->successful()) {
                Log::warning('llm.claude.http_error', [
                    'status' => $response->status(),
                    'body' => mb_substr((string) $response->body(), 0, 500),
                ]);

                return null;
            }

            // Anthropic Messages API: content is array of blocks, take first text block
            $blocks = $response->json('content');
            if (! is_array($blocks)) {
                return null;
            }

            foreach ($blocks as $block) {
                if (($block['type'] ?? null) === 'text' && is_string($block['text'] ?? null)) {
                    $text = trim($block['text']);
                    if ($text !== '') {
                        return $text;
                    }
                }
            }

            return null;
        } catch (Throwable $e) {
            Log::warning('llm.claude.exception', ['msg' => $e->getMessage()]);

            return null;
        }
    }

    public function name(): string
    {
        return 'claude';
    }
}
