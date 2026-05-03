<?php

/*
| Wave 13 — BodyDex 30 種症狀 catalog（narrative content live）
|
| 與 symptom-tags.php 對齊（symptom_key 同 symptom-tags 的 key）。
| 收集滿 30 解 legendary outfit `body_dex_master`（OutfitCatalog 補卡）。
|
| Schema（每 entry，向後相容既有 BodyDexService consumer）：
|   label           中文短名（同 symptom-tags）
|   hint            列表頁短描述（≤ 16 字）
|   rarity          common / rare / epic / legendary
|   category        physical / emotional / sexual / fertility
|   description     卡片詳細描述（≤ 100 字）— 用「許多朋友會」軟性
|   why_text        為什麼會這樣（≤ 60 字機制簡述）
|   comfort_actions daily-actions.php 的 key（朵朵建議行動）
|   dodo_companion  朵朵陪伴的一句話
|   illustration    emoji
|
| 撰寫規範：0 療效詞 / 0 商品名 / 0 加盟暗示
*/

return [
    'total_target' => 30,

    'entries' => [

        // ─── PHYSICAL 8 ───
        'cramp' => [
            'label' => '小腹悶痛', 'hint' => '經期常見的訊號', 'rarity' => 'common', 'category' => 'physical',
            'description' => '小腹深處的悶悶痛痛，是經期前幾天最典型的訊號。許多朋友會發現它有自己的節奏，痛一陣、緩一陣。',
            'why_text' => '子宮內膜剝落時會收縮，這個收縮就是妳感覺到的痛。是身體在工作。',
            'comfort_actions' => ['menstrual_warm_belly_15min', 'menstrual_child_pose_5min'],
            'dodo_companion' => '朵朵陪妳熱敷 15 分鐘。妳什麼都不用做。',
            'illustration' => '🌧️',
        ],
        'headache' => [
            'label' => '頭痛', 'hint' => '黃體期 / 經前常出現', 'rarity' => 'common', 'category' => 'physical',
            'description' => '太陽穴或後腦勺的痛，常出現在經前一週或經期第一天。許多朋友會因為睡眠不足或脫水而更明顯。',
            'why_text' => '荷爾蒙變化會影響血管收縮，妳的頭可能對這個變化比較敏感。',
            'comfort_actions' => ['luteal_box_breathing_5min', 'menstrual_early_sleep'],
            'dodo_companion' => '今晚早一點睡。手機放遠一點。',
            'illustration' => '😣',
        ],
        'fatigue' => [
            'label' => '很累', 'hint' => '身體在告訴妳要休息', 'rarity' => 'common', 'category' => 'physical',
            'description' => '不是「忙累」，是那種「明明沒做什麼也累」的累。許多朋友會在經期或黃體期感到。',
            'why_text' => '身體在內部做大工程，能量自然往內走。累是訊號不是缺點。',
            'comfort_actions' => ['menstrual_nap_20min', 'sleep_extra_30min'],
            'dodo_companion' => '累就累。今天少做一件事，朵朵不會說妳。',
            'illustration' => '😴',
        ],
        'bloating' => [
            'label' => '腹脹', 'hint' => '黃體期水分滯留', 'rarity' => 'common', 'category' => 'physical',
            'description' => '肚子鼓鼓的、衣服變緊、坐下覺得卡卡的。許多朋友會在經前一週特別有感。',
            'why_text' => '黃體期身體會多留一點水分，這是正常的循環，月經來了會自己消。',
            'comfort_actions' => ['luteal_water_bottle_visible', 'luteal_easy_walk_15min'],
            'dodo_companion' => '今天穿寬鬆的衣服，朵朵覺得舒服比好看重要。',
            'illustration' => '🎈',
        ],
        'breast_tender' => [
            'label' => '胸部脹脹的', 'hint' => '黃體期賀爾蒙波動', 'rarity' => 'common', 'category' => 'physical',
            'description' => '胸部摸起來脹脹、有壓迫感，碰到會痛。許多朋友會在排卵後到經期前出現。',
            'why_text' => '荷爾蒙讓乳腺組織短暫增厚，月經來就會自己消。',
            'comfort_actions' => ['luteal_warm_shower_long', 'luteal_journal_5min'],
            'dodo_companion' => '今天穿沒鋼圈的內衣，或乾脆不穿。妳的胸部不需要被緊緊壓著。',
            'illustration' => '💗',
        ],
        'acne' => [
            'label' => '冒痘', 'hint' => '皮脂腺活躍時期', 'rarity' => 'common', 'category' => 'physical',
            'description' => '下巴或鼻側冒紅紅的痘，常出現在經前一週。許多朋友會發現它跟著週期循環。',
            'why_text' => '荷爾蒙波動讓皮脂腺更活躍，毛孔暫時比較容易堵住。',
            'comfort_actions' => ['follicular_skincare_focus', 'luteal_water_bottle_visible'],
            'dodo_companion' => '不要擠。今天少吃一塊炸的，多喝一杯溫水。',
            'illustration' => '🔴',
        ],
        'back_pain' => [
            'label' => '腰痠', 'hint' => '經期前後常見', 'rarity' => 'common', 'category' => 'physical',
            'description' => '下背一條一條的痠，坐久了更明顯。許多朋友會在經期第一二天最有感。',
            'why_text' => '子宮收縮會牽動骨盆腔附近的肌肉，是「連帶痠」。',
            'comfort_actions' => ['menstrual_warm_pad_back', 'menstrual_stretch_back_10min'],
            'dodo_companion' => '熱敷下背 15 分鐘，貓牛式 5 個。朵朵在旁邊計時。',
            'illustration' => '🦴',
        ],
        'nausea' => [
            'label' => '反胃', 'hint' => '經期或荷爾蒙波動', 'rarity' => 'rare', 'category' => 'physical',
            'description' => '胃裡翻翻的、不一定吐得出來、聞到油味會更想吐。許多朋友會在經期第一天感到。',
            'why_text' => '前列腺素影響到腸胃肌肉，胃會比平常敏感。',
            'comfort_actions' => ['menstrual_warm_water', 'luteal_simple_dinner'],
            'dodo_companion' => '今天吃清淡。喝溫水、避開太油的東西，慢慢來。',
            'illustration' => '🤢',
        ],

        // ─── EMOTIONAL 7 ───
        'mood_swing' => [
            'label' => '情緒起伏', 'hint' => '黃體期常見', 'rarity' => 'common', 'category' => 'emotional',
            'description' => '前一秒還好好的，下一秒突然煩躁或想哭。許多朋友會在經前一週特別容易起伏。',
            'why_text' => '荷爾蒙波動會直接影響大腦的情緒迴路，不是妳「想太多」。',
            'comfort_actions' => ['luteal_journal_5min', 'luteal_self_compassion_phrase'],
            'dodo_companion' => '不是妳的問題。是身體的問題。今天對自己溫柔一點。',
            'illustration' => '🎢',
        ],
        'craving_sweet' => [
            'label' => '想吃甜', 'hint' => '經前的小訊號', 'rarity' => 'common', 'category' => 'emotional',
            'description' => '突然超想吃甜的，巧克力、餅乾、蛋糕都好。許多朋友會在經前一週出現。',
            'why_text' => '黃體期身體燃燒得快，腦袋在跟妳要快速能量。不是嘴饞。',
            'comfort_actions' => ['luteal_prep_snacks', 'ovulation_good_fats'],
            'dodo_companion' => '想吃就吃一點。一塊巧克力不會毀了什麼。',
            'illustration' => '🍬',
        ],
        'craving_salty' => [
            'label' => '想吃鹹', 'hint' => '經前水分需求', 'rarity' => 'common', 'category' => 'emotional',
            'description' => '突然想吃鹹的，洋芋片、泡麵、鹹酥雞⋯許多朋友會在經前出現。',
            'why_text' => '經前水分滯留會讓身體電解質暫時不平衡，妳會本能想補鹽。',
            'comfort_actions' => ['luteal_prep_snacks', 'follicular_water_2L'],
            'dodo_companion' => '想吃鹹的可以吃，搭配多喝水比較舒服。',
            'illustration' => '🍟',
        ],
        'insomnia' => [
            'label' => '睡不好', 'hint' => '黃體期常見', 'rarity' => 'common', 'category' => 'emotional',
            'description' => '躺很久睡不著、半夜醒、清晨太早醒。許多朋友會在經前一週特別明顯。',
            'why_text' => '黃體期體溫稍高、荷爾蒙影響大腦的睡眠迴路，比較淺眠是正常的。',
            'comfort_actions' => ['luteal_no_phone_30min_before_bed', 'luteal_dim_lights_evening'],
            'dodo_companion' => '今晚睡前 30 分不滑手機。睡不著就閉眼休息也算休息。',
            'illustration' => '🌙',
        ],
        'anxious' => [
            'label' => '焦慮', 'hint' => '身體在提醒妳', 'rarity' => 'rare', 'category' => 'emotional',
            'description' => '心臟跳很快、手心出汗、腦子停不下來。許多朋友會在經前一週感到比較頻繁。',
            'why_text' => '黃體期身體對壓力的耐受度會降低，妳沒變脆弱，是身體在關心妳。',
            'comfort_actions' => ['luteal_box_breathing_5min', 'late_journal_worry'],
            'dodo_companion' => '5 分鐘 box breathing：吸 4、停 4、吐 4、停 4。朵朵跟著妳數。',
            'illustration' => '😰',
        ],
        'irritable' => [
            'label' => '易怒', 'hint' => '經前特別敏感', 'rarity' => 'rare', 'category' => 'emotional',
            'description' => '一點小事就點火、看誰都不順眼。許多朋友會在經前 3-5 天特別明顯。',
            'why_text' => '荷爾蒙讓神經比較敏感，是身體在告訴妳「今天請給我空間」。',
            'comfort_actions' => ['luteal_decline_invitation', 'luteal_tell_partner'],
            'dodo_companion' => '今天可以拒絕一場聚會。火源減少，火就燒得小。',
            'illustration' => '😤',
        ],
        'low_mood' => [
            'label' => '心情低低的', 'hint' => '允許自己今天慢一點', 'rarity' => 'rare', 'category' => 'emotional',
            'description' => '說不上來為什麼，就是淡淡的灰。許多朋友會在經期或經前感到。',
            'why_text' => '荷爾蒙會讓情緒底線往下挪一格，這不是妳真的悲觀。',
            'comfort_actions' => ['luteal_solo_movie', 'menstrual_message_close_friend'],
            'dodo_companion' => '今天不用裝開心。低低的也是一種狀態。朵朵陪妳。',
            'illustration' => '🌧️',
        ],

        // ─── SEXUAL 6 ───
        'libido_high' => [
            'label' => '性慾較高', 'hint' => '排卵期前後正常', 'rarity' => 'rare', 'category' => 'sexual',
            'description' => '對親密有比較強的渴望，看伴侶比平常順眼。許多朋友會在排卵期前後感到。',
            'why_text' => '排卵期身體在跟妳說「現在能量飽滿」，這是自然的循環。',
            'comfort_actions' => ['ovulation_deep_talk_30min', 'ovulation_express_appreciation'],
            'dodo_companion' => '感受是禮物，不是要被壓抑的東西。',
            'illustration' => '✨',
        ],
        'libido_low' => [
            'label' => '性慾較低', 'hint' => '經期 / 黃體期常見', 'rarity' => 'rare', 'category' => 'sexual',
            'description' => '對親密暫時沒有興趣，或覺得身體不想被靠近。許多朋友會在經期或經前感到。',
            'why_text' => '身體在做大工程，能量往內收，是它在保護自己。',
            'comfort_actions' => ['luteal_tell_partner', 'menstrual_skip_one_task'],
            'dodo_companion' => '不想就不要勉強。「今天不行」是一個完整的回答。',
            'illustration' => '💤',
        ],
        'sex_protected' => [
            'label' => '性行為（有避孕）', 'hint' => '紀錄妳的選擇', 'rarity' => 'common', 'category' => 'sexual',
            'description' => '有採取避孕的親密行為紀錄。許多朋友會留這個紀錄做為週期參考。',
            'why_text' => '紀錄不是評斷，是讓妳更清楚自己的節奏。',
            'comfort_actions' => ['ovulation_log_libido'],
            'dodo_companion' => '紀錄完成。朵朵不會說什麼，只是收好。',
            'illustration' => '💗',
        ],
        'sex_unprotected' => [
            'label' => '性行為（無避孕）', 'hint' => '紀錄妳的選擇', 'rarity' => 'common', 'category' => 'sexual',
            'description' => '沒有採取避孕的親密行為紀錄。許多備孕朋友會留這個紀錄做為週期參考。',
            'why_text' => '紀錄是給妳自己看的，朵朵不會評斷。',
            'comfort_actions' => ['ovulation_log_cm', 'ovulation_log_libido'],
            'dodo_companion' => '紀錄完成。妳的選擇朵朵都尊重。',
            'illustration' => '💞',
        ],
        'contraception_pill' => [
            'label' => '避孕藥', 'hint' => '紀錄服用節奏', 'rarity' => 'rare', 'category' => 'sexual',
            'description' => '今天有服用避孕藥的紀錄。許多朋友會用這個紀錄確認服用的規律性。',
            'why_text' => '規律服用是避孕藥起作用的關鍵，連續記錄能讓妳看清自己的節奏。',
            'comfort_actions' => [],
            'dodo_companion' => '紀錄完成。朵朵幫妳記得，明天同個時間再來。',
            'illustration' => '💊',
        ],
        'contraception_condom' => [
            'label' => '保險套', 'hint' => '紀錄妳的選擇', 'rarity' => 'common', 'category' => 'sexual',
            'description' => '有使用保險套的紀錄。許多朋友會留這個紀錄做為週期參考。',
            'why_text' => '紀錄是給妳自己看的，方便回顧。',
            'comfort_actions' => [],
            'dodo_companion' => '紀錄完成。安全是好的選擇。',
            'illustration' => '🛡️',
        ],

        // ─── FERTILITY 9 ───
        'ovulation_pain' => [
            'label' => '排卵期悶痛', 'hint' => '排卵的小訊號', 'rarity' => 'rare', 'category' => 'fertility',
            'description' => '單側下腹一邊悶悶的，通常持續幾小時到一天。許多朋友會在週期中段感到。',
            'why_text' => '卵巢釋出卵子時的小訊號，左右會輪流，是身體很細膩的提醒。',
            'comfort_actions' => ['ovulation_log_cm', 'ovulation_outdoor_walk_20min'],
            'dodo_companion' => '今天身體在排卵，朵朵幫妳記下時間。',
            'illustration' => '🌸',
        ],
        'spotting' => [
            'label' => '少量出血', 'hint' => '若連續多天請就醫', 'rarity' => 'rare', 'category' => 'fertility',
            'description' => '不是經期、但有少量血絲。許多朋友會在排卵期或經前感到。',
            'why_text' => '可能是排卵期短暫荷爾蒙變化、或內膜偶爾的小剝落。連續多天值得就醫看看。',
            'comfort_actions' => ['late_consider_clinic_visit'],
            'dodo_companion' => '紀錄起來。如果連續超過 3 天，朵朵建議找醫師聊聊。',
            'illustration' => '🩸',
        ],
        'bbt_high' => [
            'label' => '基礎體溫偏高', 'hint' => '黃體期常見', 'rarity' => 'rare', 'category' => 'fertility',
            'description' => '清晨還沒下床測，體溫比平常高一點。許多朋友會在排卵後到經期前看到雙相溫度。',
            'why_text' => '黃體期身體會稍微提溫，是排卵後正常的訊號。',
            'comfort_actions' => ['ovulation_learn_fertility_window'],
            'dodo_companion' => '雙相 BBT 是身體很努力在工作的證據。',
            'illustration' => '🌡️',
        ],
        'discharge_dry' => [
            'label' => '分泌物：乾燥', 'hint' => '濾泡期常見', 'rarity' => 'rare', 'category' => 'fertility',
            'description' => '幾乎沒有分泌物，內褲是乾的。許多朋友會在經期剛結束的幾天感到。',
            'why_text' => '荷爾蒙還沒升高，是濾泡期早期的正常訊號。',
            'comfort_actions' => ['follicular_water_2L'],
            'dodo_companion' => '紀錄起來。每個分泌物變化都是身體在告訴妳節奏。',
            'illustration' => '🍂',
        ],
        'discharge_creamy' => [
            'label' => '分泌物：乳狀', 'hint' => '濾泡期 / 黃體期', 'rarity' => 'rare', 'category' => 'fertility',
            'description' => '白白濃濃的分泌物，像乳液。許多朋友會在排卵前或排卵後感到。',
            'why_text' => '荷爾蒙上升讓子宮頸黏液變厚，是身體準備中的訊號。',
            'comfort_actions' => ['ovulation_log_cm'],
            'dodo_companion' => '正常。紀錄起來，下個週期可以對照。',
            'illustration' => '🥛',
        ],
        'discharge_egg_white' => [
            'label' => '分泌物：蛋清狀', 'hint' => '排卵期訊號', 'rarity' => 'epic', 'category' => 'fertility',
            'description' => '透明、會拉絲、像蛋白。許多朋友會在排卵的 2-3 天內看到。',
            'why_text' => '雌激素高峰讓子宮頸黏液最稀，是身體在說「現在是排卵窗」。',
            'comfort_actions' => ['ovulation_log_cm', 'ovulation_learn_fertility_window'],
            'dodo_companion' => '蛋清狀分泌物是排卵的金卡訊號。朵朵替妳記下。',
            'illustration' => '🥚',
        ],
        'discharge_watery' => [
            'label' => '分泌物：水狀', 'hint' => '接近排卵期', 'rarity' => 'rare', 'category' => 'fertility',
            'description' => '清清水水、量比較多。許多朋友會在接近排卵期的前兩天感到。',
            'why_text' => '雌激素開始上升，子宮頸黏液變稀，是身體在準備排卵的前奏。',
            'comfort_actions' => ['ovulation_log_cm'],
            'dodo_companion' => '紀錄起來。蛋清狀分泌物可能下一兩天會出現。',
            'illustration' => '💧',
        ],
        'pregnancy_test_negative' => [
            'label' => '驗孕：未懷孕', 'hint' => '紀錄妳的紀錄', 'rarity' => 'rare', 'category' => 'fertility',
            'description' => '驗孕棒一條線。許多備孕朋友會在這時候有複雜的心情，這是正常的。',
            'why_text' => 'hCG 還沒被偵測到。可能是太早驗、也可能是這次還沒。下個週期再試。',
            'comfort_actions' => ['late_destress_one_thing', 'late_say_to_body'],
            'dodo_companion' => '無論結果如何，朵朵都在。我們慢慢來。',
            'illustration' => '🔎',
        ],
        'pregnancy_test_positive' => [
            'label' => '驗孕：陽性', 'hint' => '人生新章節', 'rarity' => 'legendary', 'category' => 'fertility',
            'description' => '兩條線。妳的身體進入了一個全新的章節。朵朵替妳屏住呼吸。',
            'why_text' => 'hCG 升高被驗孕棒偵測到，這是身體的奇蹟訊號。',
            'comfort_actions' => [],
            'dodo_companion' => '不論妳此刻心情是什麼，朵朵都在。我們慢慢來。',
            'illustration' => '🌟',
        ],
    ],
];
