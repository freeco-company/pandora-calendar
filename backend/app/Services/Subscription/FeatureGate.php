<?php

namespace App\Services\Subscription;

use App\Models\DodoCheckin;
use App\Models\Subscription;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;

/**
 * 訂閱 freemium gate。
 *
 * Free tier:
 * - 經期記錄與預測（無限）
 * - 朵朵 check-in：每天 1 次
 * - 12 個月歷史
 * - 基本症狀標記
 *
 * Premium (NT$99/月 / NT$899/年):
 * - 朵朵 check-in 無限次
 * - 多年歷史
 * - PMS 模式分析（P4 起）
 * - 孕期模式（P4 起）
 * - 跨產品同步（P3 起）
 * - week report PDF（P5 起）
 * - HealthKit / Health Connect（P5 起）
 */
class FeatureGate
{
    public function __construct(private readonly EntitlementResolver $entitlements) {}

    public function isPremium(User $user): bool
    {
        return $this->entitlements->resolve($user)->isPremium();
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
