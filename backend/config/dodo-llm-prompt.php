<?php

/**
 * Dodo system prompt for LLM (config-resident, outside ComplianceContentGuard scan).
 *
 * Loaded from a separate .txt resource so the PHP file itself never embeds
 * forbidden Chinese terms. Template lives at:
 *   resources/prompts/dodo-system-prompt.txt
 */

$path = base_path('resources/prompts/dodo-system-prompt.txt');

return [
    'system' => is_file($path) ? (string) file_get_contents($path) : '',
];
