<?php

/*
| Wave 13 — SkillPath quest content（narrative content live）
|
| 3 條路徑：fertility / wellness / beauty
| 每條 10 個 quest，循序漸進。
|
| Schema（向後相容既有 SkillPathService consumer）：
|   key                  unique slug
|   title                ≤ 12 字
|   description          朵朵語氣
|   trigger              cycles / streak / bbt_count / symptom_unique_keys ...
|   trigger_value        數值
|   reward_coins         完成獎勵
|   reward_xp            bond xp
|   reward_outfit_code   解的衣服 code（可 null）
|   dodo_intro           開始 quest 時朵朵的話
|   dodo_complete        完成時朵朵的話
|
| 撰寫規範：0 療效詞 / 0 商品名 / 0 加盟暗示
*/

return [
    'paths' => [

        // ============================================================
        // FERTILITY — 備孕 / 認識週期
        // ============================================================
        'fertility' => [
            'label' => '備孕路徑',
            'tagline' => '陪妳一起讀懂身體節律',
            'description' => '不論妳是備孕、避孕、或只是想真的認識身體，這條路徑帶妳認識 BBT、分泌物、排卵窗——身體最細的訊號。',
            'preferred_action_types' => ['track', 'sleep'],
            'photo_journal_boost_phase' => 'ovulation',
            'quests' => [
                ['key' => 'fertility_q1', 'title' => '記錄第 1 個週期',
                 'description' => '完成 1 個完整週期紀錄（從經期開始到下次經期前）。這是認識自己的第一步。',
                 'trigger' => 'cycles', 'trigger_value' => 1,
                 'reward_coins' => 30, 'reward_xp' => 10, 'reward_outfit_code' => null,
                 'dodo_intro' => '這條路一開始很簡單——記完一整個週期。朵朵幫妳看著。',
                 'dodo_complete' => '一個週期完成了。妳的身體有自己的歌，妳開始學會聽了。'],

                ['key' => 'fertility_q2', 'title' => '量 7 天 BBT',
                 'description' => '連續 7 天清晨量基礎體溫。BBT 是看見排卵的第一個工具。',
                 'trigger' => 'bbt_count', 'trigger_value' => 7,
                 'reward_coins' => 50, 'reward_xp' => 15, 'reward_outfit_code' => null,
                 'dodo_intro' => '清晨醒來先別下床，含著體溫計三分鐘。朵朵幫妳記。',
                 'dodo_complete' => '7 天 BBT 完成。妳已經比 90% 的朋友更認識自己了。'],

                ['key' => 'fertility_q3', 'title' => '記下 3 種分泌物',
                 'description' => '紀錄 3 種不同的分泌物變化。乾燥 → 乳狀 → 蛋清狀，是排卵窗的主旋律。',
                 'trigger' => 'symptom_unique_keys', 'trigger_value' => 3,
                 'reward_coins' => 50, 'reward_xp' => 15, 'reward_outfit_code' => null,
                 'dodo_intro' => '今天起，每天上廁所時看一眼。妳會發現身體一直在說話。',
                 'dodo_complete' => '3 種分泌物紀錄好了。蛋清狀那天最珍貴，妳找到了。'],

                ['key' => 'fertility_q4', 'title' => '完成第 1 次排卵期記錄',
                 'description' => '紀錄一次完整的排卵期觀察（含至少 2 個排卵訊號）。',
                 'trigger' => 'ovulation_logged', 'trigger_value' => 1,
                 'reward_coins' => 80, 'reward_xp' => 20, 'reward_outfit_code' => null,
                 'dodo_intro' => '排卵期是身體最有光的幾天。記下來，下次妳就會自己認得。',
                 'dodo_complete' => '排卵窗認得了。妳的身體節奏開始浮現了。'],

                ['key' => 'fertility_q5', 'title' => '雙相 BBT 偵測',
                 'description' => 'BBT 出現雙相波形（排卵後上升）。是身體在跟妳說「我這個月有排卵」。',
                 'trigger' => 'biphasic_detected', 'trigger_value' => 1,
                 'reward_coins' => 100, 'reward_xp' => 30, 'reward_outfit_code' => 'morning_thermometer_charm',
                 'dodo_intro' => '繼續量。雙相 BBT 出現的那天，朵朵會替妳開心一整天。',
                 'dodo_complete' => '雙相出現了。妳的身體很努力，朵朵看見了。'],

                ['key' => 'fertility_q6', 'title' => '完成 3 個週期',
                 'description' => '紀錄 3 個完整週期。3 個週期的數據開始有規律可以看了。',
                 'trigger' => 'cycles', 'trigger_value' => 3,
                 'reward_coins' => 120, 'reward_xp' => 30, 'reward_outfit_code' => null,
                 'dodo_intro' => '3 個週期是身體規律的最小單位，繼續走，朵朵在。',
                 'dodo_complete' => '3 個週期完成。妳會發現自己的身體有自己的脾氣，那很可愛。'],

                ['key' => 'fertility_q7', 'title' => '邀請伴侶查看',
                 'description' => '開啟伴侶視窗，讓親近的人也認識妳的節律。是備孕路上的重要一步。',
                 'trigger' => 'partner_share_enabled', 'trigger_value' => 1,
                 'reward_coins' => 150, 'reward_xp' => 40, 'reward_outfit_code' => null,
                 'dodo_intro' => '一個人走可以快，兩個人走才走得遠。要不要邀請他/她進來？',
                 'dodo_complete' => '伴侶看見了。從今天起，妳不孤單。'],

                ['key' => 'fertility_q8', 'title' => '完成 6 個週期',
                 'description' => '紀錄 6 個完整週期。半年的數據能看出更深的節奏。',
                 'trigger' => 'cycles', 'trigger_value' => 6,
                 'reward_coins' => 200, 'reward_xp' => 50, 'reward_outfit_code' => null,
                 'dodo_intro' => '6 個週期不是小數字。妳已經是專家級的「自己」了。',
                 'dodo_complete' => '6 個週期完成。半年來身體的所有故事，朵朵替妳收好。'],

                ['key' => 'fertility_q9', 'title' => '完成 12 個週期',
                 'description' => '紀錄 12 個完整週期。一整年的數據。',
                 'trigger' => 'cycles', 'trigger_value' => 12,
                 'reward_coins' => 400, 'reward_xp' => 80, 'reward_outfit_code' => 'cycle_master_obi',
                 'dodo_intro' => '一年了。朵朵想跟妳走完。',
                 'dodo_complete' => '一年的週期完成。妳已經是自己身體最懂的人。'],

                ['key' => 'fertility_q10', 'title' => '備孕路徑畢業',
                 'description' => '紀錄滿 24 個週期。兩年的累積，是真正的「身體之友」。',
                 'trigger' => 'cycles', 'trigger_value' => 24,
                 'reward_coins' => 800, 'reward_xp' => 150, 'reward_outfit_code' => 'fertility_path_graduation',
                 'dodo_intro' => '兩年。朵朵替妳屏住呼吸。',
                 'dodo_complete' => '畢業了。從今天起，妳是自己身體最懂的人。朵朵替妳開心。'],
            ],
        ],

        // ============================================================
        // WELLNESS — 健康全方位
        // ============================================================
        'wellness' => [
            'label' => '健康路徑',
            'tagline' => '把睡眠 / 運動 / 心情接回來',
            'description' => '健康不是吃了什麼，是把睡眠 / 運動 / 心情這 3 個基礎接回來。這條路徑陪妳練成日常習慣。',
            'preferred_action_types' => ['sleep', 'move', 'relax'],
            'photo_journal_boost_phase' => null,
            'quests' => [
                ['key' => 'wellness_q1', 'title' => '完成 1 次睡眠記錄',
                 'description' => '同步 1 次睡眠資料，認識自己睡得怎麼樣。',
                 'trigger' => 'health_samples', 'trigger_value' => 1,
                 'reward_coins' => 30, 'reward_xp' => 10, 'reward_outfit_code' => null,
                 'dodo_intro' => '從睡眠開始最容易。手錶或手機就能記。',
                 'dodo_complete' => '第一筆睡眠資料。朵朵替妳開心。'],

                ['key' => 'wellness_q2', 'title' => '連 3 天記情緒',
                 'description' => '連續 3 天紀錄當下心情。情緒是身體訊號之一。',
                 'trigger' => 'mood_streak', 'trigger_value' => 3,
                 'reward_coins' => 50, 'reward_xp' => 15, 'reward_outfit_code' => null,
                 'dodo_intro' => '今天心情怎樣？選一個貼紙就好。',
                 'dodo_complete' => '3 天連續記情緒。妳開始懂自己心情的形狀了。'],

                ['key' => 'wellness_q3', 'title' => '完成 5 個 relax 行動',
                 'description' => '完成 5 個朵朵推薦的 relax 類行動（熱敷、box breathing、泡腳等）。',
                 'trigger' => 'action_type_completed', 'trigger_value' => 5,
                 'reward_coins' => 60, 'reward_xp' => 15, 'reward_outfit_code' => null,
                 'dodo_intro' => '放鬆是練習。每天 5 分鐘就好。',
                 'dodo_complete' => '5 個 relax 行動完成。妳對自己更溫柔了一點。'],

                ['key' => 'wellness_q4', 'title' => '走滿 7 天步數',
                 'description' => '連續 7 天有步數紀錄（不一定要走多，有走就算）。',
                 'trigger' => 'health_sync_days', 'trigger_value' => 7,
                 'reward_coins' => 80, 'reward_xp' => 20, 'reward_outfit_code' => null,
                 'dodo_intro' => '不追配速、不追步數。出門呼吸 5 分鐘也算。',
                 'dodo_complete' => '7 天都有走。身體會記得這份善意。'],

                ['key' => 'wellness_q5', 'title' => '連 14 天打卡',
                 'description' => '連續 14 天打開 App 紀錄至少一個訊號。',
                 'trigger' => 'streak', 'trigger_value' => 14,
                 'reward_coins' => 100, 'reward_xp' => 25, 'reward_outfit_code' => 'fortnight_pendant',
                 'dodo_intro' => '14 天是習慣形成的最小單位。朵朵陪妳走。',
                 'dodo_complete' => '14 天連勝。妳變成「會看見自己的人」了。'],

                ['key' => 'wellness_q6', 'title' => '完成 10 個 sleep 行動',
                 'description' => '完成 10 個睡眠相關的朵朵建議行動。',
                 'trigger' => 'action_type_completed', 'trigger_value' => 10,
                 'reward_coins' => 120, 'reward_xp' => 30, 'reward_outfit_code' => null,
                 'dodo_intro' => '今晚早 30 分鐘睡？放下手機？挑一個簡單的開始。',
                 'dodo_complete' => '10 個睡眠行動。妳的夜晚開始有規矩了。'],

                ['key' => 'wellness_q7', 'title' => '連 30 天打卡',
                 'description' => '連續 30 天打卡，習慣已經長在妳身上。',
                 'trigger' => 'streak', 'trigger_value' => 30,
                 'reward_coins' => 200, 'reward_xp' => 50, 'reward_outfit_code' => null,
                 'dodo_intro' => '一個月。妳會發現連勝不是負擔，是陪伴。',
                 'dodo_complete' => '30 天。這個習慣不會走了，會跟妳一輩子。'],

                ['key' => 'wellness_q8', 'title' => '完成 30 個 move 行動',
                 'description' => '累計 30 個運動 / 散步類朵朵建議行動。',
                 'trigger' => 'action_type_completed', 'trigger_value' => 30,
                 'reward_coins' => 250, 'reward_xp' => 60, 'reward_outfit_code' => null,
                 'dodo_intro' => '30 次出門走走，分散在好幾個月也沒關係。',
                 'dodo_complete' => '30 個 move 完成。身體在謝謝妳。'],

                ['key' => 'wellness_q9', 'title' => '連 60 天打卡',
                 'description' => '連續 60 天打卡。兩個月，妳已經是另一個人。',
                 'trigger' => 'streak', 'trigger_value' => 60,
                 'reward_coins' => 400, 'reward_xp' => 80, 'reward_outfit_code' => 'wellness_aurora_robe',
                 'dodo_intro' => '60 天，朵朵每天都在妳這裡。',
                 'dodo_complete' => '60 天連勝。妳的身體跟心，都認得自己了。'],

                ['key' => 'wellness_q10', 'title' => '健康路徑畢業',
                 'description' => '連續打卡 100 天。朵朵替妳屏住呼吸的數字。',
                 'trigger' => 'streak', 'trigger_value' => 100,
                 'reward_coins' => 800, 'reward_xp' => 150, 'reward_outfit_code' => 'wellness_path_graduation',
                 'dodo_intro' => '100 天。朵朵不催，但會等。',
                 'dodo_complete' => '畢業了。健康不是目標，是妳已經在過的生活。朵朵以妳為榮。'],
            ],
        ],

        // ============================================================
        // BEAUTY — 美容（皮膚 / 頭髮 / 體態）
        // ============================================================
        'beauty' => [
            'label' => '美容路徑',
            'tagline' => '吃對、睡飽、肌膚自然有狀態',
            'description' => '美不是擦了什麼，是吃對、睡飽、紀錄變化。這條路徑陪妳練成「跟身體做朋友」的日常。',
            'preferred_action_types' => ['eat', 'track'],
            'photo_journal_boost_phase' => 'ovulation',
            'quests' => [
                ['key' => 'beauty_q1', 'title' => '記下 1 次飲食',
                 'description' => '完成 1 次飲食類朵朵建議行動（如多一份綠色蔬菜、減一杯咖啡）。',
                 'trigger' => 'action_type_completed', 'trigger_value' => 1,
                 'reward_coins' => 30, 'reward_xp' => 10, 'reward_outfit_code' => null,
                 'dodo_intro' => '從一個小選擇開始。今天加一份蔬菜？',
                 'dodo_complete' => '第一個飲食行動。妳的選擇權回來了。'],

                ['key' => 'beauty_q2', 'title' => '連 3 天無熬夜',
                 'description' => '連續 3 天 23 點前躺床。皮膚最聽得懂的事。',
                 'trigger' => 'no_late_night_streak', 'trigger_value' => 3,
                 'reward_coins' => 50, 'reward_xp' => 15, 'reward_outfit_code' => null,
                 'dodo_intro' => '熬夜是皮膚的小偷。今晚試一次 23 點躺床。',
                 'dodo_complete' => '3 天不熬夜。臉色會自己給妳答案。'],

                ['key' => 'beauty_q3', 'title' => '完成 5 個 eat 行動',
                 'description' => '完成 5 個飲食類朵朵建議行動。',
                 'trigger' => 'action_type_completed', 'trigger_value' => 5,
                 'reward_coins' => 60, 'reward_xp' => 15, 'reward_outfit_code' => null,
                 'dodo_intro' => '吃對不難，挑一個喜歡的開始就好。',
                 'dodo_complete' => '5 個 eat 行動完成。妳開始懂身體要什麼了。'],

                ['key' => 'beauty_q4', 'title' => '進度照 3 張',
                 'description' => '在排卵期拍 3 張臉部進度照（一個週期最佳狀態的紀錄）。',
                 'trigger' => 'photo_journal_count', 'trigger_value' => 3,
                 'reward_coins' => 80, 'reward_xp' => 20, 'reward_outfit_code' => null,
                 'dodo_intro' => '排卵期皮膚最有光。拍下來，下個月可以對照。',
                 'dodo_complete' => '3 張進度照。妳會看見自己慢慢變化的樣子。'],

                ['key' => 'beauty_q5', 'title' => '完成 10 個 eat 行動',
                 'description' => '完成 10 個飲食類朵朵建議行動。',
                 'trigger' => 'action_type_completed', 'trigger_value' => 10,
                 'reward_coins' => 100, 'reward_xp' => 25, 'reward_outfit_code' => null,
                 'dodo_intro' => '習慣養成中，朵朵在。',
                 'dodo_complete' => '10 個 eat 行動。妳的飲食有自己的節奏了。'],

                ['key' => 'beauty_q6', 'title' => '進度照 7 張',
                 'description' => '累積 7 張進度照。可以看見一個週期裡皮膚的光譜了。',
                 'trigger' => 'photo_journal_count', 'trigger_value' => 7,
                 'reward_coins' => 120, 'reward_xp' => 30, 'reward_outfit_code' => null,
                 'dodo_intro' => '7 張，朵朵幫妳排成一個小相簿。',
                 'dodo_complete' => '相簿成形了。妳會發現自己每個週期都長得不一樣。'],

                ['key' => 'beauty_q7', 'title' => '連 30 天打卡',
                 'description' => '連續 30 天打卡。一個月的累積開始有形狀。',
                 'trigger' => 'streak', 'trigger_value' => 30,
                 'reward_coins' => 200, 'reward_xp' => 50, 'reward_outfit_code' => 'glow_camellia_pin',
                 'dodo_intro' => '30 天。朵朵替妳數。',
                 'dodo_complete' => '30 天連勝。妳的「美」是長在身體裡的，不是擦上去的。'],

                ['key' => 'beauty_q8', 'title' => '進度照 15 張',
                 'description' => '累積 15 張進度照。半年的紀錄，會看見不同季節的妳。',
                 'trigger' => 'photo_journal_count', 'trigger_value' => 15,
                 'reward_coins' => 250, 'reward_xp' => 60, 'reward_outfit_code' => null,
                 'dodo_intro' => '15 張代表半年。朵朵替妳收好每一張。',
                 'dodo_complete' => '半年的妳，朵朵看完了。每一張都是真的。'],

                ['key' => 'beauty_q9', 'title' => '完成 30 個 eat 行動',
                 'description' => '累計 30 個飲食類朵朵建議行動。',
                 'trigger' => 'action_type_completed', 'trigger_value' => 30,
                 'reward_coins' => 400, 'reward_xp' => 80, 'reward_outfit_code' => null,
                 'dodo_intro' => '30 個吃對的選擇。朵朵以妳為榮。',
                 'dodo_complete' => '30 個 eat 行動完成。妳的飲食是有靈魂的。'],

                ['key' => 'beauty_q10', 'title' => '美容路徑畢業',
                 'description' => '連續打卡 90 天。三個月後，妳的「美」是真的長進去了。',
                 'trigger' => 'streak', 'trigger_value' => 90,
                 'reward_coins' => 800, 'reward_xp' => 150, 'reward_outfit_code' => 'beauty_path_graduation',
                 'dodo_intro' => '90 天。朵朵在最後一段路陪妳。',
                 'dodo_complete' => '畢業了。從今天起，妳的「美」不需要任何人定義。朵朵以妳為榮。'],
            ],
        ],
    ],

    'switch_cooldown_days' => 30,    // 每 30 天最多切 1 次
    'recommender_weight' => 0.2,     // ActionRecommender preferred_action_types 加權
];
