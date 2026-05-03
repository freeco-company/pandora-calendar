/**
 * Default tone — 沿用集團 tone-of-voice（女性化、暖、近距離朋友語氣）
 * 對照 docs/group-naming-and-voice.md：妳 / 朋友 / 夥伴 / 朵朵口吻
 *
 * 這是「預設語氣」字典；非預設啟用 inclusive mode 時走 zh-TW-inclusive.ts。
 */
export type ToneDict = Record<string, string>

export const zhTW: ToneDict = {
  // pronouns
  pronoun: '妳',
  pronoun_possessive: '妳的',
  pronoun_object: '妳',
  friend: '朋友',
  friends: '朋友們',

  // cycle 相關詞彙
  period: '經期',
  period_short: '經期',
  cycle: '週期',
  cycle_partner: '陪伴妳的潘朵拉月曆',
  body_listen: '越記越懂自己',
  body_state: '今日身體狀態',

  // 朵朵 NPC 語氣
  dodo_greeting: '嗨朋友 💛',
  dodo_today_question: '妳今天感覺如何？',
  dodo_say: '朵朵說',
  dodo_thinking: '朵朵正在想...',
  dodo_chat_history: '朵朵跟妳的對話',
  dodo_silent: '第一次點一下心情，朵朵就會回覆妳。',
  dodo_companion: '朵朵會陪著妳',
  dodo_milestone_first_log: '第一筆紀錄，朵朵會記住這天 💛',
  dodo_milestone_streak_7: '連續 7 天記錄，妳很穩定 ✨',
  dodo_milestone_first_cycle: '第一個完整週期，朵朵更懂妳的節奏了',

  // 導航
  nav_calendar: '月曆',
  nav_log: '記錄',
  nav_dodo: '朵朵',
  nav_me: '我的',

  // 共用按鈕
  btn_save: '儲存',
  btn_cancel: '取消',
  btn_next: '下一步',
  btn_back: '返回',
  btn_done: '完成',
  btn_done_today: '今天先到這',
  btn_retry: '重試',
  btn_confirm: '確認',
  btn_delete: '刪除',
  btn_edit: '編輯',

  // empty / error 三態
  empty_no_data: '目前還沒有資料',
  empty_first_record: '記下第一筆，朵朵就會開始陪妳',
  empty_dodo_quiet: '朵朵今天很安靜，記一筆心情吧',
  loading_default: '載入中...',
  error_network: '網路有點不穩，等等再試一次？',
  error_unauth: '請重新登入一下',
  error_premium_required: '這是 Premium 內容，解鎖後朵朵陪妳更深一點',
  error_rate_limit: '太快囉～休息一下再來',
  error_generic: '發生了一點小狀況',

  // onboarding
  onboarding_title: '朵朵想多認識妳一點',
  onboarding_step1_title: '上一次經期大約什麼時候？',
  onboarding_step1_question: '幫朵朵抓一下節奏 💛',
  onboarding_step1_unsure: '不太記得也沒關係',
  onboarding_step2_title: '週期通常多長？',
  onboarding_step2_help: '預設 28 天，妳可以之後再調整',
  onboarding_step3_title: '想記錄哪些？',

  // log
  log_today_mood: '今天的心情',
  log_symptoms: '身體感受',
  log_bbt: '基礎體溫',
  log_partner_visible: '伴侶可見',
  log_note_optional: '想多寫一點？（選填）',

  // paywall
  paywall_unlock: '解鎖完整朵朵',
  paywall_per_month: '每月 NT$99',
  paywall_per_year: '每年 NT$899',
  paywall_restore: '還原購買',
  paywall_cancel_anytime: '隨時可以取消',

  // login / privacy
  login_subtitle: '妳的週期 · 朵朵陪妳一起記',
  privacy_yours: '妳的資料只屬於妳',
  privacy_blurb: '我們不賣資料、不放廣告。妳隨時可以刪除妳的紀錄。',
  privacy_blurb_long: '我們不賣資料、不放廣告。妳的週期紀錄只用來陪伴妳，不會被當成廣告素材。',
  privacy_no_ads: '不放廣告',
  privacy_no_sell: '不賣資料',
  privacy_no_track: '不追蹤妳',

  paywall_heading: '朵朵陪妳更深一點',
  paywall_subtitle: '解鎖完整 PMS 分析、BBT 雙相判讀、每日衛教與伴侶分享。',

  // calendar header / countdown
  countdown_label_late: '經期已遲到',
  countdown_label_today: '經期可能今天到',
  countdown_label_close: '經期接近中',
  countdown_label_normal: '距離下次經期',

  // profile sections
  profile_greeting_default: '朋友',
  profile_section_pet: '我的寵物',
  profile_section_subscription: '訂閱',
  profile_section_security: '安全與隱私',
  profile_section_personalize: '個人化',
  profile_section_help: '需要幫忙',
  profile_section_about: '關於潘朵拉月曆',
  profile_locale_label: '語言 / Language',
  profile_locale_help: '目前 English 為早期版本，部分文案仍會顯示中文。',
  setting_personalize: '個人化',
  setting_inclusive_label: '使用性別中性語氣',
  setting_inclusive_help: '對非二元 / 跨性別 / 不喜歡女性化稱謂的朋友 ♥ 朵朵會切換為中性語氣',

  // subscription state
  subscription_active: '訂閱中',
  subscription_paused: '已暫停',
  subscription_canceled: '已取消',
  subscription_next_billing: '下次續訂',
  subscription_free: '免費版',
}
