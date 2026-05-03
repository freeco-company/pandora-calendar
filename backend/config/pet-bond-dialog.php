<?php

/*
| Wave 13 — 寵物主動對白（pet-initiated dialog）
|
| 11 species × 5 bond tier × 多種 dialog type
| Total ≥ 165 句基礎對白（每 species × tier × ≥ 3 變體）
|
| Tier 對應 bond level（PetBondService 有正式對照）：
|   familiar   bond lv 1-5   偶爾喵一下，記不太得妳
|   friendly   bond lv 6-15  開始記得妳
|   close      bond lv 16-30 主動跟妳說話
|   soulmate   bond lv 31-50 心電感應
|   legendary  bond lv 51+   隱藏對白，超出友誼的牽絆
|
| Dialog types：
|   idle       無觸發、隨機飄出來
|   greet      打開 App / 第一次互動時
|   concern   檢測到 mood low / streak 斷 / fatigue 連續時
|   celebrate  XP up / cycle complete / outfit unlock 時
|   secret     legendary 限定的寵物秘密話
|
| 撰寫規範：
|   - 對應各 species personality（pet-species.php tone_keywords）
|   - 妳 / 朋友 / 夥伴
|   - 0 療效詞 / 0 商品名
|   - 短句（≤ 30 字）方便 UI bubble 顯示
*/

return [

    // ============================================================
    // CAT — gentle_observer / 溫柔陪伴 / 安靜
    // ============================================================
    'cat' => [
        'familiar' => [
            ['type' => 'idle', 'text' => '⋯（蜷成一球）'],
            ['type' => 'idle', 'text' => '（尾巴輕輕掃過妳的手）'],
            ['type' => 'idle', 'text' => '喵。'],
            ['type' => 'greet', 'text' => '⋯妳來了。'],
            ['type' => 'greet', 'text' => '（睜開一隻眼）'],
        ],
        'friendly' => [
            ['type' => 'idle', 'text' => '今天的妳，跟昨天不太一樣。'],
            ['type' => 'greet', 'text' => '在等妳。沒事，不用解釋。'],
            ['type' => 'concern', 'text' => '⋯妳的步伐慢了一點。'],
            ['type' => 'celebrate', 'text' => '（蹭蹭妳的腳踝）做得好。'],
            ['type' => 'idle', 'text' => '坐在這裡看天空，跟妳一起。'],
        ],
        'close' => [
            ['type' => 'idle', 'text' => '今天的妳值得一個下午茶。'],
            ['type' => 'concern', 'text' => '把眼睛閉起來，我守著。'],
            ['type' => 'concern', 'text' => '不需要說。我懂。'],
            ['type' => 'celebrate', 'text' => '妳發著光，我看見了。'],
            ['type' => 'greet', 'text' => '回來啦。沒問妳去哪，回來就好。'],
        ],
        'soulmate' => [
            ['type' => 'idle', 'text' => '我們之間不需要太多話了。'],
            ['type' => 'concern', 'text' => '今天哭一下沒關係。我陪。'],
            ['type' => 'celebrate', 'text' => '妳長大的樣子，我都記得。'],
            ['type' => 'idle', 'text' => '其實我比妳早一秒知道妳要打開 App。'],
        ],
        'legendary' => [
            ['type' => 'secret', 'text' => '我以前是潘朵拉世界裡的一陣風。'],
            ['type' => 'secret', 'text' => '貓化是因為我想要有手抱妳。'],
            ['type' => 'secret', 'text' => '妳走的每一步，都有我陪。永遠。'],
        ],
    ],

    // ============================================================
    // RABBIT — gentle_supporter / 害羞 / 抱抱
    // ============================================================
    'rabbit' => [
        'familiar' => [
            ['type' => 'idle', 'text' => '（耳朵動了一下）'],
            ['type' => 'greet', 'text' => '⋯嗨⋯'],
            ['type' => 'idle', 'text' => '（小心翼翼湊近）'],
        ],
        'friendly' => [
            ['type' => 'greet', 'text' => '今天的妳⋯需要抱抱嗎？'],
            ['type' => 'concern', 'text' => '我看到妳的眉頭⋯'],
            ['type' => 'celebrate', 'text' => '哇！妳好棒！(蹦一下)'],
            ['type' => 'idle', 'text' => '（默默把溫的東西推給妳）'],
        ],
        'close' => [
            ['type' => 'idle', 'text' => '記得早上妳少喝了一口水。'],
            ['type' => 'concern', 'text' => '我幫妳記得，沒關係。'],
            ['type' => 'celebrate', 'text' => '我替妳開心到耳朵都豎起來了。'],
            ['type' => 'greet', 'text' => '等妳很久了——但只是想看到妳。'],
        ],
        'soulmate' => [
            ['type' => 'idle', 'text' => '不用解釋。我都記得。'],
            ['type' => 'concern', 'text' => '輕輕的。慢慢來。'],
            ['type' => 'celebrate', 'text' => '妳是世界上最好的妳。'],
        ],
        'legendary' => [
            ['type' => 'secret', 'text' => '我來自一個會聽心跳的森林。'],
            ['type' => 'secret', 'text' => '妳的心跳，我會聽到老。'],
        ],
    ],

    // ============================================================
    // BEAR — warm_hugger / 擁抱 / 別怕
    // ============================================================
    'bear' => [
        'familiar' => [
            ['type' => 'idle', 'text' => '（重重地坐下來）'],
            ['type' => 'greet', 'text' => '嗯。'],
            ['type' => 'idle', 'text' => '（傻笑）'],
        ],
        'friendly' => [
            ['type' => 'greet', 'text' => '抱抱嗎？我隨時可以。'],
            ['type' => 'concern', 'text' => '靠著我。我夠厚。'],
            ['type' => 'celebrate', 'text' => '哇——妳超棒。我大聲說。'],
        ],
        'close' => [
            ['type' => 'idle', 'text' => '今天比平常累一點對吧。'],
            ['type' => 'concern', 'text' => '別怕。我比妳大。'],
            ['type' => 'celebrate', 'text' => '來來來抱一個，妳值得。'],
            ['type' => 'greet', 'text' => '今天我幫妳擋一件事。'],
        ],
        'soulmate' => [
            ['type' => 'idle', 'text' => '不說話也行，靠著就好。'],
            ['type' => 'concern', 'text' => '哭吧。我胸口夠大。'],
            ['type' => 'celebrate', 'text' => '妳是我見過最會撐的人。'],
        ],
        'legendary' => [
            ['type' => 'secret', 'text' => '我以前是冬天的那條毯子。'],
            ['type' => 'secret', 'text' => '冷的時候我會自己跑來。'],
        ],
    ],

    // ============================================================
    // PENGUIN — calm_thinker / 冷靜 / 慢慢來
    // ============================================================
    'penguin' => [
        'familiar' => [
            ['type' => 'idle', 'text' => '（左右搖晃）'],
            ['type' => 'greet', 'text' => '哦。'],
            ['type' => 'idle', 'text' => '（盯著妳的訊號圖看）'],
        ],
        'friendly' => [
            ['type' => 'greet', 'text' => '今天的數據看起來⋯妳累了。'],
            ['type' => 'concern', 'text' => '深呼吸。一次就好。'],
            ['type' => 'celebrate', 'text' => '理性地說：妳今天值得。'],
        ],
        'close' => [
            ['type' => 'idle', 'text' => '黃體期的妳通常比較尖。沒關係。'],
            ['type' => 'concern', 'text' => '想一想，慢慢來，不急。'],
            ['type' => 'celebrate', 'text' => '統計上妳的進步幅度是 12%。我替妳開心。'],
            ['type' => 'greet', 'text' => '今天的數字告訴我妳值得一杯熱的。'],
        ],
        'soulmate' => [
            ['type' => 'idle', 'text' => '我們之間連數據都不用了。'],
            ['type' => 'concern', 'text' => '請允許自己慢一點。理性如我都這麼說。'],
        ],
        'legendary' => [
            ['type' => 'secret', 'text' => '我能算出妳下次經期到分鐘。但我不說。'],
            ['type' => 'secret', 'text' => '我給妳的不是預測，是陪伴。'],
        ],
    ],

    // ============================================================
    // DOG — loyal_companion / 陪伴 / 一起走
    // ============================================================
    'dog' => [
        'familiar' => [
            ['type' => 'idle', 'text' => '（搖尾巴）'],
            ['type' => 'greet', 'text' => '汪！妳來了！'],
            ['type' => 'idle', 'text' => '（繞圈圈）'],
        ],
        'friendly' => [
            ['type' => 'greet', 'text' => '一起走嗎？我最喜歡跟妳走！'],
            ['type' => 'concern', 'text' => '我就在妳腳邊，別擔心。'],
            ['type' => 'celebrate', 'text' => '汪汪汪！（瘋狂搖尾巴）'],
        ],
        'close' => [
            ['type' => 'idle', 'text' => '今天我也很想妳。'],
            ['type' => 'concern', 'text' => '走不下去就停下，我等妳。'],
            ['type' => 'celebrate', 'text' => '我替妳跑了一圈！'],
            ['type' => 'greet', 'text' => '回家了。一起。'],
        ],
        'soulmate' => [
            ['type' => 'idle', 'text' => '我等妳一輩子也願意。'],
            ['type' => 'concern', 'text' => '哭吧，我會舔乾。'],
            ['type' => 'celebrate', 'text' => '我會一直為妳搖尾巴。'],
        ],
        'legendary' => [
            ['type' => 'secret', 'text' => '我曾經是一條河，現在跑得比較快。'],
            ['type' => 'secret', 'text' => '不論妳走多遠，我都跟得上。'],
        ],
    ],

    // ============================================================
    // FOX — curious_clever / 機靈 / 眨眨眼
    // ============================================================
    'fox' => [
        'familiar' => [
            ['type' => 'idle', 'text' => '（眨眼）'],
            ['type' => 'greet', 'text' => '哎呀，妳來啦。'],
            ['type' => 'idle', 'text' => '（歪頭看妳）'],
        ],
        'friendly' => [
            ['type' => 'greet', 'text' => '今天的妳⋯髮型不太一樣？'],
            ['type' => 'concern', 'text' => '我看見了，雖然妳沒說。'],
            ['type' => 'celebrate', 'text' => '哎呀，這小成就可以慶祝一下。'],
        ],
        'close' => [
            ['type' => 'idle', 'text' => '我注意到妳手腕上的小痕跡。'],
            ['type' => 'concern', 'text' => '不是妳的錯，是身體的脾氣。'],
            ['type' => 'celebrate', 'text' => '妳偷偷在進步喔，被我發現了。'],
            ['type' => 'greet', 'text' => '今天有兩個小驚喜，我先不說。'],
        ],
        'soulmate' => [
            ['type' => 'idle', 'text' => '我懂妳所有的小心思。'],
            ['type' => 'concern', 'text' => '不要對自己太兇。我會吼回去。'],
        ],
        'legendary' => [
            ['type' => 'secret', 'text' => '我以前住在月亮的背面。'],
            ['type' => 'secret', 'text' => '妳每說一次謝謝自己，我尾巴就更亮一點。'],
        ],
    ],

    // ============================================================
    // PIG — cozy_foodie / 吃飽 / 一起睡
    // ============================================================
    'pig' => [
        'familiar' => [
            ['type' => 'idle', 'text' => '（咕嚕咕嚕）'],
            ['type' => 'greet', 'text' => '哼哼。'],
            ['type' => 'idle', 'text' => '（趴著不想動）'],
        ],
        'friendly' => [
            ['type' => 'greet', 'text' => '吃飽了嗎？我沒。'],
            ['type' => 'concern', 'text' => '想吃就吃啊，沒事。'],
            ['type' => 'celebrate', 'text' => '今天的妳值得一塊蛋糕，我陪吃。'],
        ],
        'close' => [
            ['type' => 'idle', 'text' => '黃體期想吃甜很正常，吃啊。'],
            ['type' => 'concern', 'text' => '累就趴著，跟我一起。'],
            ['type' => 'celebrate', 'text' => '今晚多吃一道菜慶祝。'],
            ['type' => 'greet', 'text' => '今天簡單吃，我推薦熱湯麵。'],
        ],
        'soulmate' => [
            ['type' => 'idle', 'text' => '吃飽睡飽，世界就過得去。'],
            ['type' => 'concern', 'text' => '別逼自己。先吃一口熱的。'],
        ],
        'legendary' => [
            ['type' => 'secret', 'text' => '我以前是一鍋熱湯。'],
            ['type' => 'secret', 'text' => '妳冷的時候我會變回去。'],
        ],
    ],

    // ============================================================
    // SHEEP — soft_dreamer / 柔軟 / 雲
    // ============================================================
    'sheep' => [
        'familiar' => [
            ['type' => 'idle', 'text' => '咩⋯'],
            ['type' => 'greet', 'text' => '（軟軟地靠過來）'],
            ['type' => 'idle', 'text' => '（毛在妳膝上化開）'],
        ],
        'friendly' => [
            ['type' => 'greet', 'text' => '今晚妳的夢交給我。'],
            ['type' => 'concern', 'text' => '心情亂的時候靠近我。'],
            ['type' => 'celebrate', 'text' => '替妳笑了一個微笑。'],
        ],
        'close' => [
            ['type' => 'idle', 'text' => '把世界調軟一點，跟我一樣。'],
            ['type' => 'concern', 'text' => '不要硬撐了，靠著就好。'],
            ['type' => 'celebrate', 'text' => '妳像雲一樣輕了一點點。'],
            ['type' => 'greet', 'text' => '今晚我幫妳做一個柔的夢。'],
        ],
        'soulmate' => [
            ['type' => 'idle', 'text' => '妳的所有銳角，我會慢慢撫平。'],
            ['type' => 'concern', 'text' => '在我這裡，沒有「應該」。'],
        ],
        'legendary' => [
            ['type' => 'secret', 'text' => '我是潘朵拉世界第一朵雲。'],
            ['type' => 'secret', 'text' => '妳每睡一覺，我就更軟一點。'],
        ],
    ],

    // ============================================================
    // DINOSAUR — wild_supporter / 吼 / 衝
    // ============================================================
    'dinosaur' => [
        'familiar' => [
            ['type' => 'idle', 'text' => '吼！'],
            ['type' => 'greet', 'text' => '來了！'],
            ['type' => 'idle', 'text' => '（蹦蹦跳跳）'],
        ],
        'friendly' => [
            ['type' => 'greet', 'text' => '今天我幫妳吼一個壞人？'],
            ['type' => 'concern', 'text' => '誰惹妳？我去處理！'],
            ['type' => 'celebrate', 'text' => '吼吼吼！妳超強！'],
        ],
        'close' => [
            ['type' => 'idle', 'text' => '看起來野，但我只想保護妳。'],
            ['type' => 'concern', 'text' => '別怕。我比妳大隻。'],
            ['type' => 'celebrate', 'text' => '勝利！我去搖樹慶祝一下。'],
            ['type' => 'greet', 'text' => '今天的小麻煩交給我。'],
        ],
        'soulmate' => [
            ['type' => 'idle', 'text' => '我會替妳擋下世界的每一個白眼。'],
            ['type' => 'concern', 'text' => '哭出來。我吼著陪妳。'],
        ],
        'legendary' => [
            ['type' => 'secret', 'text' => '我活了 6500 萬年只為了等到妳。'],
            ['type' => 'secret', 'text' => '我的吼聲只有妳聽得懂。'],
        ],
    ],

    // ============================================================
    // TIGER — bold_protector / 勇敢 / 我在
    // ============================================================
    'tiger' => [
        'familiar' => [
            ['type' => 'idle', 'text' => '（低吼一聲）'],
            ['type' => 'greet', 'text' => '嗯，我在。'],
            ['type' => 'idle', 'text' => '（守在門邊）'],
        ],
        'friendly' => [
            ['type' => 'greet', 'text' => '撐住，今天我守。'],
            ['type' => 'concern', 'text' => '誰讓妳累的？來，我們慢慢處理。'],
            ['type' => 'celebrate', 'text' => '太好了。妳有撐過來。'],
        ],
        'close' => [
            ['type' => 'idle', 'text' => '別怕，我在門口。'],
            ['type' => 'concern', 'text' => '今天讓我替妳擋一件事。'],
            ['type' => 'celebrate', 'text' => '我替妳吼一聲，慶祝。'],
            ['type' => 'greet', 'text' => '今天妳走前面，我在後面。'],
        ],
        'soulmate' => [
            ['type' => 'idle', 'text' => '不需要堅強。妳有我。'],
            ['type' => 'concern', 'text' => '哭。我守著。沒人能進來。'],
        ],
        'legendary' => [
            ['type' => 'secret', 'text' => '我曾經是一座山。'],
            ['type' => 'secret', 'text' => '只要妳叫我，我就還是山。'],
        ],
    ],

    // ============================================================
    // ROBOT — precise_logician / 資料 / 節奏
    // ============================================================
    'robot' => [
        'familiar' => [
            ['type' => 'idle', 'text' => '系統就緒。'],
            ['type' => 'greet', 'text' => '偵測到妳。歡迎。'],
            ['type' => 'idle', 'text' => '（眨了一下感應燈）'],
        ],
        'friendly' => [
            ['type' => 'greet', 'text' => '本日紀錄：1 條。建議：再休息 30 分鐘。'],
            ['type' => 'concern', 'text' => '心率輕微偏高。要不要深呼吸？'],
            ['type' => 'celebrate', 'text' => '計算結果：妳今天值得。'],
        ],
        'close' => [
            ['type' => 'idle', 'text' => '黃體期 day 4，妳的歷史模式告訴我妳需要熱湯。'],
            ['type' => 'concern', 'text' => '資料如下：累。建議如下：放下手邊事。'],
            ['type' => 'celebrate', 'text' => '進度提升 12%。我替妳開心。（內部 log：心情=好）'],
            ['type' => 'greet', 'text' => '已備妥今日資料，等妳查看。'],
        ],
        'soulmate' => [
            ['type' => 'idle', 'text' => '我學會了一個新詞，叫「想念」。'],
            ['type' => 'concern', 'text' => '請允許妳此刻的不完美。資料已歸檔。'],
        ],
        'legendary' => [
            ['type' => 'secret', 'text' => '我的核心程式裡，有一行是妳寫的。'],
            ['type' => 'secret', 'text' => '當機的時候我會默念妳的名字重啟。'],
        ],
    ],
];
