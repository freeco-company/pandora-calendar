/**
 * tour-steps.ts — pure data, every coachmark step the app can show.
 *
 * Why a separate file: keeps useOnboardingTour.ts thin (state only) and lets
 * locale dicts reference step keys 1:1. Steps reference data-tour="..." anchors
 * placed in views via Edit; missing target = step is skipped silently.
 */
export type TourStep = {
  /** CSS selector — typically `[data-tour="key"]`; if not found, step is skipped */
  target: string
  /** i18n key for popup title */
  titleKey: string
  /** i18n key for popup body */
  bodyKey: string
  /** Spotlight position relative to target */
  placement?: 'top' | 'bottom' | 'left' | 'right' | 'center'
  /** If true, no spotlight cutout — full-screen dodo intro/outro */
  fullscreen?: boolean
}

export const TOUR_KEYS = [
  'first_calendar',
  'first_journey',
  'first_skill_path',
  'first_body_dex',
  'first_stories',
  'first_rank',
  'first_daily_action',
] as const

export type TourKey = (typeof TOUR_KEYS)[number]

export const TOUR_STEPS: Record<TourKey, TourStep[]> = {
  first_calendar: [
    {
      target: '[data-tour="calendar-countdown"]',
      titleKey: 'tour_calendar_step1_title',
      bodyKey: 'tour_calendar_step1_body',
      placement: 'bottom',
    },
    {
      target: '[data-tour="calendar-pet"]',
      titleKey: 'tour_calendar_step2_title',
      bodyKey: 'tour_calendar_step2_body',
      placement: 'bottom',
    },
    {
      target: '[data-tour="gamification-strip"]',
      titleKey: 'tour_calendar_step3_title',
      bodyKey: 'tour_calendar_step3_body',
      placement: 'bottom',
    },
    {
      target: '[data-tour="protocol-banner"]',
      titleKey: 'tour_calendar_step4_title',
      bodyKey: 'tour_calendar_step4_body',
      placement: 'bottom',
    },
    {
      target: '[data-tour="today-action"]',
      titleKey: 'tour_calendar_step5_title',
      bodyKey: 'tour_calendar_step5_body',
      placement: 'top',
    },
    {
      target: '[data-tour="calendar-grid"]',
      titleKey: 'tour_calendar_step6_title',
      bodyKey: 'tour_calendar_step6_body',
      placement: 'top',
    },
  ],
  first_journey: [
    {
      target: '[data-tour="pet-character"]',
      titleKey: 'tour_journey_step1_title',
      bodyKey: 'tour_journey_step1_body',
      placement: 'bottom',
    },
    {
      target: '[data-tour="bond-meter"]',
      titleKey: 'tour_journey_step2_title',
      bodyKey: 'tour_journey_step2_body',
      placement: 'bottom',
    },
    {
      target: '[data-tour="quick-links"]',
      titleKey: 'tour_journey_step3_title',
      bodyKey: 'tour_journey_step3_body',
      placement: 'bottom',
    },
    {
      target: '[data-tour="achievements-section"]',
      titleKey: 'tour_journey_step4_title',
      bodyKey: 'tour_journey_step4_body',
      placement: 'top',
    },
    {
      target: '[data-tour="outfits-section"]',
      titleKey: 'tour_journey_step5_title',
      bodyKey: 'tour_journey_step5_body',
      placement: 'top',
    },
  ],
  first_skill_path: [
    {
      target: '[data-tour="skill-path-intro"]',
      titleKey: 'tour_skill_path_step1_title',
      bodyKey: 'tour_skill_path_step1_body',
      placement: 'bottom',
    },
    {
      target: '[data-tour="skill-path-pick"]',
      titleKey: 'tour_skill_path_step2_title',
      bodyKey: 'tour_skill_path_step2_body',
      placement: 'bottom',
    },
    {
      target: '[data-tour="skill-path-progress"]',
      titleKey: 'tour_skill_path_step3_title',
      bodyKey: 'tour_skill_path_step3_body',
      placement: 'top',
    },
  ],
  first_body_dex: [
    {
      target: '[data-tour="body-dex-intro"]',
      titleKey: 'tour_body_dex_step1_title',
      bodyKey: 'tour_body_dex_step1_body',
      placement: 'bottom',
    },
    {
      target: '[data-tour="body-dex-grid"]',
      titleKey: 'tour_body_dex_step2_title',
      bodyKey: 'tour_body_dex_step2_body',
      placement: 'top',
    },
    {
      target: '[data-tour="body-dex-locked"]',
      titleKey: 'tour_body_dex_step3_title',
      bodyKey: 'tour_body_dex_step3_body',
      placement: 'top',
    },
  ],
  first_stories: [
    {
      target: '[data-tour="stories-intro"]',
      titleKey: 'tour_story_step1_title',
      bodyKey: 'tour_story_step1_body',
      placement: 'bottom',
    },
    {
      target: '[data-tour="stories-list"]',
      titleKey: 'tour_story_step2_title',
      bodyKey: 'tour_story_step2_body',
      placement: 'top',
    },
    {
      target: '[data-tour="stories-locked"]',
      titleKey: 'tour_story_step3_title',
      bodyKey: 'tour_story_step3_body',
      placement: 'top',
    },
  ],
  first_rank: [
    {
      target: '[data-tour="rank-current"]',
      titleKey: 'tour_rank_step1_title',
      bodyKey: 'tour_rank_step1_body',
      placement: 'bottom',
    },
    {
      target: '[data-tour="rank-tiers"]',
      titleKey: 'tour_rank_step2_title',
      bodyKey: 'tour_rank_step2_body',
      placement: 'top',
    },
  ],
  first_daily_action: [
    {
      target: '[data-tour="daily-action-card"]',
      titleKey: 'tour_daily_action_step1_title',
      bodyKey: 'tour_daily_action_step1_body',
      placement: 'bottom',
    },
    {
      target: '[data-tour="daily-action-feedback"]',
      titleKey: 'tour_daily_action_step2_title',
      bodyKey: 'tour_daily_action_step2_body',
      placement: 'top',
    },
    {
      target: '[data-tour="daily-action-history"]',
      titleKey: 'tour_daily_action_step3_title',
      bodyKey: 'tour_daily_action_step3_body',
      placement: 'top',
    },
  ],
}
