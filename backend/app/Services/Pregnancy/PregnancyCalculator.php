<?php

namespace App\Services\Pregnancy;

use App\Models\Pregnancy;
use Carbon\CarbonImmutable;

/**
 * Pregnancy week / trimester / due-date math.
 *
 * Convention: gestational age (GA) counted from LMP (last menstrual period), week 1 = first 7 days post-LMP.
 *   - Trimester 1: weeks 1-13
 *   - Trimester 2: weeks 14-27
 *   - Trimester 3: weeks 28-42
 *
 * Why not Carbon\Carbon: CarbonImmutable everywhere — calendar math should never accidentally mutate input.
 */
class PregnancyCalculator
{
    public function __construct(private readonly PregnancyContentProvider $content) {}

    public function getCurrentWeek(Pregnancy $state, ?CarbonImmutable $now = null): int
    {
        $now ??= CarbonImmutable::now();
        $lmp = CarbonImmutable::parse($state->lmp_date);
        $days = (int) floor(abs($lmp->diffInDays($now)));

        return max(1, min(42, (int) floor($days / 7) + 1));
    }

    public function getTrimester(int $week): int
    {
        if ($week <= 13) {
            return 1;
        }
        if ($week <= 27) {
            return 2;
        }

        return 3;
    }

    public function getDaysUntilDue(Pregnancy $state, ?CarbonImmutable $now = null): int
    {
        $now ??= CarbonImmutable::now();
        $due = CarbonImmutable::parse($state->estimated_due_date);

        return (int) floor($due->diffInDays($now, false) * -1);
    }

    /**
     * @return array{label: string, emoji: string}
     */
    public function getFetalSize(int $week): array
    {
        $entry = $this->content->forWeek($week);

        return [
            'label' => $entry['size_comparison'] ?? '一顆豆子',
            'emoji' => $entry['size_emoji'] ?? '🫘',
        ];
    }

    /**
     * @return array{week: int, trimester: int, dodo_message: string, suggested_actions: array<int, array{key: string, label: string}>}
     */
    public function getThisWeekHighlights(int $week): array
    {
        $entry = $this->content->forWeek($week);

        return [
            'week' => $week,
            'trimester' => $this->getTrimester($week),
            'dodo_message' => $entry['dodo_message'] ?? '',
            'suggested_actions' => $entry['suggested_actions'] ?? [],
        ];
    }

    /**
     * One-shot summary used by /pregnancy/current — what the FE actually renders.
     *
     * @return array<string, mixed>
     */
    public function summarize(Pregnancy $state, ?CarbonImmutable $now = null): array
    {
        $week = $this->getCurrentWeek($state, $now);
        $trimester = $this->getTrimester($week);

        return [
            'id' => $state->id,
            'lmp_date' => $state->lmp_date->toDateString(),
            'estimated_due_date' => $state->estimated_due_date->toDateString(),
            'mode_started_at' => optional($state->mode_started_at)->toIso8601String(),
            'status' => $state->status,
            'gestational_week' => $week,
            'trimester' => $trimester,
            'days_until_due' => $this->getDaysUntilDue($state, $now),
            'fetal_size' => $this->getFetalSize($week),
            'this_week' => $this->getThisWeekHighlights($week),
        ];
    }
}
