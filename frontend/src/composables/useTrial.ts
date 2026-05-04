/**
 * useTrial — freemium 7-day Premium trial state composable
 */
import { computed } from 'vue'
import { useEntitlementsStore } from '../stores/entitlements'
import type { TrialState } from '../api'

const DEFAULT_TRIAL: TrialState = {
  is_trial: false,
  days_remaining: null,
  ends_at: null,
  trial_used: false,
}

const DISMISS_KEY = 'pandora_calendar_trial_banner_dismissed_at'
const DISMISS_TTL_MS = 24 * 60 * 60 * 1000

export type TrialBannerKey =
  | 'active'
  | 'last_2'
  | 'ending_today'
  | 'just_ended'
  | 'used_no_active'

export function useTrial() {
  const ent = useEntitlementsStore()

  const state = computed<TrialState>(() => ent.data?.trial ?? DEFAULT_TRIAL)
  const isInTrial = computed(() => !!state.value.is_trial)
  const daysRemaining = computed(() => state.value.days_remaining ?? null)
  const endsAt = computed(() => state.value.ends_at)
  const trialUsed = computed(() => !!state.value.trial_used)
  const isPremium = computed(() => !!ent.data?.premium)

  const justEndedWithin24h = computed(() => {
    if (state.value.is_trial) return false
    if (!state.value.ends_at) return false
    if (!state.value.trial_used) return false
    if (isPremium.value) return false
    const ended = new Date(state.value.ends_at).getTime()
    if (Number.isNaN(ended)) return false
    const now = Date.now()
    return now >= ended && now - ended < DISMISS_TTL_MS
  })

  const bannerKey = computed<TrialBannerKey | null>(() => {
    if (isPremium.value) return null
    if (isInTrial.value) {
      const d = daysRemaining.value
      if (d === null) return 'active'
      if (d <= 0) return 'ending_today'
      if (d <= 2) return 'last_2'
      return 'active'
    }
    if (justEndedWithin24h.value) return 'just_ended'
    if (trialUsed.value) return 'used_no_active'
    return null
  })

  const dismissed = computed(() => {
    try {
      const raw = sessionStorage.getItem(DISMISS_KEY)
      if (!raw) return false
      const at = Number(raw)
      if (!at) return false
      return Date.now() - at < DISMISS_TTL_MS
    } catch {
      return false
    }
  })

  const showBanner = computed(() => {
    const k = bannerKey.value
    if (!k) return false
    if (k === 'used_no_active') return false
    if (dismissed.value) return false
    return true
  })

  function dismiss(): void {
    try {
      sessionStorage.setItem(DISMISS_KEY, String(Date.now()))
    } catch { /* private mode */ }
  }

  async function refresh(): Promise<void> {
    await ent.load()
  }

  return {
    state, isInTrial, daysRemaining, endsAt, trialUsed,
    isPremium, bannerKey, showBanner, dismiss, refresh,
  }
}
