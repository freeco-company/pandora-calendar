<?php

/*
| ADR-009 集團遊戲化 publisher / webhook 設定（pandora-calendar 端）。
|
| Publisher（outgoing）：calendar 把事件 → outbox → flush job 用 X-Internal-Secret
| header POST 到 py-service `/internal/gamification/events`。
|
| Webhook（incoming）：py-service 把 level_up / achievement_awarded / outfit_unlocked
| 用 HMAC-SHA256 簽進來，calendar 寫回 users.total_xp / level / outfit_state，
| 並把 achievement payload cache 一份給 frontend 下次 polling 撈。
|
| Naming：對齊集團 `PANDORA_GAMIFICATION_*` 命名（meal / mother / py-service 共用）。
*/

return [
    /*
    | enabled=true 才綁 HttpGamificationPublisher，否則 NoopGamificationPublisher。
    | base_url + secret 任一缺也走 noop（避免 prod 漏設炸出 NPE）。
    */
    'enabled' => env('PANDORA_GAMIFICATION_ENABLED', false),

    'base_url' => env('PANDORA_GAMIFICATION_BASE_URL'),

    /*
    | publisher 端 outbound HMAC secret（py-service 共用同一把）
    */
    'hmac_secret' => env('PANDORA_GAMIFICATION_HMAC_SECRET'),

    /*
    | 向後相容：HttpGamificationPublisher 目前用 X-Internal-Secret header；
    | 若 prod 已設 PANDORA_GAMIFICATION_INTERNAL_SECRET 就 fallback 用它。
    */
    'internal_secret' => env('PANDORA_GAMIFICATION_INTERNAL_SECRET'),

    /*
    | webhook 接收端：py-service → calendar 的 HMAC 驗證 secret
    | 同 hmac_secret（單一 shared secret，避免兩條鏈各跑各的）
    */
    'webhook_secret' => env('PANDORA_GAMIFICATION_WEBHOOK_SECRET', env('PANDORA_GAMIFICATION_HMAC_SECRET')),

    /*
    | timestamp tolerance window（防 replay 但容忍時鐘漂移）
    */
    'webhook_window_seconds' => env('PANDORA_GAMIFICATION_WEBHOOK_WINDOW_SECONDS', 300),

    /*
    | flush job 的 queue connection / queue 名稱
    */
    'queue_connection' => env('PANDORA_GAMIFICATION_QUEUE_CONNECTION', null),
    'queue_name' => env('PANDORA_GAMIFICATION_QUEUE_NAME', 'default'),

    /*
    | source_app identifier（py-service catalog 對應 calendar.* event_kind）
    */
    'app_id' => env('PANDORA_GAMIFICATION_APP_ID', 'pandora_calendar'),

    /*
    | app_opened 每日上限（client side hint；最終 cap 由 py-service catalog 決定）
    */
    'app_opened_daily_cap' => env('PANDORA_GAMIFICATION_APP_OPENED_DAILY_CAP', 3),
];
