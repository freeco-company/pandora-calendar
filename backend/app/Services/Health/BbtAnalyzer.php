<?php

namespace App\Services\Health;

use App\Models\BbtReading;
use Carbon\CarbonImmutable;

/**
 * 基礎體溫雙相 shift 偵測（coverline 法）：
 *   - 取最近 28 天 reading
 *   - 前 6 天平均 + 0.1°C 為 coverline
 *   - 連續 3 天高於 coverline 視為 sustained shift（排卵後）
 *   - shift_date = 第 1 天高溫；ovulation_day_estimated = shift_date - 1（觀念上排卵發生在升溫前一天）
 */
class BbtAnalyzer
{
    public const WINDOW_DAYS = 28;
    public const BASELINE_DAYS = 6;
    public const COVERLINE_OFFSET_C = 0.1;
    public const SUSTAIN_DAYS = 3;

    /**
     * @return array{has_shift: bool, shift_date: ?string, ovulation_day_estimated: ?string, coverline: ?float, sample_size: int}
     */
    public function detectBiphasicShift(int $userId, ?CarbonImmutable $today = null): array
    {
        $today ??= CarbonImmutable::today();
        $from = $today->subDays(self::WINDOW_DAYS - 1)->toDateString();

        $rows = BbtReading::query()
            ->where('user_id', $userId)
            ->where('measured_on', '>=', $from)
            ->orderBy('measured_on')
            ->get(['measured_on', 'temperature_c'])
            ->map(fn ($r) => [
                'date' => $r->measured_on instanceof \Carbon\Carbon ? $r->measured_on->toDateString() : (string) $r->measured_on,
                'temp' => (float) $r->temperature_c,
            ])
            ->values();

        $empty = [
            'has_shift' => false,
            'shift_date' => null,
            'ovulation_day_estimated' => null,
            'coverline' => null,
            'sample_size' => $rows->count(),
        ];

        if ($rows->count() < self::BASELINE_DAYS + self::SUSTAIN_DAYS) {
            return $empty;
        }

        // 前 6 天 baseline
        $baseline = $rows->take(self::BASELINE_DAYS)->avg('temp');
        $coverline = round($baseline + self::COVERLINE_OFFSET_C, 2);

        // 從 baseline 之後找連續 3 天 > coverline
        $candidates = $rows->slice(self::BASELINE_DAYS)->values();

        $consecutive = 0;
        $shiftDate = null;
        foreach ($candidates as $row) {
            if ($row['temp'] > $coverline) {
                $consecutive++;
                if ($consecutive === 1) {
                    $shiftDate = $row['date'];
                }
                if ($consecutive >= self::SUSTAIN_DAYS) {
                    return [
                        'has_shift' => true,
                        'shift_date' => $shiftDate,
                        'ovulation_day_estimated' => $shiftDate
                            ? CarbonImmutable::parse($shiftDate)->subDay()->toDateString()
                            : null,
                        'coverline' => $coverline,
                        'sample_size' => $rows->count(),
                    ];
                }
            } else {
                $consecutive = 0;
                $shiftDate = null;
            }
        }

        return [
            'has_shift' => false,
            'shift_date' => null,
            'ovulation_day_estimated' => null,
            'coverline' => $coverline,
            'sample_size' => $rows->count(),
        ];
    }
}
