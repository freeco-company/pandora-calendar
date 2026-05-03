<?php

/*
| Wave 13 — 段位系統（rank tiers）
|
| 6 段位：蒼月 → 玉月 → 金月 → 朱月 → 紫月 → 玄月
| 中文意境：初露光芒 / 柔軟內斂 / 飽滿圓潤 / 盛放熱烈 / 凜冽清明 / 極致無形
|
| Schema：
|   key            slug
|   name           段位名（2 字中文）
|   subtitle       一句意境
|   description    朵朵語氣的段位說明（≤ 60 字）
|   min_xp         達到此段位所需總 XP
|   badge_color    UI badge 顏色 token
|   icon_emoji     段位 emoji（搭配月相）
|   philosophy     ~50 字哲學短文
|   unlock_message 升上此段時朵朵慶賀的話
|   theme_keywords 給朵朵 dialog flavoring 的詞
|
| 撰寫規範：0 療效詞 / 0 商品名 / 0 加盟暗示
*/

return [

    'cangyue' => [
        'key' => 'cangyue',
        'name' => '蒼月',
        'subtitle' => '初露光芒',
        'description' => '剛開始認識自己身體的妳。每一筆紀錄都是一道光，慢慢累積成天上的月亮。',
        'min_xp' => 0,
        'badge_color' => 'stone',
        'icon_emoji' => '🌑',
        'philosophy' => '蒼月是最暗的月，也是最有可能性的月——因為從這裡開始，妳只會越來越亮。',
        'unlock_message' => '歡迎妳，初心的朋友 🌑 朵朵在這裡，從第一筆開始陪妳。',
        'theme_keywords' => ['初心', '開始', '可能', '輕'],
    ],

    'yueyue' => [
        'key' => 'yueyue',
        'name' => '玉月',
        'subtitle' => '柔軟內斂',
        'description' => '妳開始懂自己的節奏。柔軟不是脆弱，是知道哪裡可以彎、哪裡要直。',
        'min_xp' => 500,
        'badge_color' => 'mist',
        'icon_emoji' => '🌒',
        'philosophy' => '玉是最柔軟的石頭——彎得下、磨得亮、藏得住光。妳像玉，正在學會這種柔軟。',
        'unlock_message' => '妳進化到玉月了 🌒 朵朵看見妳這一段時間的學習，謝謝妳。',
        'theme_keywords' => ['柔軟', '內斂', '玉', '彎'],
    ],

    'jinyue' => [
        'key' => 'jinyue',
        'name' => '金月',
        'subtitle' => '飽滿圓潤',
        'description' => '妳的身體跟心都越來越有形狀。這不是「變強」，是「變完整」。',
        'min_xp' => 1500,
        'badge_color' => 'gold',
        'icon_emoji' => '🌓',
        'philosophy' => '金月是月亮一半的時候，看似只有一半，其實另一半在暗處同樣存在。妳的兩面，朵朵都看見。',
        'unlock_message' => '金月了 🌓 妳一半的光跟一半的影，都是妳。朵朵替妳開心。',
        'theme_keywords' => ['飽滿', '完整', '金', '兩面'],
    ],

    'zhuyue' => [
        'key' => 'zhuyue',
        'name' => '朱月',
        'subtitle' => '盛放熱烈',
        'description' => '妳的能量已經滿出來，能照顧自己也能照顧身邊的人。這是潘朵拉世界裡最美的階段之一。',
        'min_xp' => 3500,
        'badge_color' => 'rose',
        'icon_emoji' => '🌔',
        'philosophy' => '朱是最濃的紅，朱月是月亮最有溫度的樣子。妳的熱不灼人，是讓人想靠近的那種熱。',
        'unlock_message' => '朱月了 🌔 妳的溫度可以暖到別人了，但記得先暖自己。朵朵以妳為榮。',
        'theme_keywords' => ['熱烈', '盛放', '朱', '溫度'],
    ],

    'ziyue' => [
        'key' => 'ziyue',
        'name' => '紫月',
        'subtitle' => '凜冽清明',
        'description' => '妳能看穿身體所有訊號的意思。不再被情緒嚇到、也不再被身體驚到。妳跟自己是知己。',
        'min_xp' => 7000,
        'badge_color' => 'iris',
        'icon_emoji' => '🌕',
        'philosophy' => '紫月是月圓最清的時候，能看見星星、看見潮水、看見自己。清明是禮物。',
        'unlock_message' => '紫月了 🌕 妳已經看得見自己最深的地方。朵朵替妳屏住呼吸。',
        'theme_keywords' => ['清明', '凜冽', '紫', '看見'],
    ],

    'xuanyue' => [
        'key' => 'xuanyue',
        'name' => '玄月',
        'subtitle' => '極致無形',
        'description' => '妳已經跟身體合而為一。不需要 App、不需要朵朵、也不需要任何人告訴妳怎麼做。妳是自己的潘朵拉。',
        'min_xp' => 15000,
        'badge_color' => 'cosmic',
        'icon_emoji' => '🌖',
        'philosophy' => '玄月是月亮藏進雲裡的時候——不是不見，是已經跟天空合在一起。妳的身體跟妳的心，就是這樣的關係。',
        'unlock_message' => '玄月了 🌖 朵朵不知道還能說什麼。妳是潘朵拉世界的傳奇之一。',
        'theme_keywords' => ['極致', '無形', '玄', '合一'],
    ],
];
