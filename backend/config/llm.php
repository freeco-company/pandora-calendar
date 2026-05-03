<?php

/**
 * 朵朵 LLM provider 配置（pluggable env-driven）
 *
 * 預設 provider=null（用 DodoDialogLibrary fallback）；上架不阻塞。
 * 切 openai：LLM_PROVIDER=openai + OPENAI_API_KEY
 * 切 claude：LLM_PROVIDER=claude + ANTHROPIC_API_KEY
 *
 * 成本：
 *   - gpt-4o-mini ≈ $0.0002/次對白
 *   - claude-haiku-4-5 ≈ $0.0003/次對白
 *   - per-user daily cap $0.05 = ~250 次/日
 */

return [
    // null | openai | claude
    'provider' => env('LLM_PROVIDER', 'null'),

    // 0 = 不 cache（朵朵對白本來就要新鮮感，預設關）
    'cache_ttl_seconds' => (int) env('LLM_CACHE_TTL', 0),

    // HTTP timeout / retry
    'timeout_seconds' => (int) env('LLM_TIMEOUT', 8),
    'max_retries' => (int) env('LLM_MAX_RETRIES', 1),

    // 成本控制（per-user per-day USD cap，超過 fallback library）
    'cost_cap_per_user_per_day_usd' => (float) env('LLM_USER_DAILY_CAP', 0.05),
    'cost_cap_premium_per_user_per_day_usd' => (float) env('LLM_PREMIUM_USER_DAILY_CAP', 0.20),

    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
        'model' => env('OPENAI_MODEL', 'gpt-4o-mini'),
        'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
        // 粗估每 1K input/output token USD（gpt-4o-mini 2026 價）
        'price_per_1k_input_usd' => (float) env('OPENAI_PRICE_INPUT', 0.00015),
        'price_per_1k_output_usd' => (float) env('OPENAI_PRICE_OUTPUT', 0.0006),
    ],

    'claude' => [
        'api_key' => env('ANTHROPIC_API_KEY'),
        'model' => env('CLAUDE_MODEL', 'claude-haiku-4-5'),
        'base_url' => env('ANTHROPIC_BASE_URL', 'https://api.anthropic.com/v1'),
        'price_per_1k_input_usd' => (float) env('CLAUDE_PRICE_INPUT', 0.001),
        'price_per_1k_output_usd' => (float) env('CLAUDE_PRICE_OUTPUT', 0.005),
    ],
];
