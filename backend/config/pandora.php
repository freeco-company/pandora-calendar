<?php

return [
    /*
    | 集團 Pandora Core Identity client config（ADR-007）
    | driver=mock（dev / testing）/ http（P1+ prod）
    */
    'identity' => [
        'driver' => env('IDENTITY_DRIVER', 'mock'),
        'base_url' => env('PANDORA_CORE_BASE_URL'),
        'secret' => env('PANDORA_CORE_INTERNAL_SECRET'),
    ],

    /*
    | 集團 Gamification publisher（ADR-009）
    | 空 = noop（不 publish）；填了就會走 HTTP publish 到 py-service
    */
    'gamification' => [
        'base_url' => env('PANDORA_GAMIFICATION_BASE_URL'),
        'secret' => env('PANDORA_GAMIFICATION_INTERNAL_SECRET'),
        'app_id' => env('PANDORA_GAMIFICATION_APP_ID', 'pandora_calendar'),
    ],

    /*
    | 集團 Conversion / lifecycle publisher（ADR-003 + ADR-008）
    | App 內永遠不顯示加盟對話 — 訊號 publish 到母艦 lead pool
    */
    'conversion' => [
        'base_url' => env('PANDORA_CONVERSION_BASE_URL'),
        'secret' => env('PANDORA_CONVERSION_INTERNAL_SECRET'),
        'app_id' => env('PANDORA_CONVERSION_APP_ID', 'pandora_calendar'),
    ],

    /*
    | 訂閱 / IAP gating
    */
    'subscription' => [
        'free_dodo_checkin_per_day' => env('FREE_DODO_CHECKIN_PER_DAY', 1),
        'free_history_months' => env('FREE_HISTORY_MONTHS', 12),
        'apple_iap_shared_secret' => env('APPLE_IAP_SHARED_SECRET'),
        'google_play_service_account_json' => env('GOOGLE_PLAY_SERVICE_ACCOUNT_JSON'),
        'ecpay_merchant_id' => env('ECPAY_MERCHANT_ID'),
        'ecpay_hash_key' => env('ECPAY_HASH_KEY'),
        'ecpay_hash_iv' => env('ECPAY_HASH_IV'),
    ],

    /*
    | 商品連結 gate（嚴守紅線）
    | 必須 mother_purchase_count >= 1 + active subscription + ≥ 90 active days
    */
    'commerce_gate' => [
        'min_mother_purchases' => 1,
        'require_active_subscription' => true,
        'min_active_days' => 90,
    ],
];
