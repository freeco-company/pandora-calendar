<?php

namespace App\Services\Health;

use App\Models\HealthSample;
use App\Models\User;
use Carbon\CarbonImmutable;

/**
 * HealthKit / Health Connect 匯入。
 *
 * Capacitor 端用 capacitor-health 或 自家 plugin 把 sample 撈出來，POST 到本 API；
 * server 端做 dedupe + 用以輔助 cycle phase 預測（基礎體溫變化曲線）。
 */
class HealthSampleImporter
{
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
}
