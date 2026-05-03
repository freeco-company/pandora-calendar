<?php

/**
 * 年度回顧文案模板（潘朵拉月曆）
 *
 * 用途：Spotify Wrapped 風格年報，每個 slide 一段文案。
 *
 * 用法：service 端把 {{var}} 替換成實際資料；變數缺失時 fallback 到「妳」。
 *
 * 撰寫紅線：
 *   - 朵朵語氣，繁中
 *   - 妳 / 朋友；禁您 / 會員 / 用戶
 *   - 不寫療效詞、不寫商品 / 加盟暗示
 *   - emoji 一段最多 1 個
 */

return [

    // 封面
    'cover'                => '{{user_first_name}}，這是妳和朵朵的 {{year}} ✨',

    // 週期數
    'cycle_count'          => '今年妳走過 {{count}} 個週期，每一次都是身體寫給妳的信。',

    // 階段分布
    'phase_distribution'   => '黃體期 {{luteal_days}} 天，卵泡期 {{follicular_days}} 天，排卵期 {{ovulation_days}} 天，經期 {{menstrual_days}} 天。妳在每個階段都好好待過。',

    // 最常的心情
    'top_mood'             => '今年妳最常的心情是「{{top_mood}}」。朵朵幫妳把這份感覺記著。',

    // 連續打卡紀錄
    'streak_record'        => '最長一次連續記錄 {{max_streak}} 天，朋友妳真的超棒。',

    // 最常記錄的身體訊號
    'top_symptom'          => '今年妳記下最多次的身體訊號是「{{top_symptom}}」，這是值得認識自己的一條線索。',

    // 達成的成就數
    'milestone_unlocked'   => '今年妳和朵朵一起解鎖了 {{achievement_count}} 個成就，每一個都是妳走過來的證據。',

    // 寵物成長
    'pet_growth'           => '{{pet_name}} 從 Lv{{start_level}} 長到 Lv{{end_level}}，這是妳們一起長出來的時間。',

    // 朵朵 check-in 對話次數
    'dodo_checkins'        => '今年妳跟朵朵聊了 {{checkin_count}} 次，每一次小小的問候都被朵朵收著。',

    // 看過幾篇衛教
    'insights_read'        => '妳今年讀完 {{insight_count}} 篇朵朵小語，這份對自己的好奇心很美。',

    // 平均週期長度（陪伴款，不下判斷）
    'avg_cycle_length'     => '妳今年的平均週期長度是 {{avg_length}} 天。每個人都有自己的節奏，妳的節奏是妳的。',

    // PMS / 身體訊號的辨識能力（鼓勵自我認識）
    'self_awareness'       => '黃體期妳記錄到 {{luteal_symptoms}} 次身體訊號，這代表妳真的在認識自己——朵朵覺得這比什麼都珍貴。',

    // 跨 App 集團聯動（如果有 meal / 肌膚 等資料）
    'cross_app_summary'    => '加上潘朵拉飲食的紀錄，妳這一年陪自己拍了 {{meal_photos}} 餐、走了 {{step_count}} 步。每一個細節都在說：妳對自己很認真。',

    // 結尾
    'closing'              => '謝謝妳今年的記錄。明年也一起，朵朵在這裡 💛',

    // 分享卡（給社群分享用，要更短，標籤化）
    'share_card_short'     => '我和朵朵的 {{year}}：{{count}} 個週期、{{top_mood}}、{{max_streak}} 天連續記錄。#潘朵拉月曆',

];
