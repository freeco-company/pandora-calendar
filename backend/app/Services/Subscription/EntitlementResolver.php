<?php

namespace App\Services\Subscription;

use App\Models\Subscription;
use App\Models\User;
use Carbon\CarbonImmutable;

class EntitlementResolver
{
    public function __construct(
        private readonly PremiumTrialService $trialService,
    ) {}

    public function resolve(User $user): Entitlements
    {
        $sub = Subscription::where('user_id', $user->id)
            ->whereIn('status', ['active', 'grace'])
            ->orderByDesc('ends_at')
            ->first();

        $hasActiveSub = $sub !== null && $sub->isActive();

        // 重抓一次 user 確保 trial 欄位是最新的（避免 actingAs 用 stale instance）
        $freshUser = User::find($user->id) ?? $user;
        $trialState = $this->trialService->userTrialState($freshUser);
        $inTrial = (bool) $trialState['is_trial'];

        // Premium 條件：付費訂閱中 OR 在 7 天 trial 期內
        $isPremium = $hasActiveSub || $inTrial;

        if (! $isPremium) {
            return Entitlements::free((bool) $trialState['trial_used']);
        }

        return new Entitlements(
            premium: true,
            activeSubscription: $hasActiveSub ? $sub : null,
            premiumUntil: $hasActiveSub && $sub->ends_at
                ? CarbonImmutable::parse($sub->ends_at)
                : null,
            inTrial: $inTrial,
            trialDaysRemaining: $trialState['days_remaining'],
            trialEndsAt: $trialState['ends_at']
                ? CarbonImmutable::parse($trialState['ends_at'])
                : null,
            trialUsed: (bool) $trialState['trial_used'],
            trialSource: $trialState['source'],
        );
    }
}
