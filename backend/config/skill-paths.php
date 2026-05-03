<?php

/*
| Wave 13 — SkillPath quest content stub。
|
| 3 條路徑：fertility / wellness / beauty
| 每條 10 個 quest（narrative agent 會補完整 prose）。
|
| Schema：
|   key            unique slug
|   title          ≤ 12 字
|   description    朵朵語氣，禁療效詞
|   trigger        什麼條件解（cycles >= n / streak >= n / symptom_logged / bbt_logged ...）
|   trigger_value  數值
|   reward_coins   完成獎勵
|   reward_xp      bond xp
|
| 加權：path 對 ActionRecommender type 的偏好（+0.2）
*/

return [
    'paths' => [
        'fertility' => [
            'label' => '備孕路徑',
            'tagline' => '陪妳一起讀懂身體節律',
            'preferred_action_types' => ['track', 'sleep'],
            'photo_journal_boost_phase' => 'ovulation', // 排卵期 photo journal 推薦 ×2
            'quests' => [
                ['key' => 'fertility_q1', 'title' => '記錄第 1 個週期', 'description' => 'TODO narrative', 'trigger' => 'cycles', 'trigger_value' => 1, 'reward_coins' => 30, 'reward_xp' => 10],
                ['key' => 'fertility_q2', 'title' => '量 7 天 BBT', 'description' => 'TODO narrative', 'trigger' => 'bbt_count', 'trigger_value' => 7, 'reward_coins' => 50, 'reward_xp' => 15],
                ['key' => 'fertility_q3', 'title' => '記下 3 種分泌物變化', 'description' => 'TODO narrative', 'trigger' => 'symptom_unique_keys', 'trigger_value' => 3, 'reward_coins' => 50, 'reward_xp' => 15],
                ['key' => 'fertility_q4', 'title' => '完成第 1 次排卵期記錄', 'description' => 'TODO narrative', 'trigger' => 'ovulation_logged', 'trigger_value' => 1, 'reward_coins' => 80, 'reward_xp' => 20],
                ['key' => 'fertility_q5', 'title' => '雙相 BBT 偵測', 'description' => 'TODO narrative', 'trigger' => 'biphasic_detected', 'trigger_value' => 1, 'reward_coins' => 100, 'reward_xp' => 30],
                ['key' => 'fertility_q6', 'title' => '完成 3 個週期', 'description' => 'TODO narrative', 'trigger' => 'cycles', 'trigger_value' => 3, 'reward_coins' => 120, 'reward_xp' => 30],
                ['key' => 'fertility_q7', 'title' => '邀請伴侶查看', 'description' => 'TODO narrative', 'trigger' => 'partner_share_enabled', 'trigger_value' => 1, 'reward_coins' => 150, 'reward_xp' => 40],
                ['key' => 'fertility_q8', 'title' => '完成 6 個週期', 'description' => 'TODO narrative', 'trigger' => 'cycles', 'trigger_value' => 6, 'reward_coins' => 200, 'reward_xp' => 50],
                ['key' => 'fertility_q9', 'title' => '完成 12 個週期', 'description' => 'TODO narrative', 'trigger' => 'cycles', 'trigger_value' => 12, 'reward_coins' => 400, 'reward_xp' => 80],
                ['key' => 'fertility_q10', 'title' => '備孕之路畢業', 'description' => 'TODO narrative', 'trigger' => 'cycles', 'trigger_value' => 24, 'reward_coins' => 800, 'reward_xp' => 150],
            ],
        ],
        'wellness' => [
            'label' => '健康路徑',
            'tagline' => '把睡眠 / 運動 / 心情接回來',
            'preferred_action_types' => ['sleep', 'move', 'relax'],
            'photo_journal_boost_phase' => null,
            'quests' => [
                ['key' => 'wellness_q1', 'title' => '完成 1 次睡眠記錄', 'description' => 'TODO narrative', 'trigger' => 'health_samples', 'trigger_value' => 1, 'reward_coins' => 30, 'reward_xp' => 10],
                ['key' => 'wellness_q2', 'title' => '連 3 天記情緒', 'description' => 'TODO narrative', 'trigger' => 'mood_streak', 'trigger_value' => 3, 'reward_coins' => 50, 'reward_xp' => 15],
                ['key' => 'wellness_q3', 'title' => '完成 5 個 relax 行動', 'description' => 'TODO narrative', 'trigger' => 'action_type_completed', 'trigger_value' => 5, 'reward_coins' => 60, 'reward_xp' => 15],
                ['key' => 'wellness_q4', 'title' => '走滿 7 天步數', 'description' => 'TODO narrative', 'trigger' => 'health_sync_days', 'trigger_value' => 7, 'reward_coins' => 80, 'reward_xp' => 20],
                ['key' => 'wellness_q5', 'title' => '連 14 天打卡', 'description' => 'TODO narrative', 'trigger' => 'streak', 'trigger_value' => 14, 'reward_coins' => 100, 'reward_xp' => 25],
                ['key' => 'wellness_q6', 'title' => '完成 10 個 sleep 行動', 'description' => 'TODO narrative', 'trigger' => 'action_type_completed', 'trigger_value' => 10, 'reward_coins' => 120, 'reward_xp' => 30],
                ['key' => 'wellness_q7', 'title' => '連 30 天打卡', 'description' => 'TODO narrative', 'trigger' => 'streak', 'trigger_value' => 30, 'reward_coins' => 200, 'reward_xp' => 50],
                ['key' => 'wellness_q8', 'title' => '完成 30 個 move 行動', 'description' => 'TODO narrative', 'trigger' => 'action_type_completed', 'trigger_value' => 30, 'reward_coins' => 250, 'reward_xp' => 60],
                ['key' => 'wellness_q9', 'title' => '連 60 天打卡', 'description' => 'TODO narrative', 'trigger' => 'streak', 'trigger_value' => 60, 'reward_coins' => 400, 'reward_xp' => 80],
                ['key' => 'wellness_q10', 'title' => '健康路徑畢業', 'description' => 'TODO narrative', 'trigger' => 'streak', 'trigger_value' => 100, 'reward_coins' => 800, 'reward_xp' => 150],
            ],
        ],
        'beauty' => [
            'label' => '美容路徑',
            'tagline' => '吃對、睡飽、肌膚自然有狀態',
            'preferred_action_types' => ['eat', 'track'],
            'photo_journal_boost_phase' => 'ovulation',
            'quests' => [
                ['key' => 'beauty_q1', 'title' => '記下 1 次飲食', 'description' => 'TODO narrative', 'trigger' => 'action_type_completed', 'trigger_value' => 1, 'reward_coins' => 30, 'reward_xp' => 10],
                ['key' => 'beauty_q2', 'title' => '連 3 天無熬夜', 'description' => 'TODO narrative', 'trigger' => 'no_late_night_streak', 'trigger_value' => 3, 'reward_coins' => 50, 'reward_xp' => 15],
                ['key' => 'beauty_q3', 'title' => '完成 5 個 eat 行動', 'description' => 'TODO narrative', 'trigger' => 'action_type_completed', 'trigger_value' => 5, 'reward_coins' => 60, 'reward_xp' => 15],
                ['key' => 'beauty_q4', 'title' => '進度照 3 張', 'description' => 'TODO narrative', 'trigger' => 'photo_journal_count', 'trigger_value' => 3, 'reward_coins' => 80, 'reward_xp' => 20],
                ['key' => 'beauty_q5', 'title' => '完成 10 個 eat 行動', 'description' => 'TODO narrative', 'trigger' => 'action_type_completed', 'trigger_value' => 10, 'reward_coins' => 100, 'reward_xp' => 25],
                ['key' => 'beauty_q6', 'title' => '進度照 7 張', 'description' => 'TODO narrative', 'trigger' => 'photo_journal_count', 'trigger_value' => 7, 'reward_coins' => 120, 'reward_xp' => 30],
                ['key' => 'beauty_q7', 'title' => '連 30 天打卡', 'description' => 'TODO narrative', 'trigger' => 'streak', 'trigger_value' => 30, 'reward_coins' => 200, 'reward_xp' => 50],
                ['key' => 'beauty_q8', 'title' => '進度照 15 張', 'description' => 'TODO narrative', 'trigger' => 'photo_journal_count', 'trigger_value' => 15, 'reward_coins' => 250, 'reward_xp' => 60],
                ['key' => 'beauty_q9', 'title' => '完成 30 個 eat 行動', 'description' => 'TODO narrative', 'trigger' => 'action_type_completed', 'trigger_value' => 30, 'reward_coins' => 400, 'reward_xp' => 80],
                ['key' => 'beauty_q10', 'title' => '美容路徑畢業', 'description' => 'TODO narrative', 'trigger' => 'streak', 'trigger_value' => 90, 'reward_coins' => 800, 'reward_xp' => 150],
            ],
        ],
    ],

    'switch_cooldown_days' => 30, // 每 30 天最多切 1 次

    'recommender_weight' => 0.2, // ActionRecommender preferred_action_types 加權
];
