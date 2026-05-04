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
 * 由 StreakToast.vue 讀 `state` 顯示 toast（`is_first_today=true` 時顯示 N 天計數，
 * `is_milestone=true` 時延長秒數 + 朵朵 SVG + 導師文案）。
 */

export interface StreakState {
  current_streak: number
  longest_streak: number
  is_first_today: boolean
  is_milestone: boolean
  milestone_label: string | null
  today_date: string
}

const state = ref<StreakState | null>(null)
const visible = ref(false)
let hideTimer: ReturnType<typeof setTimeout> | null = null

export function useStreakToast() {
  /**
   * Fetch /api/streak/today — middleware 也會 attach X-Streak header；
   * 我們直接用 response body（已經包含 is_first_today / is_milestone 判斷）。
   *
   * Fail-soft：streak fetch 失敗不影響 App boot。
   */
  async function fetchToday(): Promise<void> {
    if (!getToken()) return
    try {
      const { data } = await api.get<StreakState>('/streak/today')
      state.value = data
      if (data.is_first_today) {
        showToast(data.is_milestone ? 5000 : 3000)
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
  }
}
