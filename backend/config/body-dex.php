<?php

/*
| Wave 13 — BodyDex 30 種症狀 catalog。
| 與 symptom-tags.php 對齊（symptom_key 同 symptom-tags 的 key）。
| 收集滿 30 解 legendary outfit `body_dex_master`（OutfitCatalog 補卡）。
*/

return [
    'total_target' => 30,

    'entries' => [
        // physical 9
        'cramp' => ['label' => '小腹悶痛', 'hint' => '經期常見的訊號', 'rarity' => 'common'],
        'headache' => ['label' => '頭痛', 'hint' => '黃體期 / 經前常出現', 'rarity' => 'common'],
        'fatigue' => ['label' => '很累', 'hint' => '身體在告訴妳要休息', 'rarity' => 'common'],
        'bloating' => ['label' => '腹脹', 'hint' => '黃體期水分滯留', 'rarity' => 'common'],
        'breast_tender' => ['label' => '胸部脹脹的', 'hint' => '黃體期賀爾蒙波動', 'rarity' => 'common'],
        'acne' => ['label' => '冒痘', 'hint' => '皮脂腺活躍時期', 'rarity' => 'common'],
        'back_pain' => ['label' => '腰痠', 'hint' => '經期前後常見', 'rarity' => 'common'],
        'nausea' => ['label' => '反胃', 'hint' => '經期或荷爾蒙波動', 'rarity' => 'rare'],

        // emotional 7
        'mood_swing' => ['label' => '情緒起伏', 'hint' => '黃體期常見', 'rarity' => 'common'],
        'craving_sweet' => ['label' => '想吃甜', 'hint' => '經前的小訊號', 'rarity' => 'common'],
        'craving_salty' => ['label' => '想吃鹹', 'hint' => '經前水分需求', 'rarity' => 'common'],
        'insomnia' => ['label' => '睡不好', 'hint' => '黃體期常見', 'rarity' => 'common'],
        'anxious' => ['label' => '焦慮', 'hint' => '身體在提醒妳', 'rarity' => 'rare'],
        'irritable' => ['label' => '易怒', 'hint' => '經前特別敏感', 'rarity' => 'rare'],
        'low_mood' => ['label' => '心情低低的', 'hint' => '允許自己今天慢一點', 'rarity' => 'rare'],

        // sexual 6
        'libido_high' => ['label' => '性慾較高', 'hint' => '排卵期前後正常', 'rarity' => 'rare'],
        'libido_low' => ['label' => '性慾較低', 'hint' => '經期 / 黃體期常見', 'rarity' => 'rare'],
        'sex_protected' => ['label' => '性行為（有避孕）', 'hint' => '紀錄妳的選擇', 'rarity' => 'common'],
        'sex_unprotected' => ['label' => '性行為（無避孕）', 'hint' => '紀錄妳的選擇', 'rarity' => 'common'],
        'contraception_pill' => ['label' => '避孕藥', 'hint' => '紀錄服用節奏', 'rarity' => 'rare'],
        'contraception_condom' => ['label' => '保險套', 'hint' => '紀錄妳的選擇', 'rarity' => 'common'],

        // fertility 9
        'ovulation_pain' => ['label' => '排卵期悶痛', 'hint' => '排卵的小訊號', 'rarity' => 'rare'],
        'spotting' => ['label' => '少量出血', 'hint' => '若連續多天請就醫', 'rarity' => 'rare'],
        'bbt_high' => ['label' => '基礎體溫偏高', 'hint' => '黃體期常見', 'rarity' => 'rare'],
        'discharge_dry' => ['label' => '分泌物：乾燥', 'hint' => '濾泡期常見', 'rarity' => 'rare'],
        'discharge_creamy' => ['label' => '分泌物：乳狀', 'hint' => '濾泡期 / 黃體期', 'rarity' => 'rare'],
        'discharge_egg_white' => ['label' => '分泌物：蛋清狀', 'hint' => '排卵期訊號', 'rarity' => 'epic'],
        'discharge_watery' => ['label' => '分泌物：水狀', 'hint' => '接近排卵期', 'rarity' => 'rare'],
        'pregnancy_test_negative' => ['label' => '驗孕：未懷孕', 'hint' => '紀錄妳的紀錄', 'rarity' => 'rare'],
        'pregnancy_test_positive' => ['label' => '驗孕：陽性', 'hint' => '人生新章節', 'rarity' => 'legendary'],
    ],
];
