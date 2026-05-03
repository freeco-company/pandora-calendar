/**
 * useDailyAction — 個人化每日行動 client state
 *
 * 對接 backend /v1/actions/* — 替代純 client-side useDailyQuest pool。
 * - loadToday() 從 ActionApi 拉今天的 action（可能 null）
 * - complete(id) optimistic update + 朵朵 reply toast
 * - submitFeedback() 發 helpful / neutral / unhelpful，朵朵 reply toast
 *
 * 朵朵 reply 走既有 'pandora:dodo-reply' window event，由 DodoReplyToast / 既有 toast 顯示。
 */
import { computed, ref } from 'vue'
import { ActionApi, type ActionFeedback, type DailyAction } from '../api'

const todayAction = ref<DailyAction | null>(null)
const loading = ref(false)
const error = ref<string | null>(null)
const feedbackSubmitted = ref(false)
const lastDodoReply = ref<string | null>(null)

function emitDodoReply(text: string | null | undefined) {
  if (!text) return
  lastDodoReply.value = text
  if (typeof window !== 'undefined') {
    window.dispatchEvent(new CustomEvent('pandora:dodo-reply', { detail: { text } }))
  }
}

export function useDailyAction() {
  async function loadToday() {
    loading.value = true
    error.value = null
    try {
      const r = await ActionApi.today()
      todayAction.value = r.data.data
      feedbackSubmitted.value = !!todayAction.value?.feedback
    } catch (e: any) {
      error.value = e?.response?.data?.message ?? 'load_failed'
      todayAction.value = null
    } finally {
      loading.value = false
    }
  }

  async function complete(id: number) {
    if (!todayAction.value || todayAction.value.id !== id) return
    // optimistic
    const prev = todayAction.value.is_completed
    todayAction.value = { ...todayAction.value, is_completed: true }
    try {
      const r = await ActionApi.complete(id)
      todayAction.value = r.data.data
      emitDodoReply(r.data.dodo_reply)
    } catch (e: any) {
      // rollback
      if (todayAction.value) {
        todayAction.value = { ...todayAction.value, is_completed: prev }
      }
      error.value = e?.response?.data?.message ?? 'complete_failed'
      throw e
    }
  }

  async function submitFeedback(id: number, feedback: ActionFeedback, body_note?: string) {
    if (!todayAction.value || todayAction.value.id !== id) return
    try {
      const r = await ActionApi.feedback(id, feedback, body_note)
      todayAction.value = r.data.data
      feedbackSubmitted.value = true
      emitDodoReply(r.data.dodo_reply)
    } catch (e: any) {
      error.value = e?.response?.data?.message ?? 'feedback_failed'
      throw e
    }
  }

  const completed = computed(() => !!todayAction.value?.is_completed)
  const reactiveDodoReply = computed(() => lastDodoReply.value)

  return {
    todayAction,
    loading,
    error,
    completed,
    feedbackSubmitted,
    reactiveDodoReply,
    loadToday,
    complete,
    submitFeedback,
  }
}
