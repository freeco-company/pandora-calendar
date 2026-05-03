/**
 * useEconomy — 朵朵幣 balance + history (singleton, refresh on focus)
 * 後端尚未必然就緒：所有 endpoint fail 都 graceful fallback 到 0 / []。
 */
import { ref, computed } from 'vue'
import { EconomyApi, type EconomyTransaction } from '../api'

const balance = ref<number>(0)
const transactions = ref<EconomyTransaction[]>([])
const loading = ref(false)
const lastDelta = ref<number>(0)
const showCoinBurst = ref(false)

let inflight: Promise<void> | null = null

async function refresh() {
  if (inflight) return inflight
  loading.value = true
  inflight = (async () => {
    try {
      const r = await EconomyApi.balance()
      const next = r.data?.data?.balance ?? 0
      const delta = next - balance.value
      if (delta > 0 && balance.value > 0) {
        lastDelta.value = delta
        showCoinBurst.value = true
        setTimeout(() => (showCoinBurst.value = false), 1400)
      }
      balance.value = next
    } catch {
      // graceful — endpoint 還沒就緒 / 401 / network
    } finally {
      loading.value = false
    }
  })().finally(() => (inflight = null))
  return inflight
}

async function loadHistory(limit = 50) {
  try {
    const r = await EconomyApi.history(limit)
    transactions.value = r.data?.data?.transactions ?? []
  } catch {
    transactions.value = []
  }
}

export function useEconomy() {
  return {
    balance: computed(() => balance.value),
    transactions: computed(() => transactions.value),
    loading: computed(() => loading.value),
    lastDelta: computed(() => lastDelta.value),
    showCoinBurst: computed(() => showCoinBurst.value),
    refresh,
    loadHistory,
  }
}
