<?php

namespace App\Services\Gamification;

use App\Models\BodyDexEntry;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Wave 13 — BodyDex service。
 *
 * 每次 user 記 symptom → 寫 / 增 log_count。
 * 收集滿 catalog 數 → 解 legendary outfit `body_dex_master`（OutfitCatalog 對應補卡）。
 */
final class BodyDexService
{
    public function record(int $userId, string $symptomKey, ?CarbonImmutable $on = null): BodyDexEntry
    {
        $on ??= CarbonImmutable::today();

        // upsert manually for sqlite-friendliness
        $entry = BodyDexEntry::where('user_id', $userId)
            ->where('symptom_key', $symptomKey)
            ->first();

        if ($entry === null) {
            $entry = BodyDexEntry::create([
                'user_id' => $userId,
                'symptom_key' => $symptomKey,
                'first_logged_on' => $on->toDateString(),
                'log_count' => 1,
            ]);
        } else {
            $entry->log_count = $entry->log_count + 1;
            $entry->save();
        }

        return $entry;
    }

    /**
     * @return \Illuminate\Support\Collection<int, BodyDexEntry>
     */
    public function collected(int $userId): \Illuminate\Support\Collection
    {
        return BodyDexEntry::where('user_id', $userId)
            ->orderBy('first_logged_on')
            ->get();
    }

    public function totalTarget(): int
    {
        return (int) config('body-dex.total_target', 30);
    }

    public function catalog(): array
    {
        return (array) config('body-dex.entries', []);
    }

    public function snapshot(int $userId): array
    {
        $catalog = $this->catalog();
        $collected = $this->collected($userId)->keyBy('symptom_key');
        $entries = [];
        foreach ($catalog as $key => $meta) {
            $hit = $collected->get($key);
            $entries[] = [
                'symptom_key' => $key,
                'label' => $meta['label'] ?? $key,
                'hint' => $meta['hint'] ?? '',
                'rarity' => $meta['rarity'] ?? 'common',
                'collected' => $hit !== null,
                'first_logged_on' => $hit?->first_logged_on?->toDateString(),
                'log_count' => (int) ($hit->log_count ?? 0),
            ];
        }

        return [
            'total_target' => $this->totalTarget(),
            'collected_count' => $collected->count(),
            'entries' => $entries,
        ];
    }
}
