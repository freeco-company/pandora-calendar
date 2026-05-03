import { ref } from 'vue'
import { EcommerceApi, type EcommerceEligibility } from '../api'

/**
 * 婕樂纖深層商品連結 gate composable（P5）。
 *
 * - cache 1 hr（per session）— gate 條件不會頻繁變化
 * - 失敗時保守：eligible = false（紅線：寧可不顯示也不要錯誤顯示）
 * - 主流程任何頁面都不應呼叫此 composable，僅 Profile 與 EcommerceMemberView 使用
 */

const CACHE_TTL_MS = 60 * 60 * 1000 // 1 hr

let cached: { fetched_at: number; result: EcommerceEligibility } | null = null
let inflight: Promise<EcommerceEligibility> | null = null

async function fetchEligibility(): Promise<EcommerceEligibility> {
  if (cached && Date.now() - cached.fetched_at < CACHE_TTL_MS) {
    return cached.result
  }
  if (inflight) return inflight
  inflight = (async () => {
    try {
      const res = await EcommerceApi.eligibility()
      const result = res.data.data
      cached = { fetched_at: Date.now(), result }
      return result
    } catch {
      // 保守 default：fail closed（紅線）
      const fallback: EcommerceEligibility = {
        eligible: false,
        reasons: ['not_linked'],
        days_used: 0,
      }
      cached = { fetched_at: Date.now(), result: fallback }
      return fallback
    } finally {
      inflight = null
    }
  })()
  return inflight
}

export function useEcommerceGate() {
  const eligible = ref(false)
  const reasons = ref<EcommerceEligibility['reasons']>([])
  const daysUsed = ref(0)
  const loaded = ref(false)

  async function load(): Promise<void> {
    const r = await fetchEligibility()
    eligible.value = r.eligible
    reasons.value = r.reasons
    daysUsed.value = r.days_used
    loaded.value = true
  }

  function invalidate(): void {
    cached = null
  }

  return { eligible, reasons, daysUsed, loaded, load, invalidate }
}
