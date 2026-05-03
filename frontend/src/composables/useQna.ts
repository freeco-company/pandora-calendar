/**
 * useQna — 朵朵 Q&A 狀態管理 composable
 *
 * - chat 風格 timeline（user → 朵朵 → user → ...）
 * - 進入頁面先 load 最近 30 天歷史
 * - 送問題：optimistic 加 user message + busy spinner、回來後加朵朵 reply
 * - 自殺旗標 → UI 顯示 1925 banner
 */
import { computed, ref } from 'vue'
import { QnaApi, type QnaItem } from '../api'

export interface QnaTurn {
  id: number
  question: string
  answer: string
  sources: number[]
  safety_flag: 'redline_self_harm' | 'redline_compliance' | null
  created_at?: string | null
  /** UI-only — 顯示 spinner 時 true */
  pending?: boolean
}

export function useQna() {
  const turns = ref<QnaTurn[]>([])
  const loading = ref(false)
  const sending = ref(false)
  const remaining = ref<number | null>(null)
  const isPremium = ref(false)
  const error = ref<string | null>(null)

  async function load() {
    loading.value = true
    error.value = null
    try {
      const res = await QnaApi.history(30)
      // 歷史最舊在最上、最新在最下（chat 慣例）
      turns.value = (res.data.data as QnaItem[])
        .slice()
        .reverse()
        .map((q) => ({
          id: q.id,
          question: q.question,
          answer: q.answer,
          sources: q.sources ?? [],
          safety_flag: q.safety_flag,
          created_at: q.created_at,
        }))
      remaining.value = res.data.meta.remaining_today
      isPremium.value = res.data.meta.is_premium
    } catch (e) {
      error.value = String(e)
    } finally {
      loading.value = false
    }
  }

  async function ask(question: string): Promise<{ ok: boolean; quotaExceeded?: boolean }> {
    if (!question.trim() || sending.value) return { ok: false }
    sending.value = true
    error.value = null
    const optimistic: QnaTurn = {
      id: -Date.now(),
      question,
      answer: '',
      sources: [],
      safety_flag: null,
      pending: true,
    }
    turns.value.push(optimistic)
    try {
      const res = await QnaApi.ask(question)
      const d = res.data.data
      // 替換 optimistic
      const idx = turns.value.findIndex((t) => t.id === optimistic.id)
      if (idx >= 0) {
        turns.value[idx] = {
          id: d.id,
          question,
          answer: d.answer,
          sources: d.sources,
          safety_flag: d.safety_flag,
        }
      }
      remaining.value = d.remaining_today
      isPremium.value = d.is_premium
      return { ok: true }
    } catch (e: unknown) {
      // 移除 optimistic
      turns.value = turns.value.filter((t) => t.id !== optimistic.id)
      const err = e as { response?: { status?: number; data?: { error?: string } } }
      if (err?.response?.status === 402) {
        return { ok: false, quotaExceeded: true }
      }
      error.value = String(e)
      return { ok: false }
    } finally {
      sending.value = false
    }
  }

  async function remove(id: number) {
    try {
      await QnaApi.remove(id)
      turns.value = turns.value.filter((t) => t.id !== id)
    } catch (e) {
      error.value = String(e)
    }
  }

  const remainingLabel = computed(() => {
    if (isPremium.value) return null
    return remaining.value
  })

  return { turns, loading, sending, remaining, remainingLabel, isPremium, error, load, ask, remove }
}
