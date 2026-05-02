<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { SubscriptionApi, type SubscriptionProduct } from '../api'
import { useEntitlementsStore } from '../stores/entitlements'
import Card from '../components/ui/Card.vue'
import Spinner from '../components/ui/Spinner.vue'
import Character from '../components/Character.vue'

const router = useRouter()
const ent = useEntitlementsStore()
const products = ref<SubscriptionProduct[]>([])
const features = ref<string[]>([])
const loading = ref(false)
const initialLoad = ref(true)
const error = ref<string | null>(null)

onMounted(async () => {
  ent.load()
  try {
    const res = await SubscriptionApi.products()
    products.value = res.data.data
    features.value = res.data.features
  } finally {
    initialLoad.value = false
  }
})

const annualProduct = computed(() => products.value.find((p) => p.id.endsWith('annual')))

async function ecpayCheckout(productId: string) {
  loading.value = true
  error.value = null
  try {
    const returnUrl = `${window.location.origin}${window.location.pathname}#/me?ecpay=ok`
    const { data } = await SubscriptionApi.ecpayCheckout(productId, returnUrl)
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
  <div class="px-5 pt-8 pb-6 max-w-md mx-auto space-y-5">
    <button @click="back" class="font-zen text-sm text-peach-500 hover:text-peach-400">
      ← 回我的
    </button>

    <header class="text-center space-y-2">
      <div class="flex justify-center">
        <Character species="dodo" mood="cheering" outfit="fp_crown" :level="20" :size="130" :show-halo="true" :floaty="true" />
      </div>
      <p class="font-zen text-xs text-stone-500 tracking-widest uppercase">Premium</p>
      <h1 class="font-display text-3xl font-bold text-peach-500">朵朵陪妳更深一點</h1>
      <p class="font-zen text-sm text-stone-500">解鎖 PMS 分析、無限 check-in 與每週報告</p>
    </header>

    <Spinner v-if="initialLoad" label="載入中..." />

    <Card
      v-else-if="ent.isPremium()"
      tone="peach"
      data-test="already-premium"
      class="text-center text-white space-y-1"
    >
      <p class="font-display font-bold text-lg">妳已經是 Premium ✨</p>
      <p class="text-xs opacity-90 font-zen" v-if="ent.data?.premium_until">
        到期：{{ ent.data.premium_until.slice(0, 10) }}
      </p>
    </Card>

    <template v-else>
      <Card tone="cream" class="space-y-2.5">
        <h3 class="font-display font-bold text-peach-500 text-base mb-1">Premium 解鎖</h3>
        <ul class="space-y-2 text-sm text-stone-700 font-zen">
          <li v-for="f in features" :key="f" class="flex gap-2 items-start">
            <span class="text-peach-400 mt-0.5">✨</span><span>{{ f }}</span>
          </li>
        </ul>
      </Card>

      <div class="space-y-3">
        <button
          v-for="p in products"
          :key="p.id"
          :data-test="`buy-${p.id}`"
          :disabled="loading"
          @click="ecpayCheckout(p.id)"
          class="relative w-full bg-white rounded-3xl p-5 text-left transition-all active:scale-[0.99] disabled:opacity-50 shadow-soft"
          :class="
            p.id.endsWith('annual')
              ? 'border-2 border-peach-400 ring-4 ring-peach-100'
              : 'border border-cream-200'
          "
        >
          <span
            v-if="p === annualProduct"
            class="absolute -top-3 left-5 bg-gradient-to-r from-peach-400 to-sakura-400 text-white text-[10px] font-zen font-bold px-3 py-1 rounded-full shadow-soft"
          >
            最受歡迎
          </span>
          <div class="flex justify-between items-baseline">
            <span class="font-display font-bold text-peach-500 text-base">{{ p.title }}</span>
            <span
              v-if="p.discount"
              class="text-[11px] bg-sakura-100 text-sakura-500 font-zen font-semibold px-2.5 py-0.5 rounded-full"
            >{{ p.discount }}</span>
          </div>
          <div class="mt-2 flex items-baseline gap-1">
            <span class="font-display font-black text-3xl text-peach-500">NT${{ p.price_twd }}</span>
            <span class="text-sm text-stone-500 font-normal font-zen">
              / {{ p.period === 'year' ? '年' : '月' }}
            </span>
          </div>
          <p
            v-if="p.monthly_equivalent"
            class="text-xs text-stone-500 mt-1 font-zen"
          >
            等於 NT${{ p.monthly_equivalent }} / 月
          </p>
        </button>
      </div>

      <p v-if="error" class="text-center text-sakura-500 text-sm font-zen">{{ error }}</p>
    </template>

    <p class="text-center text-[10px] text-stone-400 pt-2 font-zen leading-relaxed">
      訂閱會自動續訂，可隨時取消。<br />
      ❌ 不做廣告 · ❌ 不賣資料 · 妳的週期資料只屬於妳
    </p>
  </div>
</template>
