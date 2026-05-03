<?php

namespace App\Console\Commands;

use App\Models\Cycle;
use App\Services\Reports\PatternReportGenerator;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

/**
 * 每日 04:00 跑：找昨天結束新 cycle 的用戶（昨天 cycleDay=N、今天 cycleDay=1）→
 * 為「上一個 cycle」生成 pattern report。
 *
 * 偵測方式：找今天 start_date = today 的 cycle，那麼前一個 cycle 是剛結束的。
 * Idempotent：generator 內 dedup（同 user / cycle_id 已存在直接 return）。
 */
class GeneratePatternReportsCommand extends Command
{
    protected $signature = 'pandora:pattern-reports:generate {--date= : 模擬日期 (YYYY-MM-DD)，預設今天}';

    protected $description = '為昨天結束 cycle 的用戶生成 pattern report';

    public function handle(PatternReportGenerator $generator): int
    {
        $today = $this->option('date')
            ? CarbonImmutable::parse($this->option('date'))
            : CarbonImmutable::today();

        // 今天有新 cycle 開始 → 該 user 上個 cycle 剛結束
        $newCycles = Cycle::whereDate('start_date', $today->toDateString())->get();

        $generated = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($newCycles as $newCycle) {
            $prev = Cycle::where('user_id', $newCycle->user_id)
                ->whereKeyNot($newCycle->id)
                ->where('start_date', '<', $newCycle->start_date->toDateString())
                ->orderByDesc('start_date')
                ->first();

            if (! $prev) {
                $skipped++;

                continue;
            }

            try {
                $generator->generateForCycle($newCycle->user_id, $prev->id);
                $generated++;
            } catch (\Throwable $e) {
                $failed++;
                $this->error("user {$newCycle->user_id} cycle {$prev->id}: ".$e->getMessage());
                report($e);
            }
        }

        $this->info("pattern reports: generated={$generated} skipped={$skipped} failed={$failed}");

        return self::SUCCESS;
    }
}
