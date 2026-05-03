<?php

/*
| Wave 13 — 隨機事件 stub（20 events）。
| narrative agent 補 dialog / flavor text。
|
| Schema：
|   key             unique slug
|   title           ≤ 12 字
|   description     朵朵語氣，禁療效詞
|   reward_coins    int
|   reward_xp       bond xp
|   weight          抽中權重（高 = 常見）
|   phase           可選 phase filter（null = 任何 phase）
*/

return [
    'events' => [
        ['key' => 'lucky_breeze', 'title' => '微風路過', 'description' => 'TODO', 'reward_coins' => 10, 'reward_xp' => 2, 'weight' => 30, 'phase' => null],
        ['key' => 'morning_dew', 'title' => '清晨露珠', 'description' => 'TODO', 'reward_coins' => 15, 'reward_xp' => 3, 'weight' => 25, 'phase' => 'follicular'],
        ['key' => 'sunset_warmth', 'title' => '黃昏暖陽', 'description' => 'TODO', 'reward_coins' => 15, 'reward_xp' => 3, 'weight' => 25, 'phase' => null],
        ['key' => 'pet_brings_treasure', 'title' => '寵物撿到寶', 'description' => 'TODO', 'reward_coins' => 25, 'reward_xp' => 5, 'weight' => 20, 'phase' => null],
        ['key' => 'starry_night', 'title' => '繁星之夜', 'description' => 'TODO', 'reward_coins' => 20, 'reward_xp' => 4, 'weight' => 20, 'phase' => 'luteal'],
        ['key' => 'rainy_cocoa', 'title' => '雨天可可', 'description' => 'TODO', 'reward_coins' => 20, 'reward_xp' => 4, 'weight' => 15, 'phase' => 'menstrual'],
        ['key' => 'good_news_letter', 'title' => '好消息來信', 'description' => 'TODO', 'reward_coins' => 30, 'reward_xp' => 6, 'weight' => 12, 'phase' => null],
        ['key' => 'forest_walk', 'title' => '森林散步', 'description' => 'TODO', 'reward_coins' => 25, 'reward_xp' => 5, 'weight' => 15, 'phase' => null],
        ['key' => 'lucky_coin', 'title' => '掉到的銅板', 'description' => 'TODO', 'reward_coins' => 40, 'reward_xp' => 5, 'weight' => 10, 'phase' => null],
        ['key' => 'shooting_star', 'title' => '流星', 'description' => 'TODO', 'reward_coins' => 50, 'reward_xp' => 8, 'weight' => 8, 'phase' => null],
        ['key' => 'rainbow', 'title' => '雨後彩虹', 'description' => 'TODO', 'reward_coins' => 35, 'reward_xp' => 6, 'weight' => 10, 'phase' => null],
        ['key' => 'pet_dance', 'title' => '寵物跳舞', 'description' => 'TODO', 'reward_coins' => 25, 'reward_xp' => 8, 'weight' => 15, 'phase' => null],
        ['key' => 'old_friend', 'title' => '老朋友來信', 'description' => 'TODO', 'reward_coins' => 30, 'reward_xp' => 5, 'weight' => 10, 'phase' => null],
        ['key' => 'fragrant_tea', 'title' => '香氣茶湯', 'description' => 'TODO', 'reward_coins' => 20, 'reward_xp' => 3, 'weight' => 18, 'phase' => 'luteal'],
        ['key' => 'cycle_companion', 'title' => '同步的女孩', 'description' => 'TODO', 'reward_coins' => 30, 'reward_xp' => 5, 'weight' => 8, 'phase' => 'menstrual'],
        ['key' => 'bedtime_lullaby', 'title' => '安眠曲', 'description' => 'TODO', 'reward_coins' => 20, 'reward_xp' => 4, 'weight' => 15, 'phase' => null],
        ['key' => 'butterfly_visit', 'title' => '蝴蝶造訪', 'description' => 'TODO', 'reward_coins' => 25, 'reward_xp' => 4, 'weight' => 12, 'phase' => 'ovulation'],
        ['key' => 'winter_blanket', 'title' => '冬日毯子', 'description' => 'TODO', 'reward_coins' => 25, 'reward_xp' => 4, 'weight' => 10, 'phase' => null],
        ['key' => 'first_snow', 'title' => '初雪', 'description' => 'TODO', 'reward_coins' => 60, 'reward_xp' => 10, 'weight' => 5, 'phase' => null],
        ['key' => 'aurora', 'title' => '極光', 'description' => 'TODO', 'reward_coins' => 100, 'reward_xp' => 20, 'weight' => 2, 'phase' => null],
    ],

    'roll_chance' => 0.15, // 15% 機率每天觸發
    'cooldown_hours' => 24,
];
