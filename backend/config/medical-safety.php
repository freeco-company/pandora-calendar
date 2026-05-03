<?php

/**
 * 醫療安全決策樹（潘朵拉月曆）
 *
 * 用途：朋友記錄到異常路徑訊號時，匹配對應 rule，回傳就醫建議文案。
 *
 * Schema：
 *   key (str): 觸發類別代號
 *   rules (array)：
 *     - condition (str): 程式判斷條件（由 service 解析）
 *     - action (str): 建議動作標籤
 *     - urgency (str): low / medium / high / urgent
 *     - message (str): 顯示給朋友的朵朵文案（已過 sanitizer）
 *     - suggest_test (bool, optional): 是否提示驗孕 / 自我檢測
 *     - find_doctor_url (str, optional): 衛福部就醫資源連結 placeholder
 *
 * 撰寫紅線：
 *   - 不寫療效詞（治療 / 緩解 / 改善 / 修復 / 痊癒 / 排毒 / 調理 ...）
 *   - 不替醫師下診斷，文案統一引導「考慮諮詢婦產科」
 *   - 用「妳 / 朋友」，禁「您 / 會員 / 用戶」
 *   - find_doctor_url 為 placeholder，user 之後改成實際資源頁
 */

return [

    // ==========================================
    // 經期延遲
    // ==========================================
    'late_period' => [
        [
            'condition' => 'days_late >= 3 && days_late < 7',
            'action'    => '繼續觀察',
            'urgency'   => 'low',
            'message'   => '晚 3-6 天還在常見浮動範圍。最近壓力、睡眠、體重、旅行有變化嗎？朵朵幫妳記著，再等等看。',
        ],
        [
            'condition'    => 'days_late >= 7 && days_late < 14 && sexually_active == true',
            'action'       => '建議驗孕',
            'urgency'      => 'medium',
            'message'      => '晚一週了，如果這段期間有性行為，朵朵建議用晨起第一泡尿驗孕試紙看一次。不論結果是什麼，朵朵都在這裡。',
            'suggest_test' => true,
        ],
        [
            'condition' => 'days_late >= 7 && days_late < 14 && sexually_active == false',
            'action'    => '繼續觀察 + 紀錄壓力 / 睡眠',
            'urgency'   => 'medium',
            'message'   => '晚一週了。最近的壓力、睡眠、運動量有沒有變化？朵朵建議這幾天記下來，再給身體一週時間。',
        ],
        [
            'condition'       => 'days_late >= 14 && days_late < 30',
            'action'          => '考慮諮詢婦產科',
            'urgency'         => 'high',
            'message'         => '晚到兩週以上不該自己撐。朵朵建議找婦產科聊聊，帶上最近的紀錄會讓對話更有方向。',
            'find_doctor_url' => 'https://www.tw.gov.tw/health/find-obgyn',
        ],
        [
            'condition'       => 'days_late >= 60',
            'action'          => '盡快諮詢婦產科',
            'urgency'         => 'urgent',
            'message'         => '兩個月沒來，建議盡快找婦產科做檢查。朵朵把最近的紀錄都備好給妳，可以直接帶過去。',
            'find_doctor_url' => 'https://www.tw.gov.tw/health/find-obgyn',
        ],
    ],

    // ==========================================
    // 經血量過多
    // ==========================================
    'heavy_bleeding' => [
        [
            'condition'       => 'pads_per_hour >= 1 && duration_hours >= 2',
            'action'          => '考慮就醫評估',
            'urgency'         => 'high',
            'message'         => '一兩個小時就要換一片是量比較多的訊號。朵朵建議找婦產科聊聊，他們可以幫妳看看是怎麼回事。',
            'find_doctor_url' => 'https://www.tw.gov.tw/health/find-obgyn',
        ],
        [
            'condition' => 'large_clot_count >= 2 && period_day <= 3',
            'action'    => '紀錄並考慮諮詢婦產科',
            'urgency'   => 'medium',
            'message'   => '反覆出現超過硬幣大小的血塊，朵朵建議記下來，下次回診讓醫師看。',
        ],
        [
            'condition'       => 'period_duration_days > 7',
            'action'          => '考慮諮詢婦產科',
            'urgency'         => 'medium',
            'message'         => '經期超過 7 天還沒結束，連續發生 2-3 個週期就值得跟婦產科聊聊。',
            'find_doctor_url' => 'https://www.tw.gov.tw/health/find-obgyn',
        ],
        [
            'condition' => 'soak_overnight_pad == true && period_day <= 4',
            'action'    => '紀錄量大模式',
            'urgency'   => 'medium',
            'message'   => '夜用棉一片整夜外漏屬於量大訊號。朵朵幫妳記著，連續 2-3 個週期都這樣可以找婦產科聊聊。',
        ],
    ],

    // ==========================================
    // 嚴重經痛
    // ==========================================
    'severe_pain' => [
        [
            'condition'       => 'pain_level >= 8',
            'action'          => '考慮諮詢婦產科',
            'urgency'         => 'high',
            'message'         => '痛到這個程度不是「忍耐力的問題」。朵朵建議找婦產科聊聊，止痛藥壓不住的痛值得被認真看待。',
            'find_doctor_url' => 'https://www.tw.gov.tw/health/find-obgyn',
        ],
        [
            'condition'       => 'pain_level >= 6 && painkiller_no_effect == true',
            'action'          => '考慮諮詢婦產科',
            'urgency'         => 'high',
            'message'         => '吃了止痛藥還壓不住，朵朵建議帶著紀錄找婦產科聊聊，他們可以幫妳分辨原因。',
            'find_doctor_url' => 'https://www.tw.gov.tw/health/find-obgyn',
        ],
        [
            'condition'       => 'pain_with_vomit == true || pain_with_faint == true',
            'action'          => '盡快就醫評估',
            'urgency'         => 'urgent',
            'message'         => '痛到嘔吐或暈眩請盡快就醫，這已經超出一般經痛範圍。朵朵會等妳回來。',
            'find_doctor_url' => 'https://www.tw.gov.tw/health/find-obgyn',
        ],
    ],

    // ==========================================
    // 週期不規律
    // ==========================================
    'irregular_pattern' => [
        [
            'condition'       => 'cycle_count_in_year < 9',
            'action'          => '考慮諮詢婦產科',
            'urgency'         => 'medium',
            'message'         => '一年來經少於 9 次屬於稀發月經，建議找婦產科做基本內分泌檢查。朵朵把妳的紀錄整理好。',
            'find_doctor_url' => 'https://www.tw.gov.tw/health/find-obgyn',
        ],
        [
            'condition'       => 'cycle_length_variance >= 14 && observed_cycles >= 3',
            'action'          => '紀錄並諮詢婦產科',
            'urgency'         => 'medium',
            'message'         => '連續 3 個週期長度差距超過兩週，朵朵建議帶著紀錄找婦產科聊聊，他們可以做更完整的評估。',
            'find_doctor_url' => 'https://www.tw.gov.tw/health/find-obgyn',
        ],
        [
            'condition' => 'cycle_length > 35 && observed_cycles >= 2',
            'action'    => '繼續觀察 + 諮詢',
            'urgency'   => 'medium',
            'message'   => '週期超過 35 天可能是排卵不規律的訊號。朵朵建議多記 1-2 個週期，必要時帶紀錄找婦產科聊聊。',
        ],
    ],

    // ==========================================
    // BBT 連續 3 個週期沒雙相（備孕族群尤需關注）
    // ==========================================
    'bbt_no_shift_3_cycles' => [
        [
            'condition'       => 'no_biphasic_curve_cycles >= 3',
            'action'          => '考慮諮詢婦產科',
            'urgency'         => 'medium',
            'message'         => '連續 3 個週期沒看到體溫雙相曲線，朵朵建議帶著紀錄找婦產科聊聊。對備孕中的朋友更建議盡早處理。',
            'find_doctor_url' => 'https://www.tw.gov.tw/health/find-obgyn',
        ],
        [
            'condition'       => 'luteal_phase_length < 10 && observed_cycles >= 2',
            'action'          => '紀錄並諮詢',
            'urgency'         => 'medium',
            'message'         => '經前期（黃體期）長度持續低於 10 天，可能是黃體功能訊號，特別在備孕期間值得跟婦產科聊聊。',
            'find_doctor_url' => 'https://www.tw.gov.tw/health/find-obgyn',
        ],
        [
            'condition'       => 'no_biphasic_curve_cycles >= 6',
            'action'          => '盡快諮詢',
            'urgency'         => 'high',
            'message'         => '半年都沒看到雙相，朵朵建議盡快找婦產科做完整評估。長期不排卵需要被認真看待。',
            'find_doctor_url' => 'https://www.tw.gov.tw/health/find-obgyn',
        ],
    ],

    // ==========================================
    // 經期間出血
    // ==========================================
    'spotting_between_periods' => [
        [
            'condition' => 'spotting_around_ovulation == true && episode_count <= 2',
            'action'    => '繼續觀察',
            'urgency'   => 'low',
            'message'   => '排卵期偶爾出現少量點狀分泌物是常見的，朵朵幫妳記著。連續多個週期反覆出現再考慮回診。',
        ],
        [
            'condition'       => 'spotting_after_intercourse == true',
            'action'          => '考慮諮詢婦產科',
            'urgency'         => 'medium',
            'message'         => '性行為後出血值得被檢查，朵朵建議找婦產科做子宮頸抹片和超音波，這個檢查不複雜。',
            'find_doctor_url' => 'https://www.tw.gov.tw/health/find-obgyn',
        ],
        [
            'condition'       => 'irregular_spotting_episodes >= 3 && in_cycles <= 2',
            'action'          => '考慮諮詢婦產科',
            'urgency'         => 'medium',
            'message'         => '兩個週期內反覆 3 次以上經期間出血，朵朵建議帶紀錄找婦產科聊聊。',
            'find_doctor_url' => 'https://www.tw.gov.tw/health/find-obgyn',
        ],
        [
            'condition'       => 'postmenopause_bleeding == true',
            'action'          => '盡快諮詢婦產科',
            'urgency'         => 'high',
            'message'         => '停經之後出血需要被認真評估，朵朵建議盡快找婦產科。不論結果如何，朵朵都陪妳。',
            'find_doctor_url' => 'https://www.tw.gov.tw/health/find-obgyn',
        ],
    ],

];
