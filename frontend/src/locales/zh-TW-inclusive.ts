/**
 * Inclusive tone — 性別中性 / 跨性別 / 非二元友善版本
 *
 * 設計原則：
 * - 不用「妳」（女性化），改「你」
 * - 不用「朋友」隱含女性語境的詞，保留 neutral「朋友 / 夥伴」
 * - 「經期」改「月經週期」更醫療中性，避免「她的經期」式女性 framing
 * - 朵朵語氣保持暖、不更冷；只是語法上中性化
 * - 不寫「您」（過於正式、與集團 tone 衝突）
 */
import type { ToneDict } from './zh-TW'

export const zhTWInclusive: ToneDict = {
  // pronouns
  pronoun: '你',
  pronoun_possessive: '你的',
  pronoun_object: '你',
  friend: '夥伴',
  friends: '夥伴們',

  // cycle 相關詞彙（更醫療中性）
  period: '月經週期',
  period_short: '月經',
  cycle: '週期',
  cycle_partner: '陪伴你的週期記錄',
  body_listen: '越記越懂自己的身體',
  body_state: '今日身體狀態',

  // 朵朵 NPC 語氣（保持溫暖，但去掉女性化稱謂）
  dodo_greeting: '嗨 💛',
  dodo_today_question: '你今天感覺如何？',
  dodo_say: '朵朵說',
  dodo_thinking: '朵朵正在想...',
  dodo_chat_history: '朵朵跟你的對話',
  dodo_silent: '第一次點一下心情，朵朵就會回覆你。',
  dodo_companion: '朵朵會陪著你',

  // login / onboarding / paywall
  login_subtitle: '你的週期 · 朵朵陪你一起記',
  privacy_yours: '你的資料只屬於你',
  privacy_blurb: '我們不賣資料、不放廣告。你隨時可以刪除你的紀錄。',
  privacy_blurb_long: '我們不賣資料、不放廣告。你的週期紀錄只用來陪伴你，不會被當成廣告素材。',
  onboarding_title: '朵朵想多認識你一點',
  paywall_heading: '朵朵陪你更深一點',
  paywall_subtitle: '解鎖完整 PMS 分析、BBT 雙相判讀、每日衛教與伴侶分享。',

  // calendar header / countdown
  countdown_label_late: '月經已遲到',
  countdown_label_today: '月經可能今天到',
  countdown_label_close: '月經接近中',
  countdown_label_normal: '距離下次月經',

  // profile section titles
  profile_greeting_default: '夥伴',
  setting_personalize: '個人化',
  setting_inclusive_label: '使用性別中性語氣',
  setting_inclusive_help: '對非二元 / 跨性別 / 不喜歡女性化稱謂的夥伴 ♥ 朵朵會切換為中性語氣',
}
