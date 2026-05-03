/**
 * English (inclusive tone) — gender-neutral / non-binary / trans-friendly.
 *
 * Notes:
 * - English "you" is already gender-neutral, so the inclusive English variant
 *   stays very close to en.ts. Differences:
 *   - "period" → "menstrual cycle" in some contexts (more clinical, less gendered framing)
 *   - "How your body feels today" stays — already neutral
 *   - "friend" stays — already neutral
 * - Same medical-claim guardrails as en.ts.
 */
import type { ToneDict } from './zh-TW'

export const enInclusive: ToneDict = {
  // pronouns
  pronoun: 'you',
  pronoun_possessive: 'your',
  pronoun_object: 'you',
  friend: 'friend',
  friends: 'friends',

  // cycle vocabulary (more clinical-neutral)
  period: 'menstrual cycle',
  period_short: 'cycle',
  cycle: 'cycle',
  cycle_partner: 'Pandora Calendar by your side',
  body_listen: 'Track more, know your body better',
  body_state: 'How your body feels today',

  // Dodo NPC voice
  dodo_greeting: 'Hey 💛',
  dodo_today_question: 'How are you feeling today?',
  dodo_say: 'Dodo says',
  dodo_thinking: 'Dodo is thinking...',
  dodo_chat_history: 'Your chats with Dodo',
  dodo_silent: 'Tap a mood once and Dodo will reply.',
  dodo_companion: 'Dodo is here with you',
  dodo_milestone_first_log: 'First log in. Dodo will remember this day 💛',
  dodo_milestone_streak_7: '7-day streak. You are showing up ✨',
  dodo_milestone_first_cycle: 'First full cycle logged. Dodo learns your rhythm now.',

  // Navigation
  nav_calendar: 'Calendar',
  nav_log: 'Log',
  nav_dodo: 'Dodo',
  nav_me: 'Me',

  // Common buttons
  btn_save: 'Save',
  btn_cancel: 'Cancel',
  btn_next: 'Next',
  btn_back: 'Back',
  btn_done: 'Done',
  btn_done_today: 'Done for today',
  btn_retry: 'Try again',
  btn_confirm: 'Confirm',
  btn_delete: 'Delete',
  btn_edit: 'Edit',

  // Empty / error states
  empty_no_data: 'Nothing here yet',
  empty_first_record: 'Log your first entry. Dodo will start showing up.',
  empty_dodo_quiet: 'Dodo is quiet today. Try logging a mood.',
  loading_default: 'Loading...',
  error_network: 'Network is a bit shaky. Try again in a moment?',
  error_unauth: 'Please sign in again.',
  error_premium_required: 'This is a Premium feature. Unlock to go deeper with Dodo.',
  error_rate_limit: 'A bit too fast. Take a breath and try again.',
  error_generic: 'Something went wrong.',

  // Onboarding
  onboarding_title: 'Dodo wants to know you a little better',
  onboarding_step1_title: 'When did your last cycle start?',
  onboarding_step1_question: 'Help Dodo find your rhythm 💛',
  onboarding_step1_unsure: 'Not sure? That is okay.',
  onboarding_step2_title: 'How long is your cycle usually?',
  onboarding_step2_help: 'Default is 28 days. You can change this anytime.',
  onboarding_step3_title: 'What do you want to track?',

  // Log
  log_today_mood: "Today's mood",
  log_symptoms: 'How your body feels',
  log_bbt: 'Basal body temperature',
  log_partner_visible: 'Visible to partner',
  log_note_optional: 'Want to add a note? (optional)',

  // Paywall
  paywall_unlock: 'Unlock full Dodo',
  paywall_per_month: 'NT$99 / month',
  paywall_per_year: 'NT$899 / year',
  paywall_restore: 'Restore purchase',
  paywall_cancel_anytime: 'Cancel anytime',

  // Login / privacy
  login_subtitle: 'Your cycle. Logged with Dodo.',
  privacy_yours: 'Your data belongs to you',
  privacy_blurb: 'No ads. No data selling. Delete your records anytime.',
  privacy_blurb_long: 'No ads. No data selling. Your cycle records are only used to support you, never as ad material.',
  privacy_no_ads: 'No ads',
  privacy_no_sell: 'No data selling',
  privacy_no_track: 'No tracking',

  paywall_heading: 'Go a little deeper with Dodo',
  paywall_subtitle: 'Unlock full pattern analysis, biphasic BBT readings, daily insights, and partner sharing.',

  // Calendar header / countdown
  countdown_label_late: 'Cycle is late',
  countdown_label_today: 'Cycle may start today',
  countdown_label_close: 'Cycle is close',
  countdown_label_normal: 'Until next cycle',

  // Profile sections
  profile_greeting_default: 'friend',
  profile_section_pet: 'Your pet',
  profile_section_subscription: 'Subscription',
  profile_section_security: 'Privacy and security',
  profile_section_personalize: 'Personalize',
  profile_section_help: 'Need help',
  profile_section_about: 'About Pandora Calendar',
  profile_locale_label: 'Language / 語言',
  profile_locale_help: 'English is early access. Some strings may still show in Chinese.',
  setting_personalize: 'Personalize',
  setting_inclusive_label: 'Use gender-neutral tone',
  setting_inclusive_help: 'For non-binary, trans, or anyone who prefers neutral language. Dodo will switch tone.',

  // Subscription state
  subscription_active: 'Active',
  subscription_paused: 'Paused',
  subscription_canceled: 'Canceled',
  subscription_next_billing: 'Next billing',
  subscription_free: 'Free plan',
}
