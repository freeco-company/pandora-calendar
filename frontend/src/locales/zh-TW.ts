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

  // login / onboarding / paywall
  login_subtitle: '妳的週期 · 朵朵陪妳一起記',
  privacy_yours: '妳的資料只屬於妳',
  privacy_blurb: '我們不賣資料、不放廣告。妳隨時可以刪除妳的紀錄。',
  privacy_blurb_long: '我們不賣資料、不放廣告。妳的週期紀錄只用來陪伴妳，不會被當成廣告素材。',
  onboarding_title: '朵朵想多認識妳一點',
  paywall_heading: '朵朵陪妳更深一點',
  paywall_subtitle: '解鎖完整 PMS 分析、BBT 雙相判讀、每日衛教與伴侶分享。',

  // calendar header / countdown
  countdown_label_late: '經期已遲到',
  countdown_label_today: '經期可能今天到',
  countdown_label_close: '經期接近中',
  countdown_label_normal: '距離下次經期',

  // profile section titles
  profile_greeting_default: '朋友',
  setting_personalize: '個人化',
  setting_inclusive_label: '使用性別中性語氣',
  setting_inclusive_help: '對非二元 / 跨性別 / 不喜歡女性化稱謂的朋友 ♥ 朵朵會切換為中性語氣',
}
