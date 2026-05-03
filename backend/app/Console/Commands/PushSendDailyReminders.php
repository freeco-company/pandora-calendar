<?php

namespace App\Console\Commands;

use App\Models\Cycle;
use App\Models\DodoCheckin;
use App\Models\PushSubscription;
use App\Models\User;
use App\Services\Calendar\BodyRhythmCalculator;
use App\Services\Calendar\CyclePredictor;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

/**
 * 每天早上 8:00 跑一次：對 push_opted_in=true 的用戶，依據今日 phase + streak 寄推播。
 *
 * 進階分支（讀 config/push-templates.php）：
 *   - period_eta_minus_1     經期前 1 天
 *   - period_started_today   經期當天
 *   - period_late_3d         經期遲到 3 天
 *   - period_late_7d         經期遲到 7 天
 *   - ovulation_eta          排卵期前 1 天
 *   - luteal_pms_window      黃體期 PMS window 第 3 天
 *   - streak_warning_2d_silent   連勝中斷預警
 *   - streak_milestone_7d / 30d  連勝里程碑
 *
 * 寄送規則：
 *   - 同分支 cooldown_hours 內只發 1 次（per-user，cache 紀錄）
 *   - 每用戶每天最多 max_per_user_per_day 個 push（global config）
 *   - 隨機從 variants 池抽 1 個（normal vs inclusive 依 user.preferences.inclusive_mode）
 *   - 用戶 push_opted_in=true 才送
 *   - en locale 暫 fallback 到 zh-TW（待 PM 拍板英文版）
 *
 * 需要 env：
 *   - PUSH_VAPID_SUBJECT="mailto:support@js-store.com.tw"
 *   - PUSH_VAPID_PUBLIC_KEY (base64url)
 *   - PUSH_VAPID_PRIVATE_KEY (base64url)
 */
class PushSendDailyReminders extends Command
{
    protected $signature = 'push:send-daily-reminders {--dry-run}';

    protected $description = '對 opt-in 用戶寄今日 phase / streak / late based 推播';

    public function __construct(
        private readonly CyclePredictor $predictor,
        private readonly BodyRhythmCalculator $calc,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $vapidSubject = (string) env('PUSH_VAPID_SUBJECT', '');
        $vapidPublic = (string) env('PUSH_VAPID_PUBLIC_KEY', '');
        $vapidPrivate = (string) env('PUSH_VAPID_PRIVATE_KEY', '');

        if ($vapidSubject === '' || $vapidPublic === '' || $vapidPrivate === '') {
            $this->error('VAPID keys 未設定（PUSH_VAPID_SUBJECT / _PUBLIC_KEY / _PRIVATE_KEY）');
            $this->info('用 `php artisan push:vapid-keygen` 產一份');

            return self::FAILURE;
        }

        $dry = (bool) $this->option('dry-run');

        $webPush = new WebPush([
            'VAPID' => [
                'subject' => $vapidSubject,
                'publicKey' => $vapidPublic,
                'privateKey' => $vapidPrivate,
            ],
        ]);

        $today = CarbonImmutable::today();
        $maxPerDay = (int) config('push-templates.global.max_per_user_per_day', 2);
        $sent = 0;
        $skipped = 0;

        User::query()
            ->where('push_opted_in', true)
            ->whereHas('pushSubscriptions', fn ($q) => $q)
            ->cursor()
            ->each(function (User $u) use ($webPush, $today, $maxPerDay, &$sent, &$skipped, $dry) {
                $messages = $this->messagesFor($u, $today);
                if (empty($messages)) {
                    $skipped++;
                    return;
                }

                // 每用戶每天最多 N 個 push
                $messages = array_slice($messages, 0, $maxPerDay);

                foreach ($messages as $msg) {
                    $payload = json_encode([
                        'title' => $msg['title'],
                        'body' => $msg['body'],
                        'tag' => 'pandora-calendar-' . $msg['branch'] . '-' . $today->toDateString(),
                        'url' => '/#/dodo',
                    ]);

                    foreach (PushSubscription::query()->where('user_id', $u->id)->get() as $sub) {
                        if ($dry) {
                            $this->line(" [dry] uuid={$u->identity_uuid} branch={$msg['branch']} → {$msg['title']}");
                            continue;
                        }
                        $webPush->queueNotification(
                            Subscription::create([
                                'endpoint' => $sub->endpoint,
                                'publicKey' => $sub->p256dh,
                                'authToken' => $sub->auth,
                            ]),
                            $payload,
                        );
                    }

                    // mark cooldown
                    if (! $dry) {
                        $cooldownHours = (int) data_get(
                            config('push-templates.branches.' . $msg['branch']),
                            'cooldown_hours',
                            24,
                        );
                        Cache::put(
                            $this->cooldownKey($u->id, $msg['branch']),
                            now()->toIso8601String(),
                            now()->addHours($cooldownHours),
                        );
                    }
                    $sent++;
                }
            });

        if (! $dry) {
            foreach ($webPush->flush() as $report) {
                if (! $report->isSuccess()) {
                    $reason = $report->getReason();
                    Log::warning('[Push] failed', ['reason' => $reason, 'endpoint' => $report->getEndpoint()]);
                    if (in_array($report->getResponse()?->getStatusCode(), [404, 410], true)) {
                        PushSubscription::query()->where('endpoint', $report->getEndpoint())->delete();
                    }
                }
            }
        }

        $this->info("sent={$sent} skipped={$skipped}".($dry ? ' (dry)' : ''));

        return self::SUCCESS;
    }

    /**
     * 對單一用戶決定今日要寄哪些分支（可能 0、1、多個）。
     *
     * @return array<int, array{branch:string, title:string, body:string}>
     */
    private function messagesFor(User $u, CarbonImmutable $today): array
    {
        $prediction = $this->predictor->predict($u->id, $today);
        $rhythm = $this->calc->compute($prediction, $today);
        $inclusive = (bool) data_get($u->preferences ?? [], 'inclusive_mode', false);

        $candidates = [];

        // ── 1. 經期前 1 天
        if ($rhythm->daysUntilNextPeriod === 1) {
            $candidates[] = 'period_eta_minus_1';
        }

        // ── 2. 經期當天
        if ($rhythm->daysUntilNextPeriod === 0) {
            $candidates[] = 'period_started_today';
        }

        // ── 3. 經期遲到 3 / 7 天
        if ($rhythm->daysUntilNextPeriod !== null && $rhythm->daysUntilNextPeriod < 0) {
            $late = abs($rhythm->daysUntilNextPeriod);
            if ($late >= 7) {
                $candidates[] = 'period_late_7d';
            } elseif ($late >= 3) {
                $candidates[] = 'period_late_3d';
            }
        }

        // ── 4. 排卵期前 1 天（cycleDay = avgCycleLength - 14 - 1）
        if (
            $rhythm->phase === 'follicular'
            && $rhythm->cycleDay !== null
            && $rhythm->cycleDay === $prediction->avgCycleLength - 14 - 1
        ) {
            $candidates[] = 'ovulation_eta';
        }

        // ── 5. 黃體期第 3 天 PMS window
        if (
            $rhythm->phase === 'luteal'
            && $rhythm->cycleDay !== null
            && $rhythm->cycleDay - ($prediction->avgCycleLength - 14) === 3
        ) {
            $candidates[] = 'luteal_pms_window';
        }

        // ── 6. 連勝相關（基於 dodo_checkins / cycles 最近活動）
        $streak = $this->computeStreak($u, $today);
        if ($streak === 0) {
            // 0 連勝但前 N 天有過 → 中斷預警（簡化判斷：昨天前推 7 天有 1+ 筆活動）
            if ($this->hasActivityInLastDays($u, $today, 7) && $this->daysSinceLastActivity($u, $today) === 2) {
                $candidates[] = 'streak_warning_2d_silent';
            }
        } elseif ($streak === 7) {
            $candidates[] = 'streak_milestone_7d';
        } elseif ($streak === 30) {
            $candidates[] = 'streak_milestone_30d';
        }

        // 去重 + cooldown 過濾
        $messages = [];
        foreach (array_unique($candidates) as $branch) {
            $branchCfg = config('push-templates.branches.' . $branch);
            if (! $branchCfg || ! ($branchCfg['enabled'] ?? false)) {
                continue;
            }
            if (Cache::has($this->cooldownKey($u->id, $branch))) {
                continue; // 在 cooldown 內，跳過
            }
            $variant = $this->pickVariant($branchCfg, $inclusive);
            if ($variant === null) {
                continue;
            }
            $messages[] = [
                'branch' => $branch,
                'title' => $variant['title'],
                'body' => $variant['body'],
            ];
        }

        return $messages;
    }

    /**
     * 隨機從 variants 池抽 1 個（依 inclusive 切 dict）。
     *
     * @param  array<string, mixed>  $branchCfg
     * @return ?array{title:string, body:string}
     */
    private function pickVariant(array $branchCfg, bool $inclusive): ?array
    {
        $key = $inclusive ? 'variants_inclusive' : 'variants';
        $pool = $branchCfg[$key] ?? $branchCfg['variants'] ?? [];
        if (empty($pool)) {
            return null;
        }

        return $pool[array_rand($pool)];
    }

    private function cooldownKey(int $userId, string $branch): string
    {
        return "push_cooldown:{$userId}:{$branch}";
    }

    /**
     * 連勝天數：以 dodo_checkins.checked_on + cycles.start_date + cycle_symptoms.logged_on 任一存在算當天有活動，
     * 從今天往前累加連續有活動的天數。
     */
    private function computeStreak(User $u, CarbonImmutable $today): int
    {
        $streak = 0;
        for ($i = 0; $i < 365; $i++) {
            $d = $today->subDays($i)->toDateString();
            $hasActivity = DodoCheckin::query()->where('user_id', $u->id)->where('checked_on', $d)->exists()
                || Cycle::query()->where('user_id', $u->id)->where('start_date', $d)->exists();
            if (! $hasActivity) {
                // 今天沒活動不馬上斷（畢竟可能還沒記），但昨天起沒活動就停
                if ($i === 0) {
                    continue;
                }
                break;
            }
            $streak++;
        }

        return $streak;
    }

    private function daysSinceLastActivity(User $u, CarbonImmutable $today): int
    {
        for ($i = 0; $i < 30; $i++) {
            $d = $today->subDays($i)->toDateString();
            $hasActivity = DodoCheckin::query()->where('user_id', $u->id)->where('checked_on', $d)->exists()
                || Cycle::query()->where('user_id', $u->id)->where('start_date', $d)->exists();
            if ($hasActivity) {
                return $i;
            }
        }

        return 30;
    }

    private function hasActivityInLastDays(User $u, CarbonImmutable $today, int $days): bool
    {
        $from = $today->subDays($days)->toDateString();

        return DodoCheckin::query()->where('user_id', $u->id)->where('checked_on', '>=', $from)->exists()
            || Cycle::query()->where('user_id', $u->id)->where('start_date', '>=', $from)->exists();
    }
}
