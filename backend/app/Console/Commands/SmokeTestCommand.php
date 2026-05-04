<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * Smoke Test (Layer F — Production Probe)
 *
 * Why: post-deploy sanity check. curl public endpoints, fail loud if anything
 * 4xx/5xx. Does NOT replace e2e — just answers "did the deploy not break the
 * front door?"
 *
 * Usage:
 *   php artisan smoke:test
 *   php artisan smoke:test --base-url=https://calendar-api.js-store.com.tw
 *
 * Exits non-zero on any failure → can be wired into deploy script.
 */
class SmokeTestCommand extends Command
{
    protected $signature = 'smoke:test {--base-url=http://127.0.0.1:8000} {--timeout=10}';

    protected $description = 'Probe key public endpoints and verify they respond 200 with expected content-type';

    /**
     * Each probe: [path, method, expected_status, expected_content_type_substring]
     * 200 here means "alive", not "happy" — auth-gated endpoints aren't probed.
     */
    private array $probes = [
        ['/api/v1/faq', 'GET', 200, 'application/json'],
        ['/', 'GET', 200, 'text/html'],
        ['/admin/login', 'GET', 200, 'text/html'],
    ];

    public function handle(): int
    {
        $baseUrl = rtrim((string) $this->option('base-url'), '/');
        $timeout = (int) $this->option('timeout');
        $failures = 0;

        $this->info("Smoke test → {$baseUrl}");

        foreach ($this->probes as [$path, $method, $expectStatus, $expectCt]) {
            $url = $baseUrl . $path;
            try {
                $response = Http::timeout($timeout)->withoutVerifying()->{strtolower($method)}($url);
                $status = $response->status();
                $ct = $response->header('content-type') ?? '';

                if ($status !== $expectStatus) {
                    $this->error("  FAIL {$method} {$path} — got HTTP {$status}, expected {$expectStatus}");
                    $failures++;

                    continue;
                }

                if (! str_contains($ct, $expectCt)) {
                    $this->error("  FAIL {$method} {$path} — content-type `{$ct}` lacks `{$expectCt}`");
                    $failures++;

                    continue;
                }

                $this->line("  OK   {$method} {$path} — {$status} {$ct}");
            } catch (\Throwable $e) {
                $this->error("  FAIL {$method} {$path} — exception: " . $e->getMessage());
                $failures++;
            }
        }

        if ($failures > 0) {
            $this->error("Smoke test failed: {$failures} probe(s) failed.");

            return self::FAILURE;
        }

        $this->info('All probes passed.');

        return self::SUCCESS;
    }
}
