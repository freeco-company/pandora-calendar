<?php

namespace App\Services\Conversion;

use App\Models\Cycle;
use App\Models\Subscription;
use App\Models\User;
use Carbon\CarbonImmutable;

/**
 * 計算用戶當前對母艦 lead pool 的 lifecycle stage。
 *
 * 對應 ADR-008 兩段漏斗：
 * - sustained_user：連用 ≥ 90 天
 * - loyalist_high：連用 ≥ 180 天 + 訂閱中 + 母艦消費過 1 筆 → 母艦後台「適合邀請聊加盟」訊號
 *
 * **絕不在 App 內顯示這個 stage**；只 publish 到 conversion service。
 */
class LoyaltySignalEvaluator
{
    public function __construct(private readonly ConversionPublisher $publisher) {}

    public function evaluate(User $user): ?string
    {
        $oldestCycle = Cycle::where('user_id', $user->id)->orderBy('start_date')->first();
        if (! $oldestCycle) {
            return null;
        }

        $activeDays = CarbonImmutable::parse($oldestCycle->start_date)->diffInDays(CarbonImmutable::today());
        $hasActiveSub = Subscription::where('user_id', $user->id)
            ->whereIn('status', ['active', 'grace'])
            ->where(function ($q) {
                $q->whereNull('ends_at')->orWhere('ends_at', '>', now());
            })
            ->exists();
        $hasMotherPurchase = (bool) ($user->mother_total_orders ?? 0);

        if ($activeDays >= 180 && $hasActiveSub && $hasMotherPurchase) {
            $this->publisher->publish($user, LifecycleEventCatalog::LOYALIST_HIGH, [
                'active_days' => $activeDays,
                'has_active_subscription' => true,
                'has_mother_purchase' => true,
            ]);

            return LifecycleEventCatalog::LOYALIST_HIGH;
        }

        if ($activeDays >= 90) {
            $this->publisher->publish($user, LifecycleEventCatalog::SUSTAINED_USER, [
                'active_days' => $activeDays,
            ]);

            return LifecycleEventCatalog::SUSTAINED_USER;
        }

        return null;
    }
}
