<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * 一次性清洗 prod 既有髒 cycle 資料：
 * - end_date 在未來
 * - end_date - start_date > 14 天（正常經期 ≤ 10，14 給異常 buffer）
 *
 * 對應 bug：用戶看到月曆 phase coloring 全錯（如 user 55 cycle start=2026-05-02 / end=2026-05-21
 * 導致 cycleDay 1-20 全被標 menstrual）。Controller / Predictor / Model 已加 guard，
 * 此 migration 把歷史髒資料 set end_date = null 讓使用者重新填。
 *
 * down() noop — 我們不知道原 end_date 是不是真有意填、還是手滑，硬還原可能再引入錯誤值。
 */
return new class extends Migration
{
    public function up(): void
    {
        $today = now()->toDateString();

        // 用 SQL DATEDIFF 兼容 MariaDB / SQLite（SQLite 走 julianday，但 dev seeder 不會出髒料；
        // prod 是 MariaDB，DATEDIFF 直接可用）。
        $driver = DB::connection()->getDriverName();

        if ($driver === 'sqlite') {
            $rows = DB::table('cycles')
                ->whereNotNull('end_date')
                ->where(function ($q) use ($today) {
                    $q->where('end_date', '>', $today)
                        ->orWhereRaw("(julianday(end_date) - julianday(start_date)) > 14");
                })
                ->get(['id', 'user_id', 'start_date', 'end_date']);
        } else {
            $rows = DB::table('cycles')
                ->whereNotNull('end_date')
                ->where(function ($q) use ($today) {
                    $q->where('end_date', '>', $today)
                        ->orWhereRaw('DATEDIFF(end_date, start_date) > 14');
                })
                ->get(['id', 'user_id', 'start_date', 'end_date']);
        }

        $count = $rows->count();

        if ($count === 0) {
            Log::info('clean_invalid_cycle_end_dates: no dirty rows found.');

            return;
        }

        DB::table('cycles')
            ->whereIn('id', $rows->pluck('id'))
            ->update(['end_date' => null]);

        Log::warning('clean_invalid_cycle_end_dates: nullified end_date on dirty rows', [
            'count' => $count,
            'sample' => $rows->take(10)->map(fn ($r) => [
                'id' => $r->id,
                'user_id' => $r->user_id,
                'start_date' => $r->start_date,
                'end_date' => $r->end_date,
            ])->all(),
        ]);
    }

    public function down(): void
    {
        // noop — 不可逆。
    }
};
