<?php

namespace App\Services\AI\Providers;

use App\Services\AI\LLMProvider;
use App\Support\Sentry\SentryHelper;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * OpenAI Chat Completions provider。
 *
 * 失敗策略：log 後 return null（不擋上層 fallback）。
 * Timeout / retry 從 config('llm') 讀。
 *
 * 隱私：
 *   - 不 leak PII，由呼叫端負責；prompt 只送 mood / phase / cycle_day / streak 等非識別資料
 *   - options['user_hash'] 走 'user' 欄位給 OpenAI 反濫用，傳 hash 而非 user_id raw
 */
class OpenAIProvider implements LLMProvider
{
    public function complete(string $systemPrompt, string $userPrompt, array $options = []): ?string
    {
        $apiKey = (string) config('llm.openai.api_key', '');
        if ($apiKey === '') {
            return null;
        }

        $baseUrl = rtrim((string) config('llm.openai.base_url'), '/');
        $model = (string) config('llm.openai.model');
        $timeout = (int) config('llm.timeout_seconds', 8);
        $retries = (int) config('llm.max_retries', 1);

        $payload = [
            'model' => $model,
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $userPrompt],
            ],
            'max_tokens' => (int) ($options['max_tokens'] ?? 200),
            'temperature' => (float) ($options['temperature'] ?? 0.8),
        ];

        if (! empty($options['user_hash'])) {
            $payload['user'] = (string) $options['user_hash'];
        }

        try {
            $response = Http::withToken($apiKey)
                ->timeout($timeout)
                ->retry($retries, 200, throw: false)
                ->post("{$baseUrl}/chat/completions", $payload);

            if (! $response->successful()) {
                Log::warning('llm.openai.http_error', [
                    'status' => $response->status(),
                    'body' => mb_substr((string) $response->body(), 0, 500),
                ]);

                $status = $response->status();
                if ($status >= 500) {
                    // 5xx = unexpected upstream, capture
                    SentryHelper::captureMessage(
                        "OpenAI http {$status}",
                        'warning',
                        'llm',
                        ['provider' => 'openai', 'status' => $status, 'model' => $model]
                    );
                } else {
                    // 4xx = predictable (rate limit / auth / bad payload) → breadcrumb only
                    SentryHelper::addBreadcrumb('llm.fail', 'openai http error', [
                        'provider' => 'openai',
                        'status' => $status,
                        'model' => $model,
                    ]);
                }

                return null;
            }

            $text = $response->json('choices.0.message.content');

            if (! is_string($text) || trim($text) === '') {
                // 200 but unparseable → unexpected
                SentryHelper::captureMessage(
                    'OpenAI 200 but empty / unparseable content',
                    'warning',
                    'llm',
                    ['provider' => 'openai', 'model' => $model]
                );

                return null;
            }

            return trim($text);
        } catch (Throwable $e) {
            Log::warning('llm.openai.exception', ['msg' => $e->getMessage()]);
            SentryHelper::captureException($e, 'llm', [
                'provider' => 'openai',
                'model' => $model,
            ]);

            return null;
        }
    }

    public function name(): string
    {
        return 'openai';
    }
}
