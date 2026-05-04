<?php

namespace App\Services\Subscription;

use App\Models\User;
use App\Support\Sentry\SentryHelper;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;

/**
 * 7-day Premium Trial — onboarding 完成自動啟動，無需信用卡。
 *
 * 紅線：
 *   - 一個 user 一輩子只一次（trial_used = true 後 startTrial() 永遠 false）
 *   - 不能購買、不能延長、不能 reset
 *   - 期間視為 Premium（FeatureGate::isPremium() = true，由 EntitlementResolver 處理）
 */
class PremiumTrialService
{
    public const DURATION_DAYS = 7;

    public const SOURCE_ONBOARDING = 'onboarding';
    public const SOURCE_MANUAL = 'manual';
    public const SOURCE_GIFT = 'gift';

    /** @var array<int, string> */
    public const VALID_SOURCES = [
        self::SOURCE_ONBOARDING,
        self::SOURCE_MANUAL,
        self::SOURCE_GIFT,
    ];

    /**
     * 啟動 trial。回 true = 啟動成功；false = 已用過或 user 不存在。
     */
    public function startTrial(int $userId, string $source = self::SOURCE_ONBOARDING): bool
    {
        if (! in_array($source, self::VALID_SOURCES, true)) {
            $source = self::SOURCE_ONBOARDING;
        }

        $user = User::find($userId);
        if ($user === null) {
            return false;
        }

        // 一輩子一次
        if ($user->trial_used) {
            return false;
        }

        $now = CarbonImmutable::now();
        $endsAt = $now->addDays(self::DURATION_DAYS);

        try {
            $user->forceFill([
                'trial_started_at' => $now,
                'trial_ends_at' => $endsAt,
                'trial_used' => true,
                'trial_source' => $source,
            ])->save();
        } catch (\Throwable $e) {
            SentryHelper::captureException($e, 'subscription', [
                'action' => 'start_trial',
                'user_id' => $userId,
                'source' => $source,
            ]);

            return false;
        }

        Log::info('subscription.trial_started', [
            'user_id' => $userId,
            'source' => $source,
            'ends_at' => $endsAt->toAtomString(),
        ]);

        return true;
    }

    public function isInTrial(int $userId): bool
    {
        $user = User::find($userId);

        return $this->userIsInTrial($user);
    }

    public function userIsInTrial(?User $user): bool
    {
        if ($user === null || $user->trial_ends_at === null) {
            return false;
        }

        return $user->trial_ends_at->isFuture();
    }

    public function daysRemaining(int $userId): ?int
    {
        $user = User::find($userId);
        if ($user === null || ! $this->userIsInTrial($user)) {
            return null;
        }

        $diff = (int) ceil(CarbonImmutable::now()->diffInHours($user->trial_ends_at, false) / 24);

        return max(0, min(self::DURATION_DAYS, $diff));
    }

    /**
     * @return array{
     *   is_trial: bool,
     *   days_remaining: int|null,
     *   trial_used: bool,
     *   started_at: string|null,
     *   ends_at: string|null,
     *   source: string|null,
     * }
     */
    public function trialState(int $userId): array
    {
        $user = User::find($userId);

        return $this->userTrialState($user);
    }

    /**
     * 同 trialState() 但接受 User 物件，避免多餘 DB 查詢。
     *
     * @return array{
     *   is_trial: bool,
     *   days_remaining: int|null,
     *   trial_used: bool,
     *   started_at: string|null,
     *   ends_at: string|null,
     *   source: string|null,
     * }
     */
    public function userTrialState(?User $user): array
    {
        if ($user === null) {
            return [
                'is_trial' => false,
                'days_remaining' => null,
                'trial_used' => false,
                'started_at' => null,
                'ends_at' => null,
                'source' => null,
            ];
        }

        $isTrial = $this->userIsInTrial($user);
        $daysRemaining = null;
        if ($isTrial && $user->trial_ends_at !== null) {
            $diff = (int) ceil(CarbonImmutable::now()->diffInHours($user->trial_ends_at, false) / 24);
            $daysRemaining = max(0, min(self::DURATION_DAYS, $diff));
        }

        return [
            'is_trial' => $isTrial,
            'days_remaining' => $daysRemaining,
            'trial_used' => (bool) $user->trial_used,
            'started_at' => $user->trial_started_at?->toAtomString(),
            'ends_at' => $user->trial_ends_at?->toAtomString(),
            'source' => $user->trial_source,
        ];
    }
}
