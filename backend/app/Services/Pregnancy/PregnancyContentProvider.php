<?php

namespace App\Services\Pregnancy;

/**
 * Reads weekly pregnancy content from config/pregnancy-content.php.
 *
 * Why a service: tests can swap the source; future i18n / per-locale content reads through this.
 */
class PregnancyContentProvider
{
    /**
     * @return array<string, mixed>
     */
    public function forWeek(int $week): array
    {
        $week = max(1, min(42, $week));
        $weeks = config('pregnancy-content.weeks', []);

        // Direct hit
        if (isset($weeks[$week])) {
            return $weeks[$week];
        }

        // Fallback: nearest defined week (linear scan; <= 42 entries so it's fine)
        $best = null;
        $bestDistance = PHP_INT_MAX;
        foreach ($weeks as $w => $entry) {
            $d = abs(((int) $w) - $week);
            if ($d < $bestDistance) {
                $bestDistance = $d;
                $best = $entry;
            }
        }

        return $best ?? [];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function all(): array
    {
        return config('pregnancy-content.weeks', []);
    }
}
