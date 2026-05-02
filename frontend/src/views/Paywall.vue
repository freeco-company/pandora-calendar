<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { SubscriptionApi, type SubscriptionProduct } from '../api'
import { useEntitlementsStore } from '../stores/entitlements'

const router = useRouter()
const ent = useEntitlementsStore()
const products = ref<SubscriptionProduct[]>([])
const features = ref<string[]>([])
const loading = ref(false)
const error = ref<string | null>(null)

onMounted(async () => {
  ent.load()
  const res = await SubscriptionApi.products()
  products.value = res.data.data
  features.value = res.data.features
})

async function ecpayCheckout(productId: string) {
  loading.value = true
  error.value = null
  try {
    // ECPay 在 web 環境用，App 內走 IAP（Capacitor plugin）。
    const returnUrl = `${window.location.origin}${window.location.pathname}#/me?ecpay=ok`
    const { data } = await SubscriptionApi.ecpayCheckout(productId, returnUrl)
    // 這裡實際應該動態 build form 並 POST 到 action_url；P0 demo 顯示 form params。
    alert(`Demo Phase: ECPay checkout 觸發\n\naction_url: ${data.data.action_url}\n\n實際 prod 會自動 POST 到 ECPay 完成付款。`)
  } catch (e: any) {
    error.value = e?.response?.data?.message ?? '建立付款失敗'
  } finally {
    loading.value = false
  }
}

function back() {
  router.push('/me')
}
</script>

<template>
  <div class="px-5 pt-8 pb-4 max-w-md mx-auto space-y-4">
    <button @click="back" class="text-sm text-brand-600">← 回我的</button>

    <header class="text-center">
      <div class="text-5xl">💎</div>
      <h1 class="text-2xl font-bold text-brand-700 mt-2">Premium</h1>
      <p class="text-sm text-stone-500 mt-1">朵朵陪妳更深一點</p>
    </header>

    <div v-if="ent.isPremium()" data-test="already-premium" class="bg-brand-100 rounded-3xl p-5 text-center">
      <p class="text-brand-700 font-bold">妳已經是 Premium ✨</p>
      <p class="text-xs text-stone-500 mt-1" v-if="ent.data?.premium_until">
        到期：{{ ent.data.premium_until.slice(0, 10) }}
      </p>
    </div>

    <template v-else>
      <div class="bg-white rounded-3xl shadow-sm p-5 space-y-2 text-sm">
        <h3 class="font-bold text-brand-700 mb-2">Premium 解鎖</h3>
        <ul class="space-y-1.5 text-stone-700">
          <li v-for="f in features" :key="f">✨ {{ f }}</li>
        </ul>
      </div>

      <div class="space-y-3">
        <button
          v-for="p in products"
          :key="p.id"
          :data-test="`buy-${p.id}`"
          :disabled="loading"
          @click="ecpayCheckout(p.id)"
          class="w-full bg-white border-2 rounded-3xl p-5 text-left hover:bg-brand-50 disabled:opacity-50 transition"
          :class="p.id.endsWith('annual') ? 'border-brand-500' : 'border-brand-100'"
        >
          <div class="flex justify-between items-baseline">
            <span class="font-bold text-brand-700">{{ p.title }}</span>
            <span v-if="p.discount" class="text-xs bg-brand-600 text-white px-2 py-0.5 rounded-full">{{ p.discount }}</span>
          </div>
          <div class="mt-1 text-2xl font-black brand-text">
            NT${{ p.price_twd }}
            <span class="text-sm text-stone-500 font-normal">/ {{ p.period === 'year' ? '年' : '月' }}</span>
          </div>
          <p v-if="p.monthly_equivalent" class="text-xs text-stone-500 mt-1">
            等於 NT${{ p.monthly_equivalent }} / 月
          </p>
        </button>
      </div>

      <p v-if="error" class="text-center text-red-500 text-sm">{{ error }}</p>
    </template>

    <p class="text-center text-[10px] text-stone-400 pt-3">
      訂閱會自動續訂，可隨時取消。<br />
      ❌ 不做廣告 · ❌ 不賣資料 · 妳的週期資料只屬於妳
    </p>
  </div>
</template>

<style>
.brand-text { color: #9F6B3E; }
</style>
