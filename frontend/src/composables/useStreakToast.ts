import { ref } from 'vue'
import { api, getToken } from '../api'

/**
 * SPEC-cross-app-streak Phase 1.B (calendar) — daily login streak toast composable.
 *
 * 鏡像 pandora-meal Phase 1.A `streak-toast.js` 的 vanilla 邏輯，改成 Vue 3 composable。
 *
 * 用法（在 App.vue mount 時 call）：
 *   const { state, fetchToday } = useStreakToast()
 *   onMounted(() => { if (getToken()) fetchToday() })
 *
 * 由 StreakToast.vue 讀 `state` 顯示 toast：
 *   - `is_first_today=true` 普通日：3s 顯示 N 天計數
 *   - `is_milestone=true` 里程碑日（1/3/7/14/21/30/60/100）：5s + 朵朵 SVG + 導師文案
 *   - `is_milestone=true` + `unlocks` 有 outfit / xp_bonus：5s + reveal animation
 *   - 21 / 100 天：fullscreen overlay variant（tap dismiss）
 */

/**
 * Streak milestone unlock payload — mirrors backend
 * `StreakMilestoneRewardService::unlockForMilestone()` return shape.
 */
export interface StreakUnlocks {
  outfit_unlocked: string | null
  outfit_skipped: string | null
  cards_unlocked: Array<{ code: string; label: string }>
  xp_bonus: number
  total_xp_after: number | null
}

/**
 * Phase 5B — cross-App master streak overlay payload (mirrors py-service
 * `/internal/group-streak/{uuid}` response). Null when user is unbound
 * (no identity_uuid yet) or py-service is unavailable (fail-soft).
 */
export interface GroupStreakState {
  current_streak: number
  longest_streak: number
  last_login_date: string | null
  last_seen_app: string | null
  today_in_streak: boolean
}

export interface StreakState {
  current_streak: number
  longest_streak: number
  is_first_today: boolean
  is_milestone: boolean
  milestone_label: string | null
  today_date: string
  /** present when `is_milestone=true`; null on non-milestone days */
  unlocks: StreakUnlocks | null
  /** Phase 5B — null when fail-soft / unbound user */
  group: GroupStreakState | null
}

const state = ref<StreakState | null>(null)
const visible = ref(false)
let hideTimer: ReturnType<typeof setTimeout> | null = null

/**
 * Fullscreen overlay tiers — these days warrant a heavier celebration
 * (大里程碑 / habit-formed point / 100-day legend) so the toast escalates
 * to a tap-to-dismiss overlay. Other milestones use the normal slide-down.
 */
const FULLSCREEN_OVERLAY_TIERS: ReadonlyArray<number> = [21, 100]

export function isFullscreenMilestone(streak: number): boolean {
  return FULLSCREEN_OVERLAY_TIERS.includes(streak)
}

export function useStreakToast() {
  /**
   * Fetch /api/streak/today — middleware also attaches X-Streak header；
   * we read from response body since it already includes
   * is_first_today / is_milestone / unlocks.
   *
   * Fail-soft: streak fetch failure must not block App boot.
   */
  async function fetchToday(): Promise<void> {
    if (!getToken()) return
    try {
      const { data } = await api.get<StreakState>('/streak/today')
      state.value = data
      if (data.is_first_today) {
        // Longer dwell when there's a real reveal (outfit / xp bonus / fullscreen)
        const hasReveal =
          !!data.unlocks &&
          (data.unlocks.outfit_unlocked !== null ||
            data.unlocks.xp_bonus > 0 ||
            isFullscreenMilestone(data.current_streak))
        const dwellMs = data.is_milestone ? (hasReveal ? 5000 : 5000) : 3000
        showToast(dwellMs)
      }
    } catch (e) {
      // swallow — streak is non-critical
    }
  }

  function showToast(durationMs: number): void {
    visible.value = true
    if (hideTimer) clearTimeout(hideTimer)
    hideTimer = setTimeout(() => {
      visible.value = false
    }, durationMs)
  }

  function dismiss(): void {
    visible.value = false
    if (hideTimer) {
      clearTimeout(hideTimer)
      hideTimer = null
    }
  }

  return {
    state,
    visible,
    fetchToday,
    dismiss,
    isFullscreenMilestone,
  }
}
