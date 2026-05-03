<?php

/*
| Daily Action Engine — 每日量身行動卡（潘朵拉月曆）
|
| 用途：
|   每天朵朵根據用戶當下 phase + cycle_day 推 1 個量身行動，
|   用戶完成 → 紀錄體感 → 累積成「妳的 protocol」（個人化健康教練 retention engine）
|
| Schema（每張卡）：
|   key                 → unique slug（陣列 key）
|   phase               → array<string>，可命中的 phase（menstrual / follicular / ovulation / luteal / late）
|   day_offset_min      → 該 phase 第幾天起可推（0-base）
|   day_offset_max      → 該 phase 第幾天止可推
|   type                → sleep / move / eat / relax / track / learn / connect
|   title               → 10 字以內
|   body                → 40 字以內，描述為什麼要做
|   expected_benefit    → 完成後的實感（不是療效）
|   time_minutes        → 行動本身需要的時間（0 = 不額外占時間）
|   difficulty          → easy / medium / hard
|
| ⚠️ schema 升級備註：
|   v1 stub（少數 phase=string + duration_min + type=care/nourish/rest/mindful）已被本檔 v2 取代。
|   ActionRecommender / ActionFeedbackProcessor consumer 需對齊新 key。
|
| 撰寫紅線（已 self-audit，跑過 LegalContentSanitizer）：
|   - 用「妳 / 朋友 / 夥伴」，禁「您 / 會員 / 用戶」
|   - 不暗示商品 / 加盟（月曆紅線 1：未綁母艦零商品 CTA）
|   - 不寫食安 / 健食法療效詞：治療 / 改善 / 緩解 / 修復 / 預防 / 排毒 /
|     燃脂 / 減重 / 抑菌 / 消炎 / 抗氧化 / 提升免疫力 / 取代正餐 /
|     代餐 / 低 GI / 高纖 / 高蛋白 / 飽足 / 加速代謝
|   - 食物建議用「許多朋友會準備的」「妳可以試試」軟性句型
|   - expected_benefit 寫實感：「精神比較穩」「情緒比較不衝」
|
| 分布（80 卡）：
|   phase: menstrual 18 / follicular 17 / ovulation 12 / luteal 22 / late 11
|   type : sleep 8 / move 11 / eat 12 / relax 14 / track 13 / learn 8 / connect 14
|   diff : easy 60 / medium 16 / hard 4
*/

return [

    // ============================================================
    // MENSTRUAL（經期 day 0-7）— 18 卡
    // ============================================================

    'menstrual_warm_belly_15min' => [
        'phase' => ['menstrual'],
        'day_offset_min' => 0,
        'day_offset_max' => 4,
        'type' => 'relax',
        'title' => '熱敷小腹 15 分鐘',
        'body'  => '經期前兩天子宮在用力，溫熱會讓小腹放鬆下來。',
        'expected_benefit' => '小腹比較不脹、坐得住',
        'time_minutes' => 15,
        'difficulty' => 'easy',
    ],

    'menstrual_child_pose_5min' => [
        'phase' => ['menstrual'],
        'day_offset_min' => 0,
        'day_offset_max' => 5,
        'type' => 'move',
        'title' => '嬰兒式 5 分鐘',
        'body'  => '跪坐前彎、額頭貼地，是經期最被朋友推薦的伸展。',
        'expected_benefit' => '腰背鬆一點、呼吸深一點',
        'time_minutes' => 5,
        'difficulty' => 'easy',
    ],

    'menstrual_warm_water' => [
        'phase' => ['menstrual'],
        'day_offset_min' => 0,
        'day_offset_max' => 6,
        'type' => 'eat',
        'title' => '今天多喝溫水',
        'body'  => '經期身體流失水分多，溫水比冰水身體接得住。',
        'expected_benefit' => '手腳沒那麼冰、身體比較暖',
        'time_minutes' => 0,
        'difficulty' => 'easy',
    ],

    'menstrual_early_sleep' => [
        'phase' => ['menstrual'],
        'day_offset_min' => 0,
        'day_offset_max' => 4,
        'type' => 'sleep',
        'title' => '今晚早一小時睡',
        'body'  => '經期前幾天身體在工作，多睡一小時是給自己最簡單的禮物。',
        'expected_benefit' => '隔天起床比較不勉強',
        'time_minutes' => 0,
        'difficulty' => 'easy',
    ],

    'menstrual_letter_to_self' => [
        'phase' => ['menstrual'],
        'day_offset_min' => 1,
        'day_offset_max' => 5,
        'type' => 'track',
        'title' => '寫一句話給自己',
        'body'  => '寫下「今天我希望被怎麼對待」。下次經期妳會想看見。',
        'expected_benefit' => '比較看得見自己的需要',
        'time_minutes' => 3,
        'difficulty' => 'easy',
    ],

    'menstrual_red_bean_soup' => [
        'phase' => ['menstrual'],
        'day_offset_min' => 0,
        'day_offset_max' => 5,
        'type' => 'eat',
        'title' => '一碗紅豆湯',
        'body'  => '紅豆湯或黑芝麻糊，是許多朋友會在經期準備的暖食。',
        'expected_benefit' => '身體比較暖、心情有照顧到',
        'time_minutes' => 10,
        'difficulty' => 'easy',
    ],

    'menstrual_easy_walk_10min' => [
        'phase' => ['menstrual'],
        'day_offset_min' => 2,
        'day_offset_max' => 6,
        'type' => 'move',
        'title' => '輕鬆散步 10 分鐘',
        'body'  => '不追配速、不算步數，只是走出去呼吸。經期後段身體會謝謝妳。',
        'expected_benefit' => '腦袋比較清、肚子比較鬆',
        'time_minutes' => 10,
        'difficulty' => 'easy',
    ],

    'menstrual_foot_soak_10min' => [
        'phase' => ['menstrual'],
        'day_offset_min' => 0,
        'day_offset_max' => 5,
        'type' => 'relax',
        'title' => '泡腳 10 分鐘',
        'body'  => '40°C 左右的溫水泡到腳踝，是經期最容易做到的暖身儀式。',
        'expected_benefit' => '入睡比較快、整個人鬆',
        'time_minutes' => 10,
        'difficulty' => 'easy',
    ],

    'menstrual_thank_body' => [
        'phase' => ['menstrual'],
        'day_offset_min' => 0,
        'day_offset_max' => 6,
        'type' => 'relax',
        'title' => '跟身體說謝謝',
        'body'  => '把手放在小腹上，輕輕說一句「謝謝妳今天努力」。',
        'expected_benefit' => '對自己沒那麼嚴格',
        'time_minutes' => 1,
        'difficulty' => 'easy',
    ],

    'menstrual_say_no_once' => [
        'phase' => ['menstrual'],
        'day_offset_min' => 1,
        'day_offset_max' => 5,
        'type' => 'connect',
        'title' => '拒絕一件事',
        'body'  => '今天找一件可以拒絕的事，溫柔地說「這次先不要」。',
        'expected_benefit' => '心理空間多出一塊',
        'time_minutes' => 0,
        'difficulty' => 'medium',
    ],

    'menstrual_stretch_back_10min' => [
        'phase' => ['menstrual'],
        'day_offset_min' => 1,
        'day_offset_max' => 6,
        'type' => 'move',
        'title' => '腰背伸展 10 分鐘',
        'body'  => '經期腰痠常見，貓牛式 + 嬰兒式輪流做，跟著呼吸慢慢來。',
        'expected_benefit' => '腰背沒那麼緊',
        'time_minutes' => 10,
        'difficulty' => 'easy',
    ],

    'menstrual_low_caffeine' => [
        'phase' => ['menstrual'],
        'day_offset_min' => 0,
        'day_offset_max' => 4,
        'type' => 'eat',
        'title' => '減一杯咖啡',
        'body'  => '經期咖啡因會讓身體更緊繃，今天減一杯試試。',
        'expected_benefit' => '心跳比較穩、手沒那麼抖',
        'time_minutes' => 0,
        'difficulty' => 'medium',
    ],

    'menstrual_log_flow' => [
        'phase' => ['menstrual'],
        'day_offset_min' => 0,
        'day_offset_max' => 6,
        'type' => 'track',
        'title' => '紀錄今天經量',
        'body'  => '輕 / 中 / 重，三選一就好。連兩個週期就能看見規律。',
        'expected_benefit' => '更認識自己的身體',
        'time_minutes' => 1,
        'difficulty' => 'easy',
    ],

    'menstrual_log_pain' => [
        'phase' => ['menstrual'],
        'day_offset_min' => 0,
        'day_offset_max' => 4,
        'type' => 'track',
        'title' => '紀錄今天經痛分數',
        'body'  => '0-10 分標一個。連續紀錄 3 個週期，就看得到妳的痛感曲線。',
        'expected_benefit' => '看醫師時講得清楚',
        'time_minutes' => 1,
        'difficulty' => 'easy',
    ],

    'menstrual_learn_cycle_basics' => [
        'phase' => ['menstrual'],
        'day_offset_min' => 2,
        'day_offset_max' => 7,
        'type' => 'learn',
        'title' => '讀一篇衛教',
        'body'  => '今天從首頁挑一篇朵朵推薦的小知識，讀 3 分鐘就好。',
        'expected_benefit' => '比較認識自己的週期',
        'time_minutes' => 3,
        'difficulty' => 'easy',
    ],

    'menstrual_warm_pad_back' => [
        'phase' => ['menstrual'],
        'day_offset_min' => 0,
        'day_offset_max' => 4,
        'type' => 'relax',
        'title' => '熱敷下背 15 分鐘',
        'body'  => '經期下背也常痠，暖暖包貼在腰部坐 15 分鐘。',
        'expected_benefit' => '腰沒那麼僵、坐姿比較放鬆',
        'time_minutes' => 15,
        'difficulty' => 'easy',
    ],

    'menstrual_message_close_friend' => [
        'phase' => ['menstrual'],
        'day_offset_min' => 1,
        'day_offset_max' => 6,
        'type' => 'connect',
        'title' => '傳訊給好朋友',
        'body'  => '跟一個懂妳的朋友說「我今天有點累」。被接住的感覺很重要。',
        'expected_benefit' => '不那麼孤單',
        'time_minutes' => 5,
        'difficulty' => 'easy',
    ],

    'menstrual_skip_one_task' => [
        'phase' => ['menstrual'],
        'day_offset_min' => 0,
        'day_offset_max' => 3,
        'type' => 'relax',
        'title' => '今天少做一件事',
        'body'  => '從待辦上劃掉一件可以拖到下週的事，把時間還給自己。',
        'expected_benefit' => '今晚比較好睡',
        'time_minutes' => 0,
        'difficulty' => 'medium',
    ],

    // ============================================================
    // FOLLICULAR（卵泡期 day 6-13）— 17 卡
    // ============================================================

    'follicular_pick_a_dream' => [
        'phase' => ['follicular'],
        'day_offset_min' => 0,
        'day_offset_max' => 5,
        'type' => 'track',
        'title' => '寫 3 個小目標',
        'body'  => '卵泡期能量回來了，趁現在寫下這個月想做的 3 件小事。',
        'expected_benefit' => '心裡比較有方向',
        'time_minutes' => 5,
        'difficulty' => 'easy',
    ],

    'follicular_strength_30min' => [
        'phase' => ['follicular'],
        'day_offset_min' => 1,
        'day_offset_max' => 6,
        'type' => 'move',
        'title' => '重訓或快走 30 分鐘',
        'body'  => '卵泡期身體吃得下強度，是運動的好時機。',
        'expected_benefit' => '練完精神更好、不累反而開心',
        'time_minutes' => 30,
        'difficulty' => 'medium',
    ],

    'follicular_tackle_postponed' => [
        'phase' => ['follicular'],
        'day_offset_min' => 0,
        'day_offset_max' => 5,
        'type' => 'connect',
        'title' => '處理一件拖延的事',
        'body'  => '那件「我知道要做但一直沒做」的事，今天 15 分鐘搞定它。',
        'expected_benefit' => '心頭石頭放下',
        'time_minutes' => 15,
        'difficulty' => 'medium',
    ],

    'follicular_read_article' => [
        'phase' => ['follicular'],
        'day_offset_min' => 0,
        'day_offset_max' => 7,
        'type' => 'learn',
        'title' => '讀一篇喜歡的文章',
        'body'  => '不是工作要看的、是妳真的想看的那種。卵泡期吸收力好。',
        'expected_benefit' => '腦袋有被滋養的感覺',
        'time_minutes' => 15,
        'difficulty' => 'easy',
    ],

    'follicular_reach_old_friend' => [
        'phase' => ['follicular'],
        'day_offset_min' => 1,
        'day_offset_max' => 6,
        'type' => 'connect',
        'title' => '聯絡久沒見的朋友',
        'body'  => '傳一句「最近怎麼樣？」給一個久沒聊的人。',
        'expected_benefit' => '心情變寬',
        'time_minutes' => 5,
        'difficulty' => 'easy',
    ],

    'follicular_tidy_corner' => [
        'phase' => ['follicular'],
        'day_offset_min' => 0,
        'day_offset_max' => 6,
        'type' => 'relax',
        'title' => '整理一個小角落',
        'body'  => '一個抽屜、一個桌面、一個包包夾層，挑一個整 10 分鐘。',
        'expected_benefit' => '空間清了、心也清了',
        'time_minutes' => 10,
        'difficulty' => 'easy',
    ],

    'follicular_learn_5min' => [
        'phase' => ['follicular'],
        'day_offset_min' => 0,
        'day_offset_max' => 7,
        'type' => 'learn',
        'title' => '學一個新東西 5 分鐘',
        'body'  => '一個英文單字、一個快捷鍵、一道食譜，5 分鐘就好。',
        'expected_benefit' => '覺得自己又長大一點',
        'time_minutes' => 5,
        'difficulty' => 'easy',
    ],

    'follicular_green_veggies' => [
        'phase' => ['follicular'],
        'day_offset_min' => 0,
        'day_offset_max' => 7,
        'type' => 'eat',
        'title' => '今天加一份綠色蔬菜',
        'body'  => '菠菜、青花菜、地瓜葉，許多朋友會準備的家常菜色。',
        'expected_benefit' => '吃完比較不重',
        'time_minutes' => 0,
        'difficulty' => 'easy',
    ],

    'follicular_yoga_15min' => [
        'phase' => ['follicular'],
        'day_offset_min' => 1,
        'day_offset_max' => 7,
        'type' => 'move',
        'title' => '瑜珈 15 分鐘',
        'body'  => '挑一支妳喜歡的瑜珈短片，跟著做。',
        'expected_benefit' => '身體比較舒展',
        'time_minutes' => 15,
        'difficulty' => 'easy',
    ],

    'follicular_outdoor_sun_15min' => [
        'phase' => ['follicular'],
        'day_offset_min' => 0,
        'day_offset_max' => 7,
        'type' => 'move',
        'title' => '出門曬太陽 15 分鐘',
        'body'  => '不一定要運動，午餐後在外面坐 15 分鐘也算。',
        'expected_benefit' => '心情比較亮、晚上比較好睡',
        'time_minutes' => 15,
        'difficulty' => 'easy',
    ],

    'follicular_journal_intentions' => [
        'phase' => ['follicular'],
        'day_offset_min' => 0,
        'day_offset_max' => 4,
        'type' => 'track',
        'title' => '寫下這個週期的期待',
        'body'  => '「這個月我希望自己更⋯」寫一句。月底回頭看會很有意思。',
        'expected_benefit' => '對自己的方向更清楚',
        'time_minutes' => 3,
        'difficulty' => 'easy',
    ],

    'follicular_try_new_recipe' => [
        'phase' => ['follicular'],
        'day_offset_min' => 1,
        'day_offset_max' => 7,
        'type' => 'eat',
        'title' => '試一道新食譜',
        'body'  => '不用很難，10 分鐘的小菜也算。卵泡期願意嘗新。',
        'expected_benefit' => '吃飯有期待感',
        'time_minutes' => 30,
        'difficulty' => 'medium',
    ],

    'follicular_water_2L' => [
        'phase' => ['follicular'],
        'day_offset_min' => 0,
        'day_offset_max' => 7,
        'type' => 'eat',
        'title' => '今天喝水 2000ml',
        'body'  => '杯子放眼前，每滿一杯就紀錄一次。',
        'expected_benefit' => '皮膚比較有水感、上廁所變規律',
        'time_minutes' => 0,
        'difficulty' => 'easy',
    ],

    'follicular_dance_5min' => [
        'phase' => ['follicular'],
        'day_offset_min' => 0,
        'day_offset_max' => 7,
        'type' => 'move',
        'title' => '放音樂跳 5 分鐘',
        'body'  => '挑一首讓妳想動的歌，在房間隨便擺動。',
        'expected_benefit' => '心情變輕、身體有醒過來',
        'time_minutes' => 5,
        'difficulty' => 'easy',
    ],

    'follicular_skincare_focus' => [
        'phase' => ['follicular'],
        'day_offset_min' => 0,
        'day_offset_max' => 7,
        'type' => 'relax',
        'title' => '專心做一次保養',
        'body'  => '不滑手機，認真敷一片面膜或塗精華。',
        'expected_benefit' => '臉有被疼到',
        'time_minutes' => 15,
        'difficulty' => 'easy',
    ],

    'follicular_book_meaningful_event' => [
        'phase' => ['follicular'],
        'day_offset_min' => 2,
        'day_offset_max' => 7,
        'type' => 'connect',
        'title' => '排一場期待的聚會',
        'body'  => '訂一個跟朋友吃飯、看展、出遊的日子。卵泡期排來最 work。',
        'expected_benefit' => '生活有盼頭',
        'time_minutes' => 5,
        'difficulty' => 'easy',
    ],

    'follicular_listen_podcast' => [
        'phase' => ['follicular'],
        'day_offset_min' => 0,
        'day_offset_max' => 7,
        'type' => 'learn',
        'title' => '聽一集 podcast',
        'body'  => '通勤、做家事的時候放一集。卵泡期吸收好。',
        'expected_benefit' => '腦袋有被填到',
        'time_minutes' => 30,
        'difficulty' => 'easy',
    ],

    // ============================================================
    // OVULATION（排卵期 day 12-16）— 12 卡
    // ============================================================

    'ovulation_progress_photo' => [
        'phase' => ['ovulation'],
        'day_offset_min' => 0,
        'day_offset_max' => 4,
        'type' => 'track',
        'title' => '拍一張臉部進度照',
        'body'  => '排卵期皮膚常常是整個週期最好的時候，現在拍下來。',
        'expected_benefit' => '看見自己的最好狀態',
        'time_minutes' => 2,
        'difficulty' => 'easy',
    ],

    'ovulation_deep_talk_30min' => [
        'phase' => ['ovulation'],
        'day_offset_min' => 0,
        'day_offset_max' => 4,
        'type' => 'connect',
        'title' => '深聊 30 分鐘',
        'body'  => '跟伴侶或好朋友認真聊一件最近在想的事。',
        'expected_benefit' => '感情變近、想法變清楚',
        'time_minutes' => 30,
        'difficulty' => 'easy',
    ],

    'ovulation_schedule_big_thing' => [
        'phase' => ['ovulation'],
        'day_offset_min' => 0,
        'day_offset_max' => 3,
        'type' => 'connect',
        'title' => '安排重要事務',
        'body'  => '簡報、面試、提案，排在這幾天精力最高峰。',
        'expected_benefit' => '表現比平常更穩',
        'time_minutes' => 5,
        'difficulty' => 'medium',
    ],

    'ovulation_good_fats' => [
        'phase' => ['ovulation'],
        'day_offset_min' => 0,
        'day_offset_max' => 4,
        'type' => 'eat',
        'title' => '加一份溫和好油',
        'body'  => '一把堅果、半顆酪梨，是不少朋友會搭配的點心。',
        'expected_benefit' => '飽得久、不容易嘴饞',
        'time_minutes' => 0,
        'difficulty' => 'easy',
    ],

    'ovulation_laugh_out_loud' => [
        'phase' => ['ovulation'],
        'day_offset_min' => 0,
        'day_offset_max' => 4,
        'type' => 'relax',
        'title' => '大笑一場',
        'body'  => '挑一個喜劇短片或脫口秀，笑到肚子痛那種。',
        'expected_benefit' => '整個人鬆掉',
        'time_minutes' => 15,
        'difficulty' => 'easy',
    ],

    'ovulation_outdoor_walk_20min' => [
        'phase' => ['ovulation'],
        'day_offset_min' => 0,
        'day_offset_max' => 4,
        'type' => 'move',
        'title' => '戶外散步 20 分鐘',
        'body'  => '排卵期戶外光線會讓人特別有精神。',
        'expected_benefit' => '心情亮、思路順',
        'time_minutes' => 20,
        'difficulty' => 'easy',
    ],

    'ovulation_log_cm' => [
        'phase' => ['ovulation'],
        'day_offset_min' => 0,
        'day_offset_max' => 4,
        'type' => 'track',
        'title' => '紀錄今天分泌物',
        'body'  => '對備孕或想認識身體的朋友，今天的拉絲分泌物是排卵訊號。',
        'expected_benefit' => '更了解自己的節律',
        'time_minutes' => 1,
        'difficulty' => 'easy',
    ],

    'ovulation_log_libido' => [
        'phase' => ['ovulation'],
        'day_offset_min' => 0,
        'day_offset_max' => 4,
        'type' => 'track',
        'title' => '紀錄今天性慾分數',
        'body'  => '0-10 分標一下。連幾個週期就看得到自己的高峰段。',
        'expected_benefit' => '更認識自己的循環',
        'time_minutes' => 1,
        'difficulty' => 'easy',
    ],

    'ovulation_express_appreciation' => [
        'phase' => ['ovulation'],
        'day_offset_min' => 0,
        'day_offset_max' => 4,
        'type' => 'connect',
        'title' => '對一個人說謝謝',
        'body'  => '具體說出他做了什麼讓妳感謝。排卵期表達特別有力量。',
        'expected_benefit' => '關係變暖',
        'time_minutes' => 3,
        'difficulty' => 'easy',
    ],

    'ovulation_creative_15min' => [
        'phase' => ['ovulation'],
        'day_offset_min' => 0,
        'day_offset_max' => 4,
        'type' => 'learn',
        'title' => '做一件創作的事',
        'body'  => '寫、畫、剪、拼，15 分鐘。排卵期創意最旺。',
        'expected_benefit' => '有創造的滿足感',
        'time_minutes' => 15,
        'difficulty' => 'easy',
    ],

    'ovulation_cardio_intense' => [
        'phase' => ['ovulation'],
        'day_offset_min' => 0,
        'day_offset_max' => 3,
        'type' => 'move',
        'title' => '高強度有氧 20 分鐘',
        'body'  => '跑步、HIIT、舞蹈課，妳會發現比平常做得更輕鬆。',
        'expected_benefit' => '練完特別爽快',
        'time_minutes' => 20,
        'difficulty' => 'hard',
    ],

    'ovulation_outfit_confidence' => [
        'phase' => ['ovulation'],
        'day_offset_min' => 0,
        'day_offset_max' => 4,
        'type' => 'relax',
        'title' => '穿一件最愛的衣服',
        'body'  => '不留給特殊場合，今天就穿。排卵期穿什麼都好看。',
        'expected_benefit' => '一整天比較有自信',
        'time_minutes' => 0,
        'difficulty' => 'easy',
    ],

    // ============================================================
    // LUTEAL（黃體期 day 16-28）— 22 卡
    // ============================================================

    'sleep_extra_30min' => [
        'phase' => ['luteal'],
        'day_offset_min' => 0,
        'day_offset_max' => 12,
        'type' => 'sleep',
        'title' => '早 30 分鐘睡',
        'body'  => '黃體期身體在工作，多 30 分鐘睡眠 PMS 通常會輕一點。',
        'expected_benefit' => '隔天精神 + 情緒比較穩',
        'time_minutes' => 0,
        'difficulty' => 'easy',
    ],

    'luteal_cut_one_caffeine' => [
        'phase' => ['luteal'],
        'day_offset_min' => 5,
        'day_offset_max' => 12,
        'type' => 'eat',
        'title' => '減一杯咖啡',
        'body'  => '黃體期咖啡因容易讓焦慮放大，減一杯試試身體的反應。',
        'expected_benefit' => '心跳沒那麼快、比較好睡',
        'time_minutes' => 0,
        'difficulty' => 'medium',
    ],

    'luteal_journal_5min' => [
        'phase' => ['luteal'],
        'day_offset_min' => 5,
        'day_offset_max' => 13,
        'type' => 'track',
        'title' => '寫日記 5 分鐘',
        'body'  => '把腦中亂飛的事寫下來，不用整齊。寫完往往比較鬆。',
        'expected_benefit' => '情緒沒那麼擠',
        'time_minutes' => 5,
        'difficulty' => 'easy',
    ],

    'luteal_decline_invitation' => [
        'phase' => ['luteal'],
        'day_offset_min' => 7,
        'day_offset_max' => 13,
        'type' => 'connect',
        'title' => '婉拒一個邀約',
        'body'  => '黃體期社交電量低，今天找一個可以推掉的邀約溫柔說不。',
        'expected_benefit' => '今晚不被掏空',
        'time_minutes' => 0,
        'difficulty' => 'medium',
    ],

    'luteal_solo_movie' => [
        'phase' => ['luteal'],
        'day_offset_min' => 5,
        'day_offset_max' => 13,
        'type' => 'relax',
        'title' => '獨處看一部電影',
        'body'  => '不講話、不滑手機，挑一部喜歡的舊片重看。',
        'expected_benefit' => '情緒被接住',
        'time_minutes' => 90,
        'difficulty' => 'easy',
    ],

    'luteal_magnesium_food' => [
        'phase' => ['luteal'],
        'day_offset_min' => 0,
        'day_offset_max' => 13,
        'type' => 'eat',
        'title' => '吃一份深綠葉菜',
        'body'  => '菠菜、莧菜或一根香蕉，是不少朋友會搭配的家常選擇。',
        'expected_benefit' => '腿沒那麼緊、心情比較穩',
        'time_minutes' => 0,
        'difficulty' => 'easy',
    ],

    'luteal_box_breathing_5min' => [
        'phase' => ['luteal'],
        'day_offset_min' => 0,
        'day_offset_max' => 13,
        'type' => 'relax',
        'title' => '5 分鐘 box breathing',
        'body'  => '吸 4 秒、停 4 秒、吐 4 秒、停 4 秒，跟著做 5 分鐘。',
        'expected_benefit' => '焦慮降下來',
        'time_minutes' => 5,
        'difficulty' => 'easy',
    ],

    'luteal_let_go_today' => [
        'phase' => ['luteal'],
        'day_offset_min' => 7,
        'day_offset_max' => 13,
        'type' => 'relax',
        'title' => '放下今天沒做完的',
        'body'  => '把待辦上一件「明天再做也沒事」的事劃掉。',
        'expected_benefit' => '今晚比較睡得著',
        'time_minutes' => 0,
        'difficulty' => 'medium',
    ],

    'luteal_prep_snacks' => [
        'phase' => ['luteal'],
        'day_offset_min' => 5,
        'day_offset_max' => 12,
        'type' => 'eat',
        'title' => '備好明天的小點心',
        'body'  => '黃體期容易餓，提前準備堅果、水果或地瓜，餓的時候有東西。',
        'expected_benefit' => '不會臨時亂吃',
        'time_minutes' => 10,
        'difficulty' => 'easy',
    ],

    'luteal_tell_partner' => [
        'phase' => ['luteal'],
        'day_offset_min' => 7,
        'day_offset_max' => 13,
        'type' => 'connect',
        'title' => '跟伴侶 / 家人說一句',
        'body'  => '「我這幾天可能比較敏感，不是針對你」這句話很有用。',
        'expected_benefit' => '吵架機率變低',
        'time_minutes' => 1,
        'difficulty' => 'medium',
    ],

    'luteal_warm_shower_long' => [
        'phase' => ['luteal'],
        'day_offset_min' => 5,
        'day_offset_max' => 13,
        'type' => 'relax',
        'title' => '泡一個長澡',
        'body'  => '溫水 10-15 分鐘，肩膀以下泡進去。',
        'expected_benefit' => '身體鬆、睡得深',
        'time_minutes' => 15,
        'difficulty' => 'easy',
    ],

    'luteal_log_pms_symptom' => [
        'phase' => ['luteal'],
        'day_offset_min' => 5,
        'day_offset_max' => 13,
        'type' => 'track',
        'title' => '紀錄今天 PMS 症狀',
        'body'  => '腹脹、頭痛、嘴饞、易怒，挑一個標一下。',
        'expected_benefit' => '看得到自己的模式',
        'time_minutes' => 1,
        'difficulty' => 'easy',
    ],

    'luteal_easy_walk_15min' => [
        'phase' => ['luteal'],
        'day_offset_min' => 0,
        'day_offset_max' => 13,
        'type' => 'move',
        'title' => '輕鬆散步 15 分鐘',
        'body'  => '不追配速，黃體期散步比硬訓練舒服。',
        'expected_benefit' => '情緒比較不衝',
        'time_minutes' => 15,
        'difficulty' => 'easy',
    ],

    'luteal_no_phone_30min_before_bed' => [
        'phase' => ['luteal'],
        'day_offset_min' => 5,
        'day_offset_max' => 13,
        'type' => 'sleep',
        'title' => '睡前 30 分不滑手機',
        'body'  => '把手機放到房間外或抽屜裡，黃體期睡眠淺更要顧入睡品質。',
        'expected_benefit' => '比較不會半夜醒',
        'time_minutes' => 30,
        'difficulty' => 'medium',
    ],

    'luteal_dim_lights_evening' => [
        'phase' => ['luteal'],
        'day_offset_min' => 5,
        'day_offset_max' => 13,
        'type' => 'sleep',
        'title' => '晚上把燈調暗',
        'body'  => '吃完飯後把家裡主燈關掉，只留黃光小燈。',
        'expected_benefit' => '身體知道要睡了',
        'time_minutes' => 0,
        'difficulty' => 'easy',
    ],

    'luteal_simple_dinner' => [
        'phase' => ['luteal'],
        'day_offset_min' => 7,
        'day_offset_max' => 13,
        'type' => 'eat',
        'title' => '晚餐吃簡單一點',
        'body'  => '一碗清湯麵、一份蛋炒飯，黃體期晚餐輕一點比較好睡。',
        'expected_benefit' => '半夜不會被脹醒',
        'time_minutes' => 0,
        'difficulty' => 'easy',
    ],

    'luteal_self_compassion_phrase' => [
        'phase' => ['luteal'],
        'day_offset_min' => 7,
        'day_offset_max' => 13,
        'type' => 'relax',
        'title' => '對自己說一句溫柔話',
        'body'  => '「我已經很努力了」「累一下沒關係」。練習對自己的溫柔。',
        'expected_benefit' => '不那麼自責',
        'time_minutes' => 1,
        'difficulty' => 'easy',
    ],

    'luteal_reduce_news' => [
        'phase' => ['luteal'],
        'day_offset_min' => 7,
        'day_offset_max' => 13,
        'type' => 'connect',
        'title' => '少看一次新聞',
        'body'  => '黃體期情緒比較容易被牽動，今天少滑一輪 IG / 新聞。',
        'expected_benefit' => '心情沒那麼浮',
        'time_minutes' => 0,
        'difficulty' => 'medium',
    ],

    'luteal_learn_pms_pattern' => [
        'phase' => ['luteal'],
        'day_offset_min' => 0,
        'day_offset_max' => 7,
        'type' => 'learn',
        'title' => '讀一篇黃體期衛教',
        'body'  => '從首頁挑一篇講黃體期的小知識，3 分鐘就好。',
        'expected_benefit' => '比較認識身體在做什麼',
        'time_minutes' => 3,
        'difficulty' => 'easy',
    ],

    'luteal_review_action_history' => [
        'phase' => ['luteal'],
        'day_offset_min' => 7,
        'day_offset_max' => 13,
        'type' => 'track',
        'title' => '回看上次黃體期紀錄',
        'body'  => '上個月妳做了什麼有用？打開看看，自己的 protocol 在累積。',
        'expected_benefit' => '更會照顧自己',
        'time_minutes' => 5,
        'difficulty' => 'easy',
    ],

    'luteal_water_bottle_visible' => [
        'phase' => ['luteal'],
        'day_offset_min' => 0,
        'day_offset_max' => 13,
        'type' => 'eat',
        'title' => '水壺放在眼前',
        'body'  => '黃體期水腫，多喝水反而消得快。把水壺放手邊提醒自己。',
        'expected_benefit' => '臉沒那麼脹',
        'time_minutes' => 0,
        'difficulty' => 'easy',
    ],

    'luteal_cancel_one_thing' => [
        'phase' => ['luteal'],
        'day_offset_min' => 9,
        'day_offset_max' => 13,
        'type' => 'connect',
        'title' => '取消一件可以取消的事',
        'body'  => '經前 3-5 天身體最累，今天主動把一件事挪到下個週期。',
        'expected_benefit' => '到經期不崩潰',
        'time_minutes' => 0,
        'difficulty' => 'hard',
    ],

    // ============================================================
    // LATE（經期延遲）— 11 卡
    // ============================================================

    'late_destress_one_thing' => [
        'phase' => ['late'],
        'day_offset_min' => 0,
        'day_offset_max' => 13,
        'type' => 'relax',
        'title' => '做一件喜歡的事',
        'body'  => '壓力大常會讓週期延後。今天先把擔心放一邊，做一件自己喜歡的事。',
        'expected_benefit' => '神經比較鬆',
        'time_minutes' => 30,
        'difficulty' => 'easy',
    ],

    'late_review_recent_stress' => [
        'phase' => ['late'],
        'day_offset_min' => 0,
        'day_offset_max' => 13,
        'type' => 'track',
        'title' => '想想最近的壓力來源',
        'body'  => '寫下三件最近讓妳緊繃的事。看見了，往往就鬆一半。',
        'expected_benefit' => '心裡比較有頭緒',
        'time_minutes' => 5,
        'difficulty' => 'easy',
    ],

    'late_say_to_body' => [
        'phase' => ['late'],
        'day_offset_min' => 0,
        'day_offset_max' => 13,
        'type' => 'relax',
        'title' => '跟身體說沒關係',
        'body'  => '把手放在小腹說：「妳忙累了沒關係，我等妳。」',
        'expected_benefit' => '不那麼焦慮',
        'time_minutes' => 1,
        'difficulty' => 'easy',
    ],

    'late_review_sleep_pattern' => [
        'phase' => ['late'],
        'day_offset_min' => 0,
        'day_offset_max' => 13,
        'type' => 'track',
        'title' => '盤點這個月作息',
        'body'  => '幾點睡？幾點起？吃什麼？回想一下，看身體經歷了什麼。',
        'expected_benefit' => '比較看得見原因',
        'time_minutes' => 5,
        'difficulty' => 'easy',
    ],

    'late_warm_bath' => [
        'phase' => ['late'],
        'day_offset_min' => 0,
        'day_offset_max' => 13,
        'type' => 'relax',
        'title' => '泡一個熱水澡',
        'body'  => '把肩膀以下泡進溫水 15 分鐘，身體放鬆下來月經反而容易來。',
        'expected_benefit' => '整個人鬆',
        'time_minutes' => 15,
        'difficulty' => 'easy',
    ],

    'late_easy_yoga' => [
        'phase' => ['late'],
        'day_offset_min' => 0,
        'day_offset_max' => 13,
        'type' => 'move',
        'title' => '溫和瑜珈 15 分鐘',
        'body'  => '蝴蝶式、嬰兒式、貓牛式輪流，跟著呼吸慢慢來。',
        'expected_benefit' => '骨盆腔比較鬆',
        'time_minutes' => 15,
        'difficulty' => 'easy',
    ],

    'late_journal_worry' => [
        'phase' => ['late'],
        'day_offset_min' => 0,
        'day_offset_max' => 13,
        'type' => 'track',
        'title' => '把擔心寫下來',
        'body'  => '把現在腦中所有「萬一」寫到紙上。寫完留 24 小時再看。',
        'expected_benefit' => '思緒不再繞圈',
        'time_minutes' => 5,
        'difficulty' => 'easy',
    ],

    'late_warm_food' => [
        'phase' => ['late'],
        'day_offset_min' => 0,
        'day_offset_max' => 13,
        'type' => 'eat',
        'title' => '今天三餐都吃溫的',
        'body'  => '不冰不生，許多朋友會在這時候多喝湯、多吃熱粥。',
        'expected_benefit' => '身體比較暖',
        'time_minutes' => 0,
        'difficulty' => 'easy',
    ],

    'late_message_someone_safe' => [
        'phase' => ['late'],
        'day_offset_min' => 0,
        'day_offset_max' => 13,
        'type' => 'connect',
        'title' => '跟一個人聊一下',
        'body'  => '不用講月經的事，跟一個讓妳放鬆的人聊 5 分鐘就好。',
        'expected_benefit' => '不那麼孤單',
        'time_minutes' => 5,
        'difficulty' => 'easy',
    ],

    'late_consider_clinic_visit' => [
        'phase' => ['late'],
        'day_offset_min' => 14,
        'day_offset_max' => 30,
        'type' => 'connect',
        'title' => '考慮看一次醫師',
        'body'  => '延遲 14 天以上，找婦產科聊聊比較安心。朵朵不是醫師，這條訊號值得被看見。',
        'expected_benefit' => '心裡有底',
        'time_minutes' => 60,
        'difficulty' => 'hard',
    ],

    'late_learn_late_period_basics' => [
        'phase' => ['late'],
        'day_offset_min' => 0,
        'day_offset_max' => 13,
        'type' => 'learn',
        'title' => '讀一篇延遲衛教',
        'body'  => '了解一下身體為什麼會延遲，常見原因比想像中多。',
        'expected_benefit' => '比較不慌',
        'time_minutes' => 3,
        'difficulty' => 'easy',
    ],

    // ============================================================
    // 補強：sleep / learn 跨 phase（達到每 type ≥ 8 的覆蓋率）
    // ============================================================

    'follicular_consistent_bedtime' => [
        'phase' => ['follicular'],
        'day_offset_min' => 0,
        'day_offset_max' => 7,
        'type' => 'sleep',
        'title' => '今晚跟昨天同時間睡',
        'body'  => '卵泡期睡眠規律會讓整個週期更穩。試試固定上床時間。',
        'expected_benefit' => '隔天醒來比較有精神',
        'time_minutes' => 0,
        'difficulty' => 'easy',
    ],

    'ovulation_protect_sleep' => [
        'phase' => ['ovulation'],
        'day_offset_min' => 0,
        'day_offset_max' => 4,
        'type' => 'sleep',
        'title' => '排卵期不熬夜',
        'body'  => '精力高峰容易讓人想多做一點，今天還是讓自己準時睡。',
        'expected_benefit' => '高峰可以延長一點',
        'time_minutes' => 0,
        'difficulty' => 'medium',
    ],

    'late_early_sleep_relax' => [
        'phase' => ['late'],
        'day_offset_min' => 0,
        'day_offset_max' => 13,
        'type' => 'sleep',
        'title' => '今晚提早 1 小時躺',
        'body'  => '身體在等待時更需要休息。提早躺著、不滑手機就好。',
        'expected_benefit' => '神經沒那麼緊繃',
        'time_minutes' => 0,
        'difficulty' => 'easy',
    ],

    'menstrual_nap_20min' => [
        'phase' => ['menstrual'],
        'day_offset_min' => 0,
        'day_offset_max' => 4,
        'type' => 'sleep',
        'title' => '午睡 20 分鐘',
        'body'  => '經期下午容易累，能躺就躺一下，20 分鐘剛剛好。',
        'expected_benefit' => '下半天比較撐得住',
        'time_minutes' => 20,
        'difficulty' => 'easy',
    ],

    'ovulation_learn_fertility_window' => [
        'phase' => ['ovulation'],
        'day_offset_min' => 0,
        'day_offset_max' => 4,
        'type' => 'learn',
        'title' => '了解黃金受孕期',
        'body'  => '不論備不備孕，認識自己的排卵窗都是基本功。讀 3 分鐘。',
        'expected_benefit' => '更會看自己的身體訊號',
        'time_minutes' => 3,
        'difficulty' => 'easy',
    ],

];
