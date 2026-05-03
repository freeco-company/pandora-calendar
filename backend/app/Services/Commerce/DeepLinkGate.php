<?php

namespace App\Services\Commerce;

use App\Models\Cycle;
use App\Models\User;
use App\Services\Subscription\FeatureGate;
use Carbon\CarbonImmutable;

/**
 * 婕樂纖深層商品連結 gate（P5，集團紅線）。
 *
 * 必須 ALL 條件成立才 eligible：
 *   1. linked_to_mother：user 已綁母艦（mother_customer_id != null）
 *   2. has_purchase：母艦 ≥ 1 筆訂單（mother_total_orders >= min_mother_purchases）
 *   3. subscription_active：calendar Premium 訂閱中
 *   4. usage_long_enough：連用 ≥ min_active_days 天（從首次 cycle 起算）
 *
 * 不 eligible 時 reasons 回 ['not_linked', 'no_purchase', 'no_subscription', 'too_new']
 * 子集，前端用 reason 決定 empty state 文案。
 */
class DeepLinkGate
{
    public function __construct(private readonly FeatureGate $featureGate) {}

    /**
     * @return array{eligible: bool, reasons: array<int, string>, days_used: int}
     */
    public function evaluate(User $user): array
    {
        $reasons = [];
        $minPurchases = (int) config('pandora.commerce_gate.min_mother_purchases', 1);
        $minDays = (int) config('pandora.commerce_gate.min_active_days', 90);
        $requireSub = (bool) config('pandora.commerce_gate.require_active_subscription', true);

        // 1. 綁母艦 — 用 mother_customer_id（mirror 欄位）；mother_total_orders > 0 隱含已綁
        $linkedToMother = ! empty($user->mother_customer_id) || ($user->mother_total_orders ?? 0) > 0;
        if (! $linkedToMother) {
            $reasons[] = 'not_linked';
        }

        // 2. 母艦消費筆數
        if (($user->mother_total_orders ?? 0) < $minPurchases) {
            $reasons[] = 'no_purchase';
        }

        // 3. 訂閱中
        if ($requireSub && ! $this->featureGate->isPremium($user)) {
            $reasons[] = 'no_subscription';
        }

        // 4. 連用天數（從首次經期記錄起算；若沒記錄 fallback 到 user.created_at）
        $oldest = Cycle::where('user_id', $user->id)->orderBy('start_date')->first();
        if ($oldest) {
            $daysUsed = (int) CarbonImmutable::parse($oldest->start_date)->diffInDays(now());
        } elseif ($user->created_at) {
            $daysUsed = (int) CarbonImmutable::parse($user->created_at)->diffInDays(now());
        } else {
            $daysUsed = 0;
        }
        if ($daysUsed < $minDays) {
            $reasons[] = 'too_new';
        }

        return [
            'eligible' => $reasons === [],
            'reasons' => $reasons,
            'days_used' => $daysUsed,
        ];
    }

    public function passes(User $user): bool
    {
        return $this->evaluate($user)['eligible'];
    }
}
