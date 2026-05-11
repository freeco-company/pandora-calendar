<?php

use Pandora\Shared\Compliance\LegalContentSanitizer;

/**
 * 集團合規硬規則 regression guard（CLAUDE.md §7 / docs/group-fp-product-compliance.md）。
 *
 * Calendar 沒有 KB / seed JSON（meal pattern），但 user-facing copy 散落在：
 *   - DodoCheckinResponder 對白（hard-coded）
 *   - 朵朵對白 / 商品連結 / paywall 文案（views / SubscriptionController）
 *
 * 本 test 把所有可能 user-facing 的 PHP / Vue 檔過 sanitizer，掃出違規詞。
 * 違規詞清單見 Pandora\Shared\Compliance\LegalContentSanitizer::REPLACEMENTS。
 */
it('user-facing PHP / Vue source files are clean of forbidden terms', function () {
    $sanitizer = new LegalContentSanitizer;

    $patterns = [
        // 朵朵對白邏輯
        base_path('app/Services/Dodo/**/*.php'),
        // controller 回給前端的文案
        base_path('app/Http/Controllers/**/*.php'),
        // Vue user-facing views
        base_path('../frontend/src/views/**/*.vue'),
        // Vue 共用元件可能含文案
        base_path('../frontend/src/components/**/*.vue'),
    ];

    $files = [];
    foreach ($patterns as $p) {
        $files = array_merge($files, glob($p, GLOB_BRACE) ?: []);
    }

    // 用 recursive glob 取代 ** support
    $rii = function ($dir) {
        $out = [];
        if (! is_dir($dir)) {
            return $out;
        }
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
        foreach ($it as $f) {
            $path = (string) $f;
            if (preg_match('/\.(php|vue)$/', $path)) {
                $out[] = $path;
            }
        }

        return $out;
    };
    $files = array_merge(
        $rii(base_path('app/Services/Dodo')),
        $rii(base_path('app/Http/Controllers')),
        $rii(base_path('../frontend/src/views')),
        $rii(base_path('../frontend/src/components')),
    );
    $files = array_values(array_unique($files));

    expect(count($files))->toBeGreaterThan(0, 'no source files matched — glob broken?');

    /**
     * Strip code comments before risk-report so reverse-list reminders inside
     * comments (e.g. `// 不寫療效詞 / 治療 / 排毒 ...`) don't false-positive.
     * Only what reaches end users matters.
     */
    $stripComments = function (string $content): string {
        // Block comments: /* ... */ (multi-line, including PHP doc blocks)
        $content = preg_replace('#/\*.*?\*/#s', '', $content) ?? $content;
        // Line comments: // ... and # ... (PHP only, end of line)
        $content = preg_replace('#^\s*(?://|\#).*$#m', '', $content) ?? $content;
        $content = preg_replace('#\s+(?://|\#)[^\n]*$#m', '', $content) ?? $content;
        // Vue / HTML comments
        $content = preg_replace('/<!--.*?-->/s', '', $content) ?? $content;

        return $content;
    };

    $offenders = [];
    foreach ($files as $f) {
        $content = $stripComments((string) file_get_contents($f));
        $hits = $sanitizer->riskReport($content);
        if ($hits) {
            $offenders[basename($f)] = array_unique($hits);
        }
    }

    expect($offenders)->toBe(
        [],
        '有違規詞，請過 sanitizer：'.json_encode($offenders, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
    );
});
