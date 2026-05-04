<?php

namespace App\Services\Calendar\Streak;

use App\Models\User;
use App\Models\UserDailyStreak;
use App\Services\Gamification\CalendarEventCatalog;
use App\Services\Gamification\GamificationPublisher;
use App\Services\Gamification\IdempotencyKey;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * SPEC-cross-app-streak Phase 1.B (calendar) — per-App 每日連續登入 streak.
 *
 * 鏡像 pandora-meal `App\Services\Dodo\Streak\DailyLoginStreakService`（PR #171），
 * 換 namespace + 對齊 calendar 既有 GamificationPublisher 簽名。
 *
 * `recordLogin()` 由 RecordDailyStreak middleware 在每個 authenticated request 跑一次
 * （成本：1 SELECT + 0~1 UPDATE）。
 *
 * 邏輯（時區 Asia/Taipei）：
 *   - last_login_date == today      → no-op；is_first_today=false
 *   - last_login_date == yesterday  → current_streak += 1; is_first_today=true
 *   - last_login_date is null / older → reset current_streak=1; is_first_today=true
 *
 * Milestone 觸發條件：is_first_today=true 且新 current_streak ∈
 *   [1, 3, 7, 14, 21, 30, 60, 100]
 *
 * Gamification publish：calendar event catalog 目前只有 `calendar.streak_30_days`
 * 是已知 event_kind（meal 那邊有 streak_3/7/14/30 是因為 meal 既有 catalog 已加）。
 * 為避免 publisher 拋 InvalidArgumentException 或 py-service 422，這裡只在
 * streak=30 時 publish，其他 milestone fail-soft skip — local streak 邏輯仍照常進行。
 */
class DailyLoginStreakService
{
    private const TIMEZONE = 'Asia/Taipei';

    /** @var list<int> */
    private const MILESTONES = [1, 3, 7, 14, 21, 30, 60, 100];

    /**
     * Catalog-known event_kinds for daily-login streak in calendar.
     *
     * Only `STREAK_30_DAYS` is in the catalog — other milestones (1/3/7/14/21/60/100)
     * fail-soft skip publish so the local streak still works. Adding new event_kinds
     * to the catalog is a separate cross-cutting change (py-service catalog + this map).
     *
     * @var array<int, string>
     */
    private const PUBLISH_KIND_BY_STREAK = [
        30 => CalendarEventCatalog::STREAK_30_DAYS,
    ];

    public function __construct(
        private readonly GamificationPublisher $gamification,
    ) {}

    /**
     * @return array{
     *     streak: int,
     *     longest_streak: int,
     *     is_first_today: bool,
     *     is_milestone: bool,
     *     milestone_label: ?string,
     *     today_date: string,
     * }
     */
    public function recordLogin(User $user): array
    {
        $today = Carbon::now(self::TIMEZONE)->toDateString();
        $yesterday = Carbon::now(self::TIMEZONE)->subDay()->toDateString();

        $result = DB::transaction(function () use ($user, $today, $yesterday): array {
            /** @var UserDailyStreak $row */
            $row = UserDailyStreak::query()
                ->lockForUpdate()
                ->firstOrCreate(
                    ['user_id' => $user->id],
                    ['current_streak' => 0, 'longest_streak' => 0, 'last_login_date' => null],
                );

            $last = $row->last_login_date?->toDateString();

            if ($last === $today) {
                return [
                    'streak' => (int) $row->current_streak,
                    'longest' => (int) $row->longest_streak,
                    'is_first_today' => false,
                ];
            }

            if ($last === $yesterday) {
                $row->current_streak = ((int) $row->current_streak) + 1;
            } else {
                $row->current_streak = 1;
            }

            if ($row->current_streak > $row->longest_streak) {
                $row->longest_streak = $row->current_streak;
            }

            $row->last_login_date = Carbon::parse($today);
            $row->save();

            return [
                'streak' => (int) $row->current_streak,
                'longest' => (int) $row->longest_streak,
                'is_first_today' => true,
            ];
        });

        $isMilestone = $result['is_first_today'] && in_array($result['streak'], self::MILESTONES, true);
        $milestoneLabel = $isMilestone ? $this->milestoneLabel($result['streak']) : null;

        if ($result['is_first_today']) {
            $this->safePublish($user, $result['streak'], $today);
        }

        return [
            'streak' => $result['streak'],
            'longest_streak' => $result['longest'],
            'is_first_today' => $result['is_first_today'],
            'is_milestone' => $isMilestone,
            'milestone_label' => $milestoneLabel,
            'today_date' => $today,
        ];
    }

    /**
     * Read-only snapshot — used by GET /api/streak/today alongside recordLogin().
     *
     * @return array{
     *     current_streak: int,
     *     longest_streak: int,
     *     last_login_date: ?string,
     *     today_date: string,
     * }
     */
    public function snapshot(User $user): array
    {
        $today = Carbon::now(self::TIMEZONE)->toDateString();
        $row = UserDailyStreak::query()->where('user_id', $user->id)->first();

        return [
            'current_streak' => $row ? (int) $row->current_streak : 0,
            'longest_streak' => $row ? (int) $row->longest_streak : 0,
            'last_login_date' => $row?->last_login_date?->toDateString(),
            'today_date' => $today,
        ];
    }

    private function milestoneLabel(int $streak): string
    {
        return match ($streak) {
            1 => '第一天',
            3 => '連續 3 天',
            7 => '連續 7 天',
            14 => '連續 14 天',
            21 => '連續 21 天',
            30 => '連續 30 天',
            60 => '連續 60 天',
            100 => '連續 100 天',
            default => "連續 {$streak} 天",
        };
    }

    /**
     * Fail-soft publish — local streak must succeed even when py-service is
     * down or catalog is behind. We swallow exceptions and log; queue retries
     * inside Publisher handle transient failures.
     */
    private function safePublish(User $user, int $streak, string $today): void
    {
        $kind = self::PUBLISH_KIND_BY_STREAK[$streak] ?? null;
        if ($kind === null) {
            return;
        }

        if (empty($user->identity_uuid)) {
            // calendar User mirror 在 ADR-007 接通前 identity_uuid 可能還是 null；
            // publisher 也會用 user model 但 outbox payload 寫入 uuid 必要 — skip 即可。
            return;
        }

        try {
            $this->gamification->publish(
                $user,
                $kind,
                ['streak_days' => $streak, 'source' => 'daily_login'],
                IdempotencyKey::make($kind, $user->id, 0, $today),
            );
        } catch (Throwable $e) {
            Log::warning('[DailyLoginStreak] publish failed (soft)', [
                'user_id' => $user->id,
                'kind' => $kind,
                'streak' => $streak,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
