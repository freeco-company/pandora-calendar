<?php

/**
 * 孕期 40 週內容（P4 孕期模式）
 *
 * 設計原則：
 *   - 朵朵語氣（暖、近距離朋友、用「妳 / 朋友 / 夥伴」）
 *   - 食安法 §28 / 健食法 §14 合規 — 過 LegalContentSanitizer 禁用詞清單
 *   - 醫療相關建議統一引導「諮詢產科醫師」，不下指導
 *   - 胎兒大小用「跟 X 差不多」+ emoji（避免照片 / 圖示再被當成醫療廣告）
 *
 * suggested_actions key 對應 frontend 行動（早期=葉酸 / 中期=產檢拍肚照 / 晚期=陣痛包胎動）
 */

return [
    'weeks' => [
        1 => [
            'week' => 1,
            'trimester' => 1,
            'size_comparison' => '一顆芝麻',
            'size_emoji' => '·',
            'dodo_message' => '朋友妳剛開始這趟旅程 🌱 朵朵會陪著妳，每週給妳一些溫柔的提醒。',
            'suggested_actions' => [
                ['key' => 'folate', 'label' => '記得每天補充葉酸（建議諮詢產科醫師劑量）'],
                ['key' => 'consult', 'label' => '安排第一次婦產科諮詢'],
            ],
        ],
        2 => [
            'week' => 2,
            'trimester' => 1,
            'size_comparison' => '一顆芝麻',
            'size_emoji' => '·',
            'dodo_message' => '朵朵知道現在身體可能還沒明顯變化，慢慢來，妳不用急。',
            'suggested_actions' => [
                ['key' => 'folate', 'label' => '葉酸是這個階段的好夥伴，記得每天補充'],
                ['key' => 'rest', 'label' => '避免劇烈運動、好好休息'],
            ],
        ],
        3 => [
            'week' => 3,
            'trimester' => 1,
            'size_comparison' => '一顆罌粟籽',
            'size_emoji' => '·',
            'dodo_message' => '寶寶剛剛抵達 ✨ 妳可能還沒感覺到，但身體已經很努力在準備了。',
            'suggested_actions' => [
                ['key' => 'folate', 'label' => '繼續每天的葉酸'],
                ['key' => 'avoid_alcohol', 'label' => '避開酒精、二手菸'],
            ],
        ],
        4 => [
            'week' => 4,
            'trimester' => 1,
            'size_comparison' => '一顆罌粟籽',
            'size_emoji' => '·',
            'dodo_message' => '這週很多朋友會用驗孕棒看到結果。如果妳剛知道，深呼吸，朵朵在這 💛',
            'suggested_actions' => [
                ['key' => 'consult', 'label' => '預約第一次產檢時間'],
                ['key' => 'folate', 'label' => '葉酸繼續喔'],
            ],
        ],
        5 => [
            'week' => 5,
            'trimester' => 1,
            'size_comparison' => '一顆芝麻粒',
            'size_emoji' => '·',
            'dodo_message' => '心跳開始發芽 🌸 妳的身體正在做一件很神奇的事。',
            'suggested_actions' => [
                ['key' => 'consult', 'label' => '產檢可安排聽心跳'],
                ['key' => 'rest', 'label' => '疲倦是這階段的常客，妳可以多躺一下'],
            ],
        ],
        6 => [
            'week' => 6,
            'trimester' => 1,
            'size_comparison' => '一顆扁豆',
            'size_emoji' => '🫘',
            'dodo_message' => '朋友如果妳開始有孕吐，朵朵想抱抱妳。試試少量多餐，餓肚子有時反而會更不舒服。',
            'suggested_actions' => [
                ['key' => 'small_meals', 'label' => '少量多餐，乾糧放床邊'],
                ['key' => 'consult', 'label' => '孕吐難受到無法進食請聯絡產科醫師'],
            ],
        ],
        7 => [
            'week' => 7,
            'trimester' => 1,
            'size_comparison' => '一顆藍莓',
            'size_emoji' => '🫐',
            'dodo_message' => '寶寶這週開始長出小芽 🌿 妳的疲倦不是錯覺，是身體在用力。',
            'suggested_actions' => [
                ['key' => 'rest', 'label' => '允許自己午睡 30 分鐘'],
                ['key' => 'hydrate', 'label' => '記得喝水'],
            ],
        ],
        8 => [
            'week' => 8,
            'trimester' => 1,
            'size_comparison' => '一顆覆盆莓',
            'size_emoji' => '🍓',
            'dodo_message' => '朵朵今天想對妳說：身材還看不出來，但妳已經是一個媽媽了。',
            'suggested_actions' => [
                ['key' => 'consult', 'label' => '安排第一次正式產檢（約 8-10 週）'],
                ['key' => 'avoid_strain', 'label' => '避免提重物、避免劇烈運動'],
            ],
        ],
        9 => [
            'week' => 9,
            'trimester' => 1,
            'size_comparison' => '一顆櫻桃',
            'size_emoji' => '🍒',
            'dodo_message' => '寶寶現在開始有手指了！朋友妳辛苦了，多照顧自己。',
            'suggested_actions' => [
                ['key' => 'consult', 'label' => '產檢可問醫師關於唐氏症篩檢時程'],
                ['key' => 'gentle_walk', 'label' => '散步是不錯的溫和運動'],
            ],
        ],
        10 => [
            'week' => 10,
            'trimester' => 1,
            'size_comparison' => '一顆草莓',
            'size_emoji' => '🍓',
            'dodo_message' => '進入第 10 週了 🌷 寶寶長得很努力，妳也很努力。',
            'suggested_actions' => [
                ['key' => 'screening', 'label' => '可詢問醫師 NIPT / 初唐篩檢時程'],
                ['key' => 'rest', 'label' => '疲倦時不要硬撐'],
            ],
        ],
        11 => [
            'week' => 11,
            'trimester' => 1,
            'size_comparison' => '一顆無花果',
            'size_emoji' => '🟢',
            'dodo_message' => '朋友再撐一下，過了第一孕期身體會輕鬆許多。',
            'suggested_actions' => [
                ['key' => 'screening', 'label' => '11-13 週初唐篩檢黃金期'],
                ['key' => 'hydrate', 'label' => '多喝溫開水'],
            ],
        ],
        12 => [
            'week' => 12,
            'trimester' => 1,
            'size_comparison' => '一顆萊姆',
            'size_emoji' => '🍋',
            'dodo_message' => '快要進入第二孕期了 ✨ 流產風險明顯下降，妳穩穩地走到了這。',
            'suggested_actions' => [
                ['key' => 'screening', 'label' => '12 週超音波 + NT 頸部透明帶檢查'],
                ['key' => 'announce', 'label' => '可以開始想：什麼時候告訴家人'],
            ],
        ],
        13 => [
            'week' => 13,
            'trimester' => 1,
            'size_comparison' => '一顆檸檬',
            'size_emoji' => '🍋',
            'dodo_message' => '第一孕期最後一週，妳真的好棒。',
            'suggested_actions' => [
                ['key' => 'screening', 'label' => '把握初唐篩檢最後時段'],
                ['key' => 'rest', 'label' => '繼續好好休息'],
            ],
        ],
        14 => [
            'week' => 14,
            'trimester' => 2,
            'size_comparison' => '一顆桃子',
            'size_emoji' => '🍑',
            'dodo_message' => '進入第二孕期 🌸 通常孕吐會慢慢退場，朋友妳會輕鬆一些。',
            'suggested_actions' => [
                ['key' => 'belly_photo', 'label' => '每週拍一張肚肚照，留下回憶'],
                ['key' => 'gentle_exercise', 'label' => '可開始溫和的孕婦瑜珈'],
            ],
        ],
        15 => [
            'week' => 15,
            'trimester' => 2,
            'size_comparison' => '一顆酪梨',
            'size_emoji' => '🥑',
            'dodo_message' => '寶寶開始會做表情了 🥺 雖然妳還感受不到，但 ta 在跟妳互動。',
            'suggested_actions' => [
                ['key' => 'belly_photo', 'label' => '記得拍肚肚照'],
                ['key' => 'screening', 'label' => '15-20 週可做羊膜穿刺（依醫師建議）'],
            ],
        ],
        16 => [
            'week' => 16,
            'trimester' => 2,
            'size_comparison' => '一顆酪梨',
            'size_emoji' => '🥑',
            'dodo_message' => '朋友妳辛苦了 💛 這週開始有些媽媽會感覺到第一次胎動。',
            'suggested_actions' => [
                ['key' => 'consult', 'label' => '產檢頻率約每月一次'],
                ['key' => 'gentle_walk', 'label' => '每天散步 20-30 分鐘是不錯的'],
            ],
        ],
        17 => [
            'week' => 17,
            'trimester' => 2,
            'size_comparison' => '一顆蘋果',
            'size_emoji' => '🍎',
            'dodo_message' => '寶寶長得很穩了。朵朵覺得妳今天值得吃一個小點心。',
            'suggested_actions' => [
                ['key' => 'belly_photo', 'label' => '拍肚肚照'],
                ['key' => 'sleep_side', 'label' => '建議慢慢養成左側躺習慣'],
            ],
        ],
        18 => [
            'week' => 18,
            'trimester' => 2,
            'size_comparison' => '一顆甜椒',
            'size_emoji' => '🫑',
            'dodo_message' => '胎動可能更明顯了，朋友妳第一次感覺到的時候會記一輩子。',
            'suggested_actions' => [
                ['key' => 'screening', 'label' => '18-22 週可安排高層次超音波'],
                ['key' => 'belly_photo', 'label' => '繼續拍肚肚照'],
            ],
        ],
        19 => [
            'week' => 19,
            'trimester' => 2,
            'size_comparison' => '一顆芒果',
            'size_emoji' => '🥭',
            'dodo_message' => '寶寶會聽到外面的聲音了 🎵 妳可以開始跟 ta 說話喔。',
            'suggested_actions' => [
                ['key' => 'talk_to_baby', 'label' => '對寶寶說說話'],
                ['key' => 'gentle_exercise', 'label' => '繼續溫和運動'],
            ],
        ],
        20 => [
            'week' => 20,
            'trimester' => 2,
            'size_comparison' => '一根香蕉',
            'size_emoji' => '🍌',
            'dodo_message' => '一半了 🎉 朋友妳走到孕期的中點了，給自己一個擁抱。',
            'suggested_actions' => [
                ['key' => 'screening', 'label' => '高層次超音波黃金時段'],
                ['key' => 'belly_photo', 'label' => '20 週紀念拍照'],
            ],
        ],
        21 => [
            'week' => 21,
            'trimester' => 2,
            'size_comparison' => '一根紅蘿蔔',
            'size_emoji' => '🥕',
            'dodo_message' => '寶寶會踢踢了 🦶 那是 ta 在打招呼。',
            'suggested_actions' => [
                ['key' => 'kick_count', 'label' => '可開始記錄胎動感受'],
                ['key' => 'hydrate', 'label' => '多補充水分'],
            ],
        ],
        22 => [
            'week' => 22,
            'trimester' => 2,
            'size_comparison' => '一顆木瓜',
            'size_emoji' => '🟠',
            'dodo_message' => '朵朵想提醒妳：腰會開始痠，記得選一張支撐好的椅子。',
            'suggested_actions' => [
                ['key' => 'posture', 'label' => '注意姿勢，必要時加靠墊'],
                ['key' => 'belly_photo', 'label' => '拍肚肚照'],
            ],
        ],
        23 => [
            'week' => 23,
            'trimester' => 2,
            'size_comparison' => '一顆大芒果',
            'size_emoji' => '🥭',
            'dodo_message' => '寶寶現在約 500 克。妳的身體正在用心打造這個小生命。',
            'suggested_actions' => [
                ['key' => 'kick_count', 'label' => '記錄胎動'],
                ['key' => 'consult', 'label' => '24-28 週準備做妊娠糖尿病篩檢'],
            ],
        ],
        24 => [
            'week' => 24,
            'trimester' => 2,
            'size_comparison' => '一根玉米',
            'size_emoji' => '🌽',
            'dodo_message' => '朋友妳到了重要的里程碑 — 這週後寶寶有體外存活的可能了 💛',
            'suggested_actions' => [
                ['key' => 'gd_screening', 'label' => '安排妊娠糖尿病篩檢'],
                ['key' => 'rest', 'label' => '腳開始水腫的話多抬腿'],
            ],
        ],
        25 => [
            'week' => 25,
            'trimester' => 2,
            'size_comparison' => '一顆白花椰菜',
            'size_emoji' => '🥦',
            'dodo_message' => '寶寶開始有作息了，會醒會睡。妳們已經有一點默契。',
            'suggested_actions' => [
                ['key' => 'kick_count', 'label' => '感受寶寶的活動規律'],
                ['key' => 'sleep_side', 'label' => '左側躺有助血液循環'],
            ],
        ],
        26 => [
            'week' => 26,
            'trimester' => 2,
            'size_comparison' => '一個酪梨',
            'size_emoji' => '🥑',
            'dodo_message' => '朵朵想跟妳說：累了就停一下，不必逞強。',
            'suggested_actions' => [
                ['key' => 'gd_screening', 'label' => '完成妊娠糖尿病篩檢'],
                ['key' => 'belly_photo', 'label' => '拍肚肚照'],
            ],
        ],
        27 => [
            'week' => 27,
            'trimester' => 2,
            'size_comparison' => '一顆花椰菜',
            'size_emoji' => '🥦',
            'dodo_message' => '第二孕期最後一週了 🌷 妳一路走得很穩。',
            'suggested_actions' => [
                ['key' => 'birth_class', 'label' => '可開始打聽媽媽教室'],
                ['key' => 'kick_count', 'label' => '繼續記胎動'],
            ],
        ],
        28 => [
            'week' => 28,
            'trimester' => 3,
            'size_comparison' => '一顆茄子',
            'size_emoji' => '🍆',
            'dodo_message' => '進入第三孕期 🌟 朋友妳很棒，最後一段路了。',
            'suggested_actions' => [
                ['key' => 'kick_count', 'label' => '每天記錄胎動次數'],
                ['key' => 'consult', 'label' => '產檢頻率變兩週一次'],
            ],
        ],
        29 => [
            'week' => 29,
            'trimester' => 3,
            'size_comparison' => '一顆南瓜',
            'size_emoji' => '🎃',
            'dodo_message' => '寶寶現在開始變胖嘟嘟了 🥺 妳的肚子也是。',
            'suggested_actions' => [
                ['key' => 'birth_class', 'label' => '報名媽媽教室'],
                ['key' => 'kick_count', 'label' => '胎動異常請聯絡醫師'],
            ],
        ],
        30 => [
            'week' => 30,
            'trimester' => 3,
            'size_comparison' => '一顆高麗菜',
            'size_emoji' => '🥬',
            'dodo_message' => '剩下 10 週了 ✨ 朵朵替妳數倒數。',
            'suggested_actions' => [
                ['key' => 'hospital_bag', 'label' => '可以開始準備待產包清單'],
                ['key' => 'belly_photo', 'label' => '拍肚肚照'],
            ],
        ],
        31 => [
            'week' => 31,
            'trimester' => 3,
            'size_comparison' => '一顆椰子',
            'size_emoji' => '🥥',
            'dodo_message' => '腰背的負擔會更明顯。朋友妳做得到。',
            'suggested_actions' => [
                ['key' => 'birth_plan', 'label' => '與醫師討論生產計畫'],
                ['key' => 'sleep_side', 'label' => '繼續左側躺'],
            ],
        ],
        32 => [
            'week' => 32,
            'trimester' => 3,
            'size_comparison' => '一根大白蘿蔔',
            'size_emoji' => '🥬',
            'dodo_message' => '寶寶開始喬位置 🐣 妳有時會覺得肚子緊緊的，那是練習收縮。',
            'suggested_actions' => [
                ['key' => 'hospital_bag', 'label' => '開始準備待產包'],
                ['key' => 'kick_count', 'label' => '胎動明顯減少請就醫'],
            ],
        ],
        33 => [
            'week' => 33,
            'trimester' => 3,
            'size_comparison' => '一顆鳳梨',
            'size_emoji' => '🍍',
            'dodo_message' => '寶寶骨頭逐漸變硬，但頭部還會繼續發育，準備迎接出生。',
            'suggested_actions' => [
                ['key' => 'birth_class', 'label' => '生產知識補課'],
                ['key' => 'kick_count', 'label' => '記錄胎動'],
            ],
        ],
        34 => [
            'week' => 34,
            'trimester' => 3,
            'size_comparison' => '一顆哈密瓜',
            'size_emoji' => '🍈',
            'dodo_message' => '剩下不到 6 週了。朵朵覺得妳是這世界上最勇敢的人之一。',
            'suggested_actions' => [
                ['key' => 'hospital_bag', 'label' => '待產包進度檢查'],
                ['key' => 'consult', 'label' => '產檢頻率改一週一次'],
            ],
        ],
        35 => [
            'week' => 35,
            'trimester' => 3,
            'size_comparison' => '一顆水蜜桃',
            'size_emoji' => '🍑',
            'dodo_message' => '寶寶幾乎準備好了。朋友妳也快了 💛',
            'suggested_actions' => [
                ['key' => 'gbs', 'label' => '35-37 週做 GBS 乙型鏈球菌篩檢'],
                ['key' => 'rest', 'label' => '腳水腫多抬腿'],
            ],
        ],
        36 => [
            'week' => 36,
            'trimester' => 3,
            'size_comparison' => '一顆蘿蔓萵苣',
            'size_emoji' => '🥬',
            'dodo_message' => '進入足月倒數，寶寶可能隨時會準備出生。',
            'suggested_actions' => [
                ['key' => 'hospital_bag', 'label' => '待產包放車上 / 玄關'],
                ['key' => 'birth_plan', 'label' => '確認生產醫院動線'],
            ],
        ],
        37 => [
            'week' => 37,
            'trimester' => 3,
            'size_comparison' => '一顆冬瓜小段',
            'size_emoji' => '🍉',
            'dodo_message' => '足月了 🌟 寶寶現在出生都是健康的。妳辛苦了。',
            'suggested_actions' => [
                ['key' => 'contraction_signs', 'label' => '熟悉真假性陣痛差別'],
                ['key' => 'partner_alert', 'label' => '跟陪產夥伴對齊聯絡方式'],
            ],
        ],
        38 => [
            'week' => 38,
            'trimester' => 3,
            'size_comparison' => '一顆韭蔥',
            'size_emoji' => '🥬',
            'dodo_message' => '隨時準備見面 ✨ 朋友妳的勇敢朵朵都看到了。',
            'suggested_actions' => [
                ['key' => 'contraction_signs', 'label' => '密切留意規律陣痛'],
                ['key' => 'kick_count', 'label' => '胎動減少立刻就醫'],
            ],
        ],
        39 => [
            'week' => 39,
            'trimester' => 3,
            'size_comparison' => '一顆小西瓜',
            'size_emoji' => '🍉',
            'dodo_message' => '寶寶可能在這週或下週見到妳。深呼吸，妳會很厲害。',
            'suggested_actions' => [
                ['key' => 'contraction_signs', 'label' => '陣痛 5-1-1 法則：5 分鐘一次、痛 1 分鐘、持續 1 小時 → 上醫院'],
                ['key' => 'partner_alert', 'label' => '陪產夥伴待命'],
            ],
        ],
        40 => [
            'week' => 40,
            'trimester' => 3,
            'size_comparison' => '一顆南瓜',
            'size_emoji' => '🎃',
            'dodo_message' => '預產期到了 🎀 寶寶可能今天，也可能再等一週，妳已經做到最棒。',
            'suggested_actions' => [
                ['key' => 'contraction_signs', 'label' => '留意陣痛規律與破水訊號'],
                ['key' => 'consult', 'label' => '超過 41 週請與醫師討論引產'],
            ],
        ],
        41 => [
            'week' => 41,
            'trimester' => 3,
            'size_comparison' => '一顆南瓜',
            'size_emoji' => '🎃',
            'dodo_message' => '寶寶想多待一點點 💛 朵朵陪妳等，但要密切跟醫師保持聯繫。',
            'suggested_actions' => [
                ['key' => 'consult', 'label' => '與產科醫師討論引產時程'],
                ['key' => 'kick_count', 'label' => '密切記錄胎動'],
            ],
        ],
        42 => [
            'week' => 42,
            'trimester' => 3,
            'size_comparison' => '一顆南瓜',
            'size_emoji' => '🎃',
            'dodo_message' => '朋友這週請務必跟醫師密切討論，朵朵在這陪妳到最後一步。',
            'suggested_actions' => [
                ['key' => 'consult', 'label' => '立即聯絡產科醫師'],
                ['key' => 'kick_count', 'label' => '胎動是寶寶健康訊號'],
            ],
        ],
    ],
];
