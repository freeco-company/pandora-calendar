<?php

namespace App\Console\Commands;

use App\Models\PushSubscription;
use App\Models\User;
use App\Services\Calendar\BodyRhythmCalculator;
use App\Services\Calendar\CyclePredictor;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

/**
 * 每天早上 8:00 跑一次：對 push_opted_in=true 的用戶，依據今日 phase 寄推播。
 *
 * 觸發規則（避免噪音）：
 *   - 經期前一天（黃體期 cycle_day = avg_cycle_length - 1）→ 提醒
 *   - 經期第一天 → 關懷
 *   - 排卵期前一天 → 提醒
 *   - 連勝中斷風險（昨天沒 check-in 但前 6 天連續）→ 別斷
 *
 * 平日 phase 不寄（不要每天 push 變煩人）。
 *
 * 需要 env：
 *   - PUSH_VAPID_SUBJECT="mailto:support@js-store.com.tw"
 *   - PUSH_VAPID_PUBLIC_KEY (base64url)
 *   - PUSH_VAPID_PRIVATE_KEY (base64url)
 *
 * 用 `php artisan push:vapid-keygen` 一次性產 keypair。
 */
class PushSendDailyReminders extends Command
{
    protected $signature = 'push:send-daily-reminders {--dry-run}';

    protected $description = '對 opt-in 用戶寄今日 phase-based 推播';

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
        $sent = 0;
        $skipped = 0;

        User::query()
            ->where('push_opted_in', true)
            ->whereHas('pushSubscriptions', fn ($q) => $q)
            ->cursor()
            ->each(function (User $u) use ($webPush, $today, &$sent, &$skipped, $dry) {
                $message = $this->messageFor($u, $today);
                if ($message === null) {
                    $skipped++;
                    return;
                }

                $payload = json_encode([
                    'title' => $message['title'],
                    'body' => $message['body'],
                    'tag' => 'pandora-calendar-' . $today->toDateString(),
                    'url' => '/#/dodo',
                ]);

                foreach (PushSubscription::query()->where('user_id', $u->id)->get() as $sub) {
                    if ($dry) {
                        $this->line(" [dry] uuid={$u->identity_uuid} → {$message['title']}");
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
                $sent++;
            });

        if (! $dry) {
            foreach ($webPush->flush() as $report) {
                if (! $report->isSuccess()) {
                    $reason = $report->getReason();
                    Log::warning('[Push] failed', ['reason' => $reason, 'endpoint' => $report->getEndpoint()]);
                    // 410 / 404 → 訂閱失效，刪掉
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
     * @return ?array{title:string, body:string}
     */
    private function messageFor(User $u, CarbonImmutable $today): ?array
    {
        $prediction = $this->predictor->predict($u->id, $today);
        $rhythm = $this->calc->compute($prediction, $today);

        // 經期前一天
        if ($rhythm->daysUntilNextPeriod === 1) {
            return ['title' => '朵朵想跟妳說', 'body' => '經期可能明天到，記得備好妳的小工具，今天多照顧自己一點。'];
        }
        // 經期當天
        if ($rhythm->daysUntilNextPeriod === 0) {
            return ['title' => '朵朵在這', 'body' => '今天可能是經期第一天，多喝溫水、別硬撐。'];
        }
        // 排卵期前一天
        if ($rhythm->phase === 'follicular' && $rhythm->cycleDay === $prediction->avgCycleLength - 14 - 1) {
            return ['title' => '能量高峰要來了', 'body' => '明天進入排卵期，朵朵覺得妳會發光。'];
        }

        return null; // 平日不寄
    }
}
