<?php

namespace App\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * LLM 呼叫成本概覽。
 *
 * 上游 ai service 應該每次 call Anthropic 後寫一筆 cache:
 *   Cache::increment("llm.cost.day.<YYYY-MM-DD>.usd_micros", $costMicros);
 *   Cache::increment("llm.cost.day.<YYYY-MM-DD>.calls");
 *   Cache::increment("llm.cost.day.<YYYY-MM-DD>.provider.<name>.usd_micros", $costMicros);
 *
 * 用 micros (USD * 1e6) 才能準確存整數 — Cache::increment 不接受 float。
 * 若上游還沒 instrument 對應 keys，widget 顯示 0 / —，不炸。
 */
class LlmCostWidget extends StatsOverviewWidget
{
    protected ?string $heading = 'LLM 呼叫量 / 成本';

    protected static bool $isLazy = false;

    private function todayKey(string $suffix): string
    {
        return 'llm.cost.day.' . now()->format('Y-m-d') . '.' . $suffix;
    }

    private function yesterdayKey(string $suffix): string
    {
        return 'llm.cost.day.' . now()->subDay()->format('Y-m-d') . '.' . $suffix;
    }

    private function dollarsFromMicros(int $micros): string
    {
        return '$' . number_format($micros / 1_000_000, 2);
    }

    protected function getStats(): array
    {
        $todayUsd = (int) (Cache::get($this->todayKey('usd_micros')) ?? 0);
        $todayCalls = (int) (Cache::get($this->todayKey('calls')) ?? 0);
        $yesterdayUsd = (int) (Cache::get($this->yesterdayKey('usd_micros')) ?? 0);

        // Sum past 7 days
        $sum7d = 0;
        for ($i = 0; $i < 7; $i++) {
            $sum7d += (int) (Cache::get('llm.cost.day.' . now()->subDays($i)->format('Y-m-d') . '.usd_micros') ?? 0);
        }

        return [
            Stat::make('今日 LLM 成本', $this->dollarsFromMicros($todayUsd))
                ->description("呼叫 {$todayCalls} 次")
                ->color($todayUsd > 5_000_000 ? 'danger' : 'info')  // > $5 today = warn
                ->icon('heroicon-o-cpu-chip'),

            Stat::make('昨日 LLM 成本', $this->dollarsFromMicros($yesterdayUsd))
                ->description('日結對照')
                ->color('gray')
                ->icon('heroicon-o-calendar'),

            Stat::make('近 7 天累積', $this->dollarsFromMicros($sum7d))
                ->description('rolling sum')
                ->color('warning')
                ->icon('heroicon-o-banknotes'),
        ];
    }
}
