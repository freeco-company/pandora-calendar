<?php

/**
 * Push channel config — pluggable, env-driven。
 *
 * 缺 credential 時對應 channel 變 noop（log info 不報錯），不擋上架。
 * 有 credential 時走真實 FCM / APNs / WebPush 發送。
 */

return [
    /*
    |--------------------------------------------------------------------------
    | FCM (Android, HTTP v1 + OAuth2 service account)
    |--------------------------------------------------------------------------
    | FCM_PROJECT_ID         — Firebase project ID (例如 pandora-calendar-prod)
    | FCM_CREDENTIALS_PATH   — Service account JSON 檔案絕對路徑
    |                           (從 Firebase Console → Project Settings → Service accounts → Generate new private key)
    */
    'fcm' => [
        'project_id' => env('FCM_PROJECT_ID', ''),
        'credentials_path' => env('FCM_CREDENTIALS_PATH', ''),
    ],

    /*
    |--------------------------------------------------------------------------
    | APNs (iOS, HTTP/2 + JWT auth)
    |--------------------------------------------------------------------------
    | APNS_TEAM_ID           — Apple Developer Team ID (10 chars)
    | APNS_KEY_ID            — APNs Auth Key ID (10 chars)
    | APNS_PRIVATE_KEY_PATH  — Path to AuthKey_XXXXXXXXXX.p8 file
    | APNS_BUNDLE_ID         — App bundle id (com.jerosse.pandora.calendar)
    | APNS_SANDBOX_MODE      — true=development、false=production
    */
    'apns' => [
        'team_id' => env('APNS_TEAM_ID', ''),
        'key_id' => env('APNS_KEY_ID', ''),
        'private_key_path' => env('APNS_PRIVATE_KEY_PATH', ''),
        'bundle_id' => env('APNS_BUNDLE_ID', 'com.jerosse.pandora.calendar'),
        'sandbox' => filter_var(env('APNS_SANDBOX_MODE', false), FILTER_VALIDATE_BOOLEAN),
    ],

    /*
    |--------------------------------------------------------------------------
    | Web Push (VAPID, minishlink/web-push)
    |--------------------------------------------------------------------------
    | 沿用既有 PUSH_VAPID_* 鍵；新環境變數 WEBPUSH_* 也可用（先讀 WEBPUSH_，fallback PUSH_VAPID_）
    */
    'webpush' => [
        'subject' => env('WEBPUSH_VAPID_SUBJECT', env('PUSH_VAPID_SUBJECT', '')),
        'public_key' => env('WEBPUSH_VAPID_PUBLIC_KEY', env('PUSH_VAPID_PUBLIC_KEY', '')),
        'private_key' => env('WEBPUSH_VAPID_PRIVATE_KEY', env('PUSH_VAPID_PRIVATE_KEY', '')),
    ],

    /*
    |--------------------------------------------------------------------------
    | Metric cache keys (admin dashboard 用)
    |--------------------------------------------------------------------------
    */
    'metrics' => [
        'success_key' => 'push.sent.success',
        'failure_key' => 'push.sent.failure',
        'ttl_seconds' => 86400 * 30, // 30 days
    ],
];
