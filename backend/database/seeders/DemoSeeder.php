<?php

namespace Database\Seeders;

use App\Models\Cycle;
use App\Models\CycleSymptom;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Phase 0 demo seeder：3 個假用戶 + 90 天歷史資料。
 *
 * 命名遵守集團 tone-voice 硬規則：用戶名一律用「妳 / 朋友 / 朵朵」風格，不寫「您 / 會員」。
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $users = [
            ['name' => '小敏', 'email' => 'demo-min@pandora-calendar.test', 'cycle_length' => 28],
            ['name' => '雨晴', 'email' => 'demo-yuching@pandora-calendar.test', 'cycle_length' => 30],
            ['name' => '阿伶', 'email' => 'demo-aling@pandora-calendar.test', 'cycle_length' => 26],
        ];

        $today = CarbonImmutable::today();

        foreach ($users as $u) {
            $user = User::updateOrCreate(
                ['email' => $u['email']],
                [
                    'name' => $u['name'],
                    'password' => Hash::make('demo1234'),
                    'email_verified_at' => now(),
                ],
            );

            // 90 天 = 約 3 個週期
            $cursor = $today->subDays(90);
            $cycleStarts = [];
            while ($cursor->lessThanOrEqualTo($today)) {
                $cycleStarts[] = $cursor;
                $cursor = $cursor->addDays($u['cycle_length']);
            }

            // 取最後一個應為「最近的一次經期」，但若超過今天就丟掉
            $cycleStarts = array_filter($cycleStarts, fn ($d) => $d->lessThanOrEqualTo($today));

            // Wipe & re-seed for repeatability
            Cycle::where('user_id', $user->id)->delete();
            CycleSymptom::where('user_id', $user->id)->delete();

            foreach ($cycleStarts as $start) {
                $endDate = $start->addDays(4);
                Cycle::create([
                    'user_id' => $user->id,
                    'start_date' => $start->toDateString(),
                    'end_date' => $endDate->lessThanOrEqualTo($today) ? $endDate->toDateString() : null,
                    'peak_flow' => 3,
                    'notes' => null,
                ]);
            }

            // Symptoms: 每個週期經前期前 3 天標 craving_sweet + mood_swing
            foreach ($cycleStarts as $start) {
                for ($d = -5; $d <= -1; $d++) {
                    $loggedOn = $start->addDays($d);
                    if ($loggedOn->greaterThan($today)) {
                        continue;
                    }
                    CycleSymptom::create([
                        'user_id' => $user->id,
                        'logged_on' => $loggedOn->toDateString(),
                        'tags' => ['craving_sweet', 'mood_swing'],
                        'mood' => 'okay',
                        'note' => null,
                    ]);
                }
            }
        }

        $this->command?->info('Seeded 3 demo users with ~3 cycles each over the past 90 days.');
        $this->command?->line('  → demo-min@pandora-calendar.test (cycle 28d)');
        $this->command?->line('  → demo-yuching@pandora-calendar.test (cycle 30d)');
        $this->command?->line('  → demo-aling@pandora-calendar.test (cycle 26d)');
        $this->command?->line('  password 一律 demo1234');
    }
}
