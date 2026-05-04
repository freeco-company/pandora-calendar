<?php

namespace App\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Cache;

/**
 * LLM 成本拆解（依 provider × 7d / 30d / 90d）。
 *
 * 上游 LLMClient 應寫 cache key（micros 整數，避免 float 累加漂移）：
 *   llm.cost.day.<YYYY-MM-DD>.usd_micros                      — 全 provider 加總（既有）
 *   llm.cost.day.<YYYY-MM-DD>.calls                           — 既有
 *   llm.cost.day.<YYYY-MM-DD>.provider.<openai|claude>.usd_micros — provider 拆解
 *   llm.cost.day.<YYYY-MM-DD>.cap_hits                        — 觸發 daily cap 次數
 *
 * Key 不存在時 widget 顯示 0 / —，不炸。
 */
class LlmCostBreakdownWidget extends StatsOverviewWidget
{
    protected ?string $heading = 'LLM 成本（依 provider 拆解）';

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    private function dollars(int $micros): string
    {
        return '$' . number_format($micros / 1_000_000, 2);
    }

    /**
     * Sum cache key over the past N days.
     */
    private function sumDays(string $suffix, int $days): int
    {
        $sum = 0;
        for ($i = 0; $i < $days; $i++) {
            $key = 'llm.cost.day.' . now()->subDays($i)->format('Y-m-d') . '.' . $suffix;
            $sum += (int) (Cache::get($key) ?? 0);
        }

        return $sum;
    }

    protected function getStats(): array
    {
        $openai7 = $this->sumDays('provider.openai.usd_micros', 7);
        $claude7 = $this->sumDays('provider.claude.usd_micros', 7);
        $total30 = $this->sumDays('usd_micros', 30);
        $total90 = $this->sumDays('usd_micros', 90);
        $capHits7 = $this->sumDays('cap_hits', 7);

        return [
            Stat::make('OpenAI 7d', $this->dollars($openai7))
                ->description('past 7 days')
                ->color('info')
                ->icon('heroicon-o-cpu-chip'),

            Stat::make('Claude 7d', $this->dollars($claude7))
                ->description('past 7 days')
                ->color('warning')
                ->icon('heroicon-o-cpu-chip'),

            Stat::make('30d 累積', $this->dollars($total30))
                ->description('all providers')
                ->color('gray')
                ->icon('heroicon-o-banknotes'),

            Stat::make('90d 累積', $this->dollars($total90))
                ->description('all providers')
                ->color('gray')
                ->icon('heroicon-o-banknotes'),

            Stat::make('7d Daily-cap 觸發', number_format($capHits7))
                ->description($capHits7 > 0 ? '達 daily cap 提醒檢視 quota' : '本週無觸發')
                ->color($capHits7 > 0 ? 'danger' : 'success')
                ->icon('heroicon-o-shield-exclamation'),
        ];
    }
}
