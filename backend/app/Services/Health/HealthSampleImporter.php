<?php

namespace App\Services\Health;

use App\Models\BbtReading;
use App\Models\Cycle;
use App\Models\HealthSample;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * HealthKit / Health Connect 匯入。
 *
 * Capacitor 端用 capacitor-health plugin 把 sample 撈出來，POST 到本 API；
 * server 端做 dedupe + 寫進對應 domain table（BBT → bbt_readings、cycle 起始 → cycles）
 * 並同步存一份到 health_samples 作 raw audit log（後續 AI / week report 取用）。
 *
 * 支援 kind:
 *  - bbt              → bbt_readings (dedupe by user_id + measured_on)
 *  - steps            → health_samples (metric=steps)
 *  - sleep            → health_samples (metric=sleep_hours)
 *  - menstrual_flow   → cycles (auto start new cycle when first flow date follows ≥7d gap)
 */
class HealthSampleImporter
{
    public const SUPPORTED_KINDS = ['bbt', 'steps', 'sleep', 'menstrual_flow'];

    /**
     * 新版 typed import（前端 useHealthKit composable 呼叫）。
     *
     * @param  array<int, array{date?:string, datetime?:string, value:float|int, unit?:string, meta?:array}>  $samples
     * @return array{imported:int, duplicates:int, errors:array<int,string>}
     */
    public function import(int $userId, string $kind, array $samples, string $source = 'healthkit'): array
    {
        if (! in_array($kind, self::SUPPORTED_KINDS, true)) {
            return ['imported' => 0, 'duplicates' => 0, 'errors' => ["unsupported_kind:{$kind}"]];
        }

        $imported = 0;
        $duplicates = 0;
        $errors = [];

        DB::transaction(function () use ($userId, $kind, $samples, $source, &$imported, &$duplicates, &$errors) {
            foreach ($samples as $idx => $raw) {
                try {
                    $date = $this->resolveDate($raw);
                    if (! $date) {
                        $errors[] = "row_{$idx}:missing_date";

                        continue;
                    }
                    $value = isset($raw['value']) ? (float) $raw['value'] : null;
                    if ($value === null) {
                        $errors[] = "row_{$idx}:missing_value";

                        continue;
                    }

                    $metric = $this->mapMetric($kind);
                    if ($metric) {
                        $existing = HealthSample::where('user_id', $userId)
                            ->where('source', $source)
                            ->where('metric', $metric)
                            ->where('recorded_on', $date)
                            ->exists();
                        if ($existing) {
                            $duplicates++;
                        }
                        HealthSample::updateOrCreate(
                            [
                                'user_id' => $userId,
                                'source' => $source,
                                'metric' => $metric,
                                'recorded_on' => $date,
                            ],
                            [
                                'value' => $value,
                                'recorded_at' => $raw['datetime'] ?? null,
                                'meta' => array_filter([
                                    'unit' => $raw['unit'] ?? null,
                                    ...($raw['meta'] ?? []),
                                ]),
                            ],
                        );
                        if (! $existing) {
                            $imported++;
                        }
                    }

                    if ($kind === 'bbt') {
                        $bbt = BbtReading::where('user_id', $userId)->whereDate('measured_on', $date)->first();
                        if ($bbt) {
                            $bbt->update(['temperature_c' => $value, 'note' => 'auto: HealthKit / Health Connect']);
                        } else {
                            BbtReading::create([
                                'user_id' => $userId,
                                'measured_on' => $date,
                                'temperature_c' => $value,
                                'note' => 'auto: HealthKit / Health Connect',
                            ]);
                        }
                    } elseif ($kind === 'menstrual_flow' && $value > 0) {
                        $this->upsertCycleStart($userId, $date);
                    }
                } catch (\Throwable $e) {
                    $errors[] = "row_{$idx}:{$e->getMessage()}";
                }
            }
        });

        return [
            'imported' => $imported,
            'duplicates' => $duplicates,
            'errors' => $errors,
        ];
    }

    /**
     * Backwards-compatible legacy bulk method（保留給舊 controller path / 既有測試）。
     *
     * @param  array<int, array<string,mixed>>  $samples
     */
    public function importBatch(User $user, array $samples, string $source = 'healthkit'): int
    {
        $count = 0;
        foreach ($samples as $s) {
            $metric = $s['metric'] ?? null;
            $value = $s['value'] ?? null;
            $recordedOn = $s['recorded_on'] ?? null;
            if (! $metric || $value === null || ! $recordedOn) {
                continue;
            }

            HealthSample::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'source' => $source,
                    'metric' => $metric,
                    'recorded_on' => $recordedOn,
                ],
                [
                    'value' => $value,
                    'recorded_at' => $s['recorded_at'] ?? null,
                    'meta' => $s['meta'] ?? null,
                ],
            );
            $count++;
        }

        return $count;
    }

    private function resolveDate(array $raw): ?string
    {
        $candidate = $raw['date'] ?? ($raw['datetime'] ?? ($raw['recorded_on'] ?? null));
        if (! $candidate) {
            return null;
        }
        try {
            return CarbonImmutable::parse($candidate)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    private function mapMetric(string $kind): ?string
    {
        return match ($kind) {
            'bbt' => HealthSample::METRIC_BASAL_TEMP,
            'steps' => HealthSample::METRIC_STEPS,
            'sleep' => HealthSample::METRIC_SLEEP_HOURS,
            default => null,
        };
    }

    /**
     * 若這天已落在某 cycle 區間 → skip；否則新建一筆 cycle（以 7 天 gap 為新週期門檻）。
     */
    private function upsertCycleStart(int $userId, string $date): void
    {
        $latest = Cycle::where('user_id', $userId)->orderByDesc('start_date')->first();
        if ($latest) {
            $start = $latest->start_date->toDateString();
            $end = $latest->end_date?->toDateString();
            if ($end && $date <= $end) {
                return;
            }
            if (abs(CarbonImmutable::parse($date)->diffInDays(CarbonImmutable::parse($start))) < 7) {
                return;
            }
        }
        Cycle::firstOrCreate(
            ['user_id' => $userId, 'start_date' => $date],
            ['notes' => 'auto: HealthKit menstrual_flow'],
        );
    }
}
