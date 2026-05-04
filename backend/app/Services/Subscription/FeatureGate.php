<?php

namespace App\Services\Subscription;

use App\Models\DodoCheckin;
use App\Models\User;
use Carbon\CarbonImmutable;

/**
 * Freemium gate（已重新分層 2026-05-04）
 *
 * Tier:
 *   - FREE        — 完全免費（含放寬後的 90% 功能 Day 1）
 *   - TRIAL       — onboarding 完啟動的 7 天 Premium 試用（自動，無信用卡）
 *   - PREMIUM     — 付費訂閱中
 *
 * isPremium() 對 trial 與 paid 都回 true（trial = Premium 全功能體驗）
 *
 * Free 放寬後可用：
 *   - PMS 分析 Top 3 / BBT has_shift 基本欄位 / YearReview 基本卡 (cover/phase_dist/top_mood/closing)
 *   - Pattern Report 最近 3 份 / Story chapter 1-5 自動解
 *   - Body Dex 全部 / Skill Path 3 條 / Q&A 5/天
 *
 * Premium / Trial 才有：
 *   - PMS Top 4-5 / BBT 精細 (coverline / shift_confidence)
 *   - YearReview 12 卡完整 + 全歷史 / Pattern Report 全歷史
 *   - Story chapter 6-25 / Pregnancy mode / HealthKit reflection
 *   - 進度照雲端 sync / 朵朵 LLM 高 cap / Q&A 無限
 */
class FeatureGate
{
    public function __construct(private readonly EntitlementResolver $entitlements) {}

    public function isPremium(User $user): bool
    {
        return $this->entitlements->resolve($user)->isPremium();
    }

    /**
     * 'free' | 'trial' | 'premium'
     */
    public function effectiveTier(User $user): string
    {
        return $this->entitlements->resolve($user)->tier();
    }

    public function entitlements(User $user): Entitlements
    {
        return $this->entitlements->resolve($user);
    }

    public function canCheckinDodo(User $user, ?CarbonImmutable $on = null): GateResult
    {
        if ($this->isPremium($user)) {
            return GateResult::allow();
        }

        $on ??= CarbonImmutable::today();
        $today = $on->toDateString();
        $count = DodoCheckin::where('user_id', $user->id)
            ->whereDate('checked_on', $today)
            ->count();

        $limit = (int) config('pandora.subscription.free_dodo_checkin_per_day', 1);
        if ($count >= $limit) {
            return GateResult::deny(
                'free_daily_dodo_limit',
                "免費版每天 {$limit} 次朵朵 check-in；升級 Premium 解鎖無限。",
            );
        }

        return GateResult::allow();
    }

    public function maxHistoryMonths(User $user): int
    {
        return $this->isPremium($user) ? 9999 : (int) config('pandora.subscription.free_history_months', 12);
    }
}
