<?php

/**
 * 含金量 Q&A（P4）配置 — 朵朵 LLM + RAG 衛教文章
 */

$promptPath = base_path('resources/prompts/qna-system-prompt.txt');

return [
    // free tier 每日問答上限（Premium 無限）
    'free_daily_cap' => (int) env('QNA_FREE_DAILY_CAP', 3),

    // system prompt（外部 .txt，避免 PHP file 內 embed 違規詞描述觸發 ContentGuard）
    'system_prompt' => is_file($promptPath) ? (string) file_get_contents($promptPath) : '',
];
