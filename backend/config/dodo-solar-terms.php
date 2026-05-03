<?php

/*
| Wave 13 — 24 節氣 stub。
| 月份 + 大致日期（節氣每年實際日期會差 1 天，這裡用「常年中位日期」做粗略）。
| 期間 ±1 天（共 3 天 window）內完成記錄即算 participation。
|
| narrative agent 補 description / outfit unlock / dialog flavor。
*/

return [
    'terms' => [
        ['key' => 'lichun', 'label' => '立春', 'month' => 2, 'day' => 4, 'description' => 'TODO', 'outfit_unlock' => null],
        ['key' => 'yushui', 'label' => '雨水', 'month' => 2, 'day' => 19, 'description' => 'TODO', 'outfit_unlock' => null],
        ['key' => 'jingzhe', 'label' => '驚蟄', 'month' => 3, 'day' => 6, 'description' => 'TODO', 'outfit_unlock' => null],
        ['key' => 'chunfen', 'label' => '春分', 'month' => 3, 'day' => 21, 'description' => 'TODO', 'outfit_unlock' => 'cherry_blossom_kimono'],
        ['key' => 'qingming', 'label' => '清明', 'month' => 4, 'day' => 5, 'description' => 'TODO', 'outfit_unlock' => null],
        ['key' => 'guyu', 'label' => '穀雨', 'month' => 4, 'day' => 20, 'description' => 'TODO', 'outfit_unlock' => null],
        ['key' => 'lixia', 'label' => '立夏', 'month' => 5, 'day' => 6, 'description' => 'TODO', 'outfit_unlock' => null],
        ['key' => 'xiaoman', 'label' => '小滿', 'month' => 5, 'day' => 21, 'description' => 'TODO', 'outfit_unlock' => null],
        ['key' => 'mangzhong', 'label' => '芒種', 'month' => 6, 'day' => 6, 'description' => 'TODO', 'outfit_unlock' => null],
        ['key' => 'xiazhi', 'label' => '夏至', 'month' => 6, 'day' => 21, 'description' => 'TODO', 'outfit_unlock' => 'summer_yukata'],
        ['key' => 'xiaoshu', 'label' => '小暑', 'month' => 7, 'day' => 7, 'description' => 'TODO', 'outfit_unlock' => null],
        ['key' => 'dashu', 'label' => '大暑', 'month' => 7, 'day' => 23, 'description' => 'TODO', 'outfit_unlock' => null],
        ['key' => 'liqiu', 'label' => '立秋', 'month' => 8, 'day' => 8, 'description' => 'TODO', 'outfit_unlock' => null],
        ['key' => 'chushu', 'label' => '處暑', 'month' => 8, 'day' => 23, 'description' => 'TODO', 'outfit_unlock' => null],
        ['key' => 'bailu', 'label' => '白露', 'month' => 9, 'day' => 8, 'description' => 'TODO', 'outfit_unlock' => null],
        ['key' => 'qiufen', 'label' => '秋分', 'month' => 9, 'day' => 23, 'description' => 'TODO', 'outfit_unlock' => 'autumn_maple'],
        ['key' => 'hanlu', 'label' => '寒露', 'month' => 10, 'day' => 8, 'description' => 'TODO', 'outfit_unlock' => null],
        ['key' => 'shuangjiang', 'label' => '霜降', 'month' => 10, 'day' => 23, 'description' => 'TODO', 'outfit_unlock' => null],
        ['key' => 'lidong', 'label' => '立冬', 'month' => 11, 'day' => 7, 'description' => 'TODO', 'outfit_unlock' => null],
        ['key' => 'xiaoxue', 'label' => '小雪', 'month' => 11, 'day' => 22, 'description' => 'TODO', 'outfit_unlock' => null],
        ['key' => 'daxue', 'label' => '大雪', 'month' => 12, 'day' => 7, 'description' => 'TODO', 'outfit_unlock' => null],
        ['key' => 'dongzhi', 'label' => '冬至', 'month' => 12, 'day' => 22, 'description' => 'TODO', 'outfit_unlock' => 'winter_scarf'],
        ['key' => 'xiaohan', 'label' => '小寒', 'month' => 1, 'day' => 6, 'description' => 'TODO', 'outfit_unlock' => null],
        ['key' => 'dahan', 'label' => '大寒', 'month' => 1, 'day' => 20, 'description' => 'TODO', 'outfit_unlock' => null],
    ],

    'window_days' => 1, // 節氣前後各 1 天 = 3 天 window
    'participation_reward_coins' => 50,
];
