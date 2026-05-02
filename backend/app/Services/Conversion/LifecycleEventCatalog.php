<?php

namespace App\Services\Conversion;

/**
 * ADR-003 / ADR-008：對母艦 conversion service 發的 lifecycle 訊號。
 *
 * **這些訊號只給母艦 lead pool 用，App 內 zero 顯示**。
 */
final class LifecycleEventCatalog
{
    /** 用戶開始用 calendar */
    public const APP_OPENED = 'pandora_calendar.app_opened';

    /** 訂閱中 */
    public const SUBSCRIPTION_ACTIVE = 'pandora_calendar.subscription_active';

    /** 連用 ≥ 90 天 */
    public const SUSTAINED_USER = 'pandora_calendar.sustained_user';

    /** 連用 ≥ 180 天 + 訂閱中 + 母艦消費過 → mother lead pool 進「適合邀請聊加盟」 */
    public const LOYALIST_HIGH = 'pandora_calendar.loyalist_high';

    /** 主動分享朵朵建議給朋友 → 強訊號 */
    public const SHARED_DODO_INSIGHT = 'pandora_calendar.shared_dodo_insight';

    public const ALL = [
        self::APP_OPENED, self::SUBSCRIPTION_ACTIVE, self::SUSTAINED_USER,
        self::LOYALIST_HIGH, self::SHARED_DODO_INSIGHT,
    ];
}
