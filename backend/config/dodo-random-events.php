<?php

/*
| Wave 13 — 隨機事件 20 events（narrative content live）
|
| Schema：
|   key             unique slug
|   title           ≤ 12 字
|   description     朵朵語氣，禁療效詞
|   reward_coins    int
|   reward_xp       bond xp
|   weight          抽中權重（高 = 常見）
|   phase           可選 phase filter（null = 任何 phase）
|   rarity          common / rare / epic（顯示用）
|   dialogs         3+ 變體 — DodoEventResolver 隨機抽
|
| 撰寫規範：朵朵第一人稱、妳/朋友、0 療效詞 / 0 商品名 / 0 加盟暗示
*/

return [
    'events' => [

        // ─── COMMON（高頻 / 8）───
        [
            'key' => 'lucky_breeze',
            'title' => '微風路過',
            'description' => '今天朵朵感覺到一陣很輕的風吹過妳的肩膀，像在說「辛苦了」。',
            'reward_coins' => 10, 'reward_xp' => 2, 'weight' => 30, 'phase' => null, 'rarity' => 'common',
            'dialogs' => [
                '剛剛有一陣風路過，朵朵覺得它在輕輕拍妳的肩膀。',
                '一陣風，沒帶走什麼，只帶來一句「妳今天有被看見」。',
                '風是潘朵拉世界最常見的禮物，朵朵幫妳收下。',
            ],
        ],
        [
            'key' => 'morning_dew',
            'title' => '清晨露珠',
            'description' => '舒緩期的早上，葉尖上有一顆露珠。朵朵說那是身體在告訴妳「今天我準備好了」。',
            'reward_coins' => 15, 'reward_xp' => 3, 'weight' => 25, 'phase' => 'follicular', 'rarity' => 'common',
            'dialogs' => [
                '早上葉尖那顆露珠，朵朵覺得是給妳的小信號。',
                '露珠很小，可是它把整個天空映進去了。妳也是。',
                '今天的露珠特別清，朵朵覺得妳今天會比較有精神。',
            ],
        ],
        [
            'key' => 'sunset_warmth',
            'title' => '黃昏暖陽',
            'description' => '黃昏的光斜斜地打過來，剛剛好暖。朵朵把這一刻收起來給妳。',
            'reward_coins' => 15, 'reward_xp' => 3, 'weight' => 25, 'phase' => null, 'rarity' => 'common',
            'dialogs' => [
                '黃昏的光最溫柔。朵朵把它收進口袋，分妳一半。',
                '今天太陽下山的時候特別慢，像在等妳記筆記。',
                '暖暖的光打在臉上，朵朵覺得妳今天值得這樣被照。',
            ],
        ],
        [
            'key' => 'forest_walk',
            'title' => '森林散步',
            'description' => '朵朵今天去森林散了步，帶了一片葉子回來給妳。妳收一下，當這一週的書籤。',
            'reward_coins' => 25, 'reward_xp' => 5, 'weight' => 15, 'phase' => null, 'rarity' => 'common',
            'dialogs' => [
                '朵朵今天去森林散步，撿了一片葉子要給妳。',
                '森林很安靜，朵朵走著走著想到妳，就轉身回來了。',
                '把葉子當書籤吧，這一週的故事妳記得用它夾。',
            ],
        ],
        [
            'key' => 'pet_dance',
            'title' => '寵物跳舞',
            'description' => '妳的寵物今天忽然在房間繞圈，朵朵覺得它在跳一支只給妳看的舞。',
            'reward_coins' => 25, 'reward_xp' => 8, 'weight' => 15, 'phase' => null, 'rarity' => 'common',
            'dialogs' => [
                '它今天繞圈圈跳舞了，朵朵覺得是因為妳今天比較開心。',
                '寵物跳舞通常是想跟妳說「謝謝妳今天記得我」。',
                '看，它跳得有點傻，可是是真心的。',
            ],
        ],
        [
            'key' => 'bedtime_lullaby',
            'title' => '安眠曲',
            'description' => '朵朵今天哼了一首很舊很舊的歌，希望今晚妳睡得深一點。',
            'reward_coins' => 20, 'reward_xp' => 4, 'weight' => 15, 'phase' => null, 'rarity' => 'common',
            'dialogs' => [
                '朵朵剛剛哼了一首歌，妳沒聽到沒關係，身體會記得。',
                '這首歌是朵朵媽媽教的，今天輪到妳被哄睡。',
                '把眼睛閉起來，朵朵會在門口守到妳睡著。',
            ],
        ],
        [
            'key' => 'fragrant_tea',
            'title' => '香氣茶湯',
            'description' => '今天朵朵泡了一壺茶，香味繞了一圈才停在妳的桌上。',
            'reward_coins' => 20, 'reward_xp' => 3, 'weight' => 18, 'phase' => 'luteal', 'rarity' => 'common',
            'dialogs' => [
                '一壺茶的時間就好。朵朵陪妳坐一下。',
                '黃體期最適合慢慢喝一杯熱的，朵朵已經幫妳倒好。',
                '聞到香味了嗎？是朵朵希望妳今晚比較鬆。',
            ],
        ],
        [
            'key' => 'rainy_cocoa',
            'title' => '雨天可可',
            'description' => '經期下雨天最適合一杯熱可可。朵朵陪妳坐窗邊，什麼都不用做。',
            'reward_coins' => 20, 'reward_xp' => 4, 'weight' => 15, 'phase' => 'menstrual', 'rarity' => 'common',
            'dialogs' => [
                '外面下雨，朵朵泡好可可了。',
                '經期不用討好誰。坐著，喝完這杯就好。',
                '雨聲是免費的白噪音，朵朵覺得妳今天可以放給自己聽。',
            ],
        ],

        // ─── RARE（中頻 / 8）───
        [
            'key' => 'pet_brings_treasure',
            'title' => '寵物撿到寶',
            'description' => '寵物今天叼了一個小東西回家，亮亮的。朵朵看不懂是什麼，但它一定是想送妳。',
            'reward_coins' => 25, 'reward_xp' => 5, 'weight' => 20, 'phase' => null, 'rarity' => 'rare',
            'dialogs' => [
                '它叼回一個亮晶晶的小東西，朵朵覺得是要給妳。',
                '不要嫌它撿的東西怪，那是它的世界裡最好的禮物。',
                '收下吧。寵物的禮物不能拒絕，這是潘朵拉世界的規矩。',
            ],
        ],
        [
            'key' => 'starry_night',
            'title' => '繁星之夜',
            'description' => '今晚星星特別多，朵朵看見有一顆對著妳的窗閃了一下。',
            'reward_coins' => 20, 'reward_xp' => 4, 'weight' => 20, 'phase' => 'luteal', 'rarity' => 'rare',
            'dialogs' => [
                '今晚星星很多，朵朵幫妳數了一下，至少有一顆是妳的。',
                '黃體期的夜晚最適合看星星——它們不會催妳。',
                '抬頭看一下，朵朵在最亮的那顆旁邊揮手。',
            ],
        ],
        [
            'key' => 'good_news_letter',
            'title' => '好消息來信',
            'description' => '今天有一封信飛到朵朵手上，說「請把這份好心情轉給妳」。朵朵照做。',
            'reward_coins' => 30, 'reward_xp' => 6, 'weight' => 12, 'phase' => null, 'rarity' => 'rare',
            'dialogs' => [
                '剛剛有一封信飛來，指名要給妳。',
                '信上說「她今天值得一點好的」。朵朵舉雙手贊成。',
                '不管今天怎麼樣，這封信先放妳口袋裡。',
            ],
        ],
        [
            'key' => 'old_friend',
            'title' => '老朋友來信',
            'description' => '一個朵朵很久沒聯絡的朋友寫信來，說她也想妳了。世界很小，妳被想著。',
            'reward_coins' => 30, 'reward_xp' => 5, 'weight' => 10, 'phase' => null, 'rarity' => 'rare',
            'dialogs' => [
                '一個老朋友來信，問起妳，朵朵覺得她氣味跟妳很像。',
                '世界很小，妳被想著。不孤單。',
                '今天可以傳個訊息給一個久沒聊的人嗎？朵朵推一下。',
            ],
        ],
        [
            'key' => 'cycle_companion',
            'title' => '同步的朋友',
            'description' => '朵朵今天遇到另一個跟妳同一天經期的朋友。妳們不認識，可是身體在同一個節奏。',
            'reward_coins' => 30, 'reward_xp' => 5, 'weight' => 8, 'phase' => 'menstrual', 'rarity' => 'rare',
            'dialogs' => [
                '今天有人跟妳同一天經期。雖然妳不認識她，但妳們在一起痛。',
                '潘朵拉世界裡，永遠有人跟妳同步。',
                '不孤單。這個下午有人跟妳一起蜷在沙發上。',
            ],
        ],
        [
            'key' => 'butterfly_visit',
            'title' => '蝴蝶造訪',
            'description' => '排卵期窗外有一隻蝴蝶停了三秒。朵朵覺得它認得妳。',
            'reward_coins' => 25, 'reward_xp' => 4, 'weight' => 12, 'phase' => 'ovulation', 'rarity' => 'rare',
            'dialogs' => [
                '一隻蝴蝶停了三秒，朵朵覺得它認得妳。',
                '排卵期身體最有光，連蝴蝶都看得到。',
                '看到了嗎？是潘朵拉世界派來打招呼的。',
            ],
        ],
        [
            'key' => 'rainbow',
            'title' => '雨後彩虹',
            'description' => '剛下完雨，天邊出來一道彩虹。朵朵把它存在妳的紀錄裡。',
            'reward_coins' => 35, 'reward_xp' => 6, 'weight' => 10, 'phase' => null, 'rarity' => 'rare',
            'dialogs' => [
                '雨剛停，天邊有彩虹。朵朵幫妳看到了。',
                '雨之後的彩虹比較圓，是因為它等了很久。',
                '今天的彩虹存在妳的紀錄裡，要看的時候打開就有。',
            ],
        ],
        [
            'key' => 'winter_blanket',
            'title' => '冬日毯子',
            'description' => '冷的時候朵朵會幫妳蓋毯子。今天就算是夏天，朵朵也想幫妳蓋一條。',
            'reward_coins' => 25, 'reward_xp' => 4, 'weight' => 10, 'phase' => null, 'rarity' => 'rare',
            'dialogs' => [
                '幫妳蓋一條毯子。不冷也可以蓋，是儀式感。',
                '毯子是潘朵拉世界發明的——「我希望妳此刻被包著」。',
                '蓋好了。今天可以縮在裡面當小小的妳。',
            ],
        ],

        // ─── EPIC（低頻 / 4）───
        [
            'key' => 'lucky_coin',
            'title' => '掉到的銅板',
            'description' => '朵朵今天在路上撿到一個銅板，亮亮的。寫著「這是給她的」。',
            'reward_coins' => 40, 'reward_xp' => 5, 'weight' => 10, 'phase' => null, 'rarity' => 'epic',
            'dialogs' => [
                '撿到一個亮亮的銅板，上面寫著妳的名字。',
                '不問來歷。潘朵拉世界的銅板都是來找對的人的。',
                '今天的好運氣存進妳的存錢筒。',
            ],
        ],
        [
            'key' => 'shooting_star',
            'title' => '流星',
            'description' => '一顆流星劃過天空，朵朵替妳許了一個願：「願她今晚睡得深。」',
            'reward_coins' => 50, 'reward_xp' => 8, 'weight' => 8, 'phase' => null, 'rarity' => 'epic',
            'dialogs' => [
                '剛剛有一顆流星，朵朵替妳許願了——「願她今晚睡得深」。',
                '流星太快，所以願望要簡單。朵朵已經幫妳挑好。',
                '看到了嗎？沒看到沒關係，朵朵把它記下來了。',
            ],
        ],
        [
            'key' => 'first_snow',
            'title' => '初雪',
            'description' => '今年的第一片雪落下，朵朵把它接住，變成一張收藏卡放進妳的圖鑑。',
            'reward_coins' => 60, 'reward_xp' => 10, 'weight' => 5, 'phase' => null, 'rarity' => 'epic',
            'dialogs' => [
                '今年的第一片雪。朵朵接住了。',
                '初雪只有一年一次，朵朵把它變成卡片給妳收。',
                '冷的時候打開圖鑑看這張卡，朵朵覺得會比較暖。',
            ],
        ],
        [
            'key' => 'aurora',
            'title' => '極光',
            'description' => '潘朵拉世界今天有極光。朵朵特別把妳叫醒，請看一眼。',
            'reward_coins' => 100, 'reward_xp' => 20, 'weight' => 2, 'phase' => null, 'rarity' => 'epic',
            'dialogs' => [
                '極光來了，朵朵把妳叫醒。請看一眼。',
                '一年最多兩次極光，朵朵不希望妳錯過。',
                '看到了？這個夜晚會記得很久。朵朵也是。',
            ],
        ],
    ],

    'roll_chance' => 0.15,   // 15% 機率每天觸發
    'cooldown_hours' => 24,
];
