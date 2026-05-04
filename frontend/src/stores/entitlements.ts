import { defineStore } from 'pinia'
import { ref } from 'vue'
import { SubscriptionApi, type Entitlements } from '../api'

export const useEntitlementsStore = defineStore('entitlements', () => {
  const data = ref<Entitlements | null>(null)
  const loading = ref(false)

  async function load() {
    loading.value = true
    try {
      const res = await SubscriptionApi.me()
      data.value = res.data.data
    } catch {
      data.value = {
        premium: false,
        premium_until: null,
        product_id: null,
        platform: null,
        auto_renew: false,
        trial: { is_trial: false, days_remaining: null, ends_at: null, trial_used: false },
      }
    } finally {
      loading.value = false
    }
  }

  function isPremium(): boolean {
    return !!data.value?.premium
  }

  function reset() {
    data.value = null
  }

  return { data, loading, load, isPremium, reset }
})
