<?php

/*
|--------------------------------------------------------------------------
| 婕樂纖商品連結文案（P5）
|--------------------------------------------------------------------------
|
| 紅線：每筆 message **必過** Pandora\Shared\Compliance\LegalContentSanitizer。
| 禁療效詞（改善 / 緩解 / 治療 / 排毒 / 調理 / 取代正餐 / 低 GI / 高纖維 / 燃脂 ...）。
| 用「陪伴 / 選擇 / 補充 / 點心 / 不少朋友的選擇」這類模糊語。
|
| 結構：
|   trigger  = 觸發條件 key（在 MotherEcommerceConnector 裡 hardcoded match）
|   threshold = 30 天內最少要出現幾次該 symptom tag 才推
|   product_slug = 對應婕樂纖商品 slug（必用註冊全稱）
|   message = 朵朵語氣文案（已過合規）
|   mother_url = 母艦商品頁深連結
|
*/

return [
    [
        'trigger' => 'bloating',
        'threshold' => 3,
        'product_slug' => 'fp-burst-fiber',
        'product_name' => '婕樂纖爆纖錠',
        'message' => '妳這個月腹脹有點頻繁，婕樂纖爆纖錠是不少朋友的選擇。',
        'mother_url' => 'https://pandora.js-store.com.tw/products/fp-burst-fiber',
    ],
    [
        'trigger' => 'gut_discomfort',
        'threshold' => 2,
        'product_slug' => 'fp-probiotics',
        'product_name' => '婕樂纖高機能益生菌',
        'message' => '經期腸胃跟妳鬧脾氣的時候，婕樂纖高機能益生菌是不少朋友會準備的補充。',
        'mother_url' => 'https://pandora.js-store.com.tw/products/fp-probiotics',
    ],
    [
        'trigger' => 'acne',
        'threshold' => 2,
        'product_slug' => 'fp-water-light',
        'product_name' => '婕樂纖水光錠',
        'message' => '經前一週皮膚比較乾，婕樂纖水光錠是常被選的補充。',
        'mother_url' => 'https://pandora.js-store.com.tw/products/fp-water-light',
    ],
    [
        'trigger' => 'craving_sweet',
        'threshold' => 3,
        'product_slug' => 'fp-thick-milk-tea',
        'product_name' => '婕樂纖厚焙奶茶',
        'message' => '想喝甜的時候，婕樂纖厚焙奶茶是不少朋友的點心選擇。',
        'mother_url' => 'https://pandora.js-store.com.tw/products/fp-thick-milk-tea',
    ],
];
