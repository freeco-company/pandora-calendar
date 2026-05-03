<?php

/**
 * Symptom tag canonical 對照（2026-05-03 擴充至 22 個）
 *
 * 結構維持既有 4 類分組（physical / emotional / sexual / fertility），
 * 每個 tag：
 *   - key   : DB / API 用 snake_case（既有 key 不要動，避免 migration）
 *   - label : 中文（朵朵語氣，禁療效詞：改善 / 緩解 / 治療 / 排毒 / 調理 / 抑菌 / 消炎 ...）
 *   - emoji : 1 個
 *
 * P0 → P0+ 增補（備孕 / 避孕族群必需）：
 *   sexual    : sex_protected / sex_unprotected
 *   fertility : discharge_dry / discharge_creamy / discharge_watery
 *               pregnancy_test_negative / pregnancy_test_positive
 *               contraception_pill / contraception_condom
 *
 * 既有 emotional 群裡 craving_sweet / craving_salty / insomnia 雖然語意上偏
 * physical/behavioral，為避免 P0 已寫入資料的分組變動，**保留現位**。未來
 * 若要重分組，需走 migration + UI 升級流程。
 */

return [
    'physical' => [
        ['key' => 'cramp',          'label' => '小腹悶痛',     'emoji' => '🤕'],
        ['key' => 'headache',       'label' => '頭痛',         'emoji' => '😣'],
        ['key' => 'fatigue',        'label' => '很累',         'emoji' => '😴'],
        ['key' => 'bloating',       'label' => '腹脹',         'emoji' => '🎈'],
        ['key' => 'breast_tender',  'label' => '胸部脹脹的',   'emoji' => '💗'],
        ['key' => 'acne',           'label' => '冒痘',         'emoji' => '🔴'],
        ['key' => 'back_pain',      'label' => '腰痠',         'emoji' => '🦴'],
        ['key' => 'nausea',         'label' => '反胃',         'emoji' => '🤢'],
        ['key' => 'dizziness',      'label' => '頭暈',         'emoji' => '💫'],
    ],
    'emotional' => [
        ['key' => 'mood_swing',     'label' => '情緒起伏',     'emoji' => '🎢'],
        ['key' => 'craving_sweet',  'label' => '想吃甜',       'emoji' => '🍬'],
        ['key' => 'craving_salty',  'label' => '想吃鹹',       'emoji' => '🍟'],
        ['key' => 'insomnia',       'label' => '睡不好',       'emoji' => '🌙'],
        ['key' => 'anxious',        'label' => '焦慮',         'emoji' => '😰'],
        ['key' => 'irritable',      'label' => '易怒',         'emoji' => '😤'],
        ['key' => 'low_mood',       'label' => '心情低低的',   'emoji' => '🌧️'],
    ],
    'sexual' => [
        ['key' => 'libido_high',         'label' => '性慾較高',         'emoji' => '✨'],
        ['key' => 'libido_low',          'label' => '性慾較低',         'emoji' => '💤'],
        ['key' => 'sex_protected',       'label' => '性行為（有避孕）', 'emoji' => '💗'],
        ['key' => 'sex_unprotected',     'label' => '性行為（無避孕）', 'emoji' => '💞'],
        ['key' => 'contraception_pill',  'label' => '今日服用避孕藥',   'emoji' => '💊'],
        ['key' => 'contraception_condom', 'label' => '保險套',           'emoji' => '🛡️'],
    ],
    'fertility' => [
        ['key' => 'ovulation_pain',         'label' => '排卵期悶痛',       'emoji' => '🌸'],
        ['key' => 'spotting',               'label' => '少量出血',         'emoji' => '🩸'],
        ['key' => 'bbt_high',               'label' => '基礎體溫偏高',     'emoji' => '🌡️'],
        ['key' => 'discharge_dry',          'label' => '分泌物：乾燥',     'emoji' => '🍂'],
        ['key' => 'discharge_creamy',       'label' => '分泌物：乳狀',     'emoji' => '🥛'],
        ['key' => 'discharge_egg_white',    'label' => '分泌物：蛋清狀',   'emoji' => '🥚'],
        ['key' => 'discharge_watery',       'label' => '分泌物：水狀',     'emoji' => '💧'],
        ['key' => 'pregnancy_test_negative', 'label' => '驗孕：未懷孕',     'emoji' => '🔎'],
        ['key' => 'pregnancy_test_positive', 'label' => '驗孕：陽性',       'emoji' => '🌟'],
    ],
];
