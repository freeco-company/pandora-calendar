<?php

namespace App\Services\Commerce;

use App\Models\User;
use App\Services\Subscription\FeatureGate;
use Carbon\CarbonImmutable;

/**
 * 婕樂纖商品連結解析器（P5 起，極嚴守紅線）。
 *
 * Gate（必須全部通過才返回任何商品）：
 * - 母艦消費 ≥ 1（user.mother_total_orders > 0）
 * - 訂閱中（FeatureGate::isPremium(user) === true）
 * - 連用 ≥ 90 天（從首次經期記錄起算）
 *
 * 若 gate 不通過 → 返回空陣列（App 端就什麼都不顯示）。
 *
 * 文案紅線：
 * - 必過 LegalContentSanitizer
 * - 禁療效詞（改善 / 緩解 / 治療 / 排毒 / 調理 / 取代正餐 / 低 GI / 高纖維...）
 * - 用「陪伴 / 選擇 / 補充」這類模糊語
 *
 * 顯示位置紅線：
 * - 只在「我的 → 婕樂纖會員」深層頁，主流程零干擾
 */
class ProductLinkResolver
{
    public function __construct(
        private readonly FeatureGate $gate,
        private readonly MotherEcommerceConnector $connector,
    ) {}

    /**
     * @return array<int, array{product_slug: string, message: string, mother_url: string}>
     */
    public function resolveFor(User $user): array
    {
        if (! $this->passesGate($user)) {
            return [];
        }

        // 委派到 connector — 文案 / 規則來自 config('ecommerce-products') 並過 sanitizer
        return $this->connector->recommendationsFor($user);
    }

    public function passesGate(User $user): bool
    {
        $minPurchases = (int) config('pandora.commerce_gate.min_mother_purchases', 1);
        $minDays = (int) config('pandora.commerce_gate.min_active_days', 90);
        $requireSub = (bool) config('pandora.commerce_gate.require_active_subscription', true);

        if (($user->mother_total_orders ?? 0) < $minPurchases) {
            return false;
        }
        if ($requireSub && ! $this->gate->isPremium($user)) {
            return false;
        }

        $oldest = \App\Models\Cycle::where('user_id', $user->id)->orderBy('start_date')->first();
        if (! $oldest) {
            return false;
        }

        return CarbonImmutable::parse($oldest->start_date)->diffInDays(now()) >= $minDays;
    }
}
