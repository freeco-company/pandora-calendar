/**
 * usePregnancyMode — shared reactive state for P4 pregnancy mode.
 *
 * Why a composable: 至少 3 個地方要讀（Profile toggle / Calendar banner / PregnancyModeView），
 * 不希望各頁各打一次 API；module-singleton ref 對齊既有 usePet 模式。
 *
 * 觸發 refresh 的時機：
 *   - mount（頁面初次載入）
 *   - 'pandora:pregnancy-updated' custom event（start / end 後 dispatch）
 *
 * 不在 LS 緩存：孕期是高敏感資料，每次都從後端取真實狀態。
 */
import { onMounted, onUnmounted, ref } from 'vue'
import { PregnancyApi, type PregnancyState, type PregnancyEndReason } from '../api'

const state = ref<PregnancyState | null>(null)
const loading = ref(false)
const loaded = ref(false)

async function refresh() {
  loading.value = true
  try {
    const res = await PregnancyApi.current()
    state.value = res.data.data
  } catch {
    // 404 / 402 / 網路錯都靜默 — UI 自己呈現空狀態 / 升級提示
    state.value = null
  } finally {
    loading.value = false
    loaded.value = true
  }
}

async function start(lmpDate: string): Promise<PregnancyState> {
  const res = await PregnancyApi.start(lmpDate)
  state.value = res.data.data
  window.dispatchEvent(new CustomEvent('pandora:pregnancy-updated'))
  return res.data.data
}

async function end(reason: PregnancyEndReason): Promise<void> {
  await PregnancyApi.end(reason)
  state.value = null
  window.dispatchEvent(new CustomEvent('pandora:pregnancy-updated'))
}

export function usePregnancyMode() {
  function onUpdated() {
    refresh()
  }

  onMounted(() => {
    if (!loaded.value) refresh()
    window.addEventListener('pandora:pregnancy-updated', onUpdated)
  })

  onUnmounted(() => {
    window.removeEventListener('pandora:pregnancy-updated', onUpdated)
  })

  return {
    state,
    loading,
    loaded,
    refresh,
    start,
    end,
    isActive: () => state.value?.status === 'active',
  }
}
