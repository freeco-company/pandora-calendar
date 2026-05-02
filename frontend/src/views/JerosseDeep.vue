<script setup lang="ts">
/**
 * 婕樂纖會員深層頁。
 *
 * ⚠️ 紅線（meal 同模式）：這個頁面只在 user 主動「我的 → 婕樂纖會員」進來時才會顯示。
 * 主流程（月曆 / 記錄 / 朵朵 / 我的）都不能放捷徑進來。
 *
 * 後端 ProductLinkResolver 會根據 gate（母艦消費 + 訂閱中 + 連用 90 天）決定是否回傳商品連結。
 * gate 不通過時 → 顯示「妳還沒開通」狀態，不顯示任何商品。
 */
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { CommerceApi, type ProductLink } from '../api'

const router = useRouter()
const links = ref<ProductLink[]>([])
const gatePassed = ref(false)
const loading = ref(true)

onMounted(async () => {
  try {
    const res = await CommerceApi.productLinks()
    links.value = res.data.data
    gatePassed.value = res.data.gate_passed
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <div class="px-5 pt-8 pb-4 max-w-md mx-auto space-y-4">
    <button @click="router.push('/me')" class="text-sm text-brand-600">← 回我的</button>

    <header>
      <h1 class="text-2xl font-bold text-brand-700">婕樂纖會員專區</h1>
      <p class="text-sm text-stone-500">為長期使用月曆 + 婕樂纖會員的妳開的角落</p>
    </header>

    <div v-if="loading" class="text-center py-8 text-stone-400">載入中...</div>

    <div v-else-if="!gatePassed" class="bg-white rounded-3xl shadow-sm p-6 text-center space-y-2 text-sm text-stone-600">
      <p>這個區域目前對妳還沒開通。</p>
      <p class="text-xs text-stone-400">
        條件：在婕樂纖商店有過 1 次以上消費 · 訂閱 Premium · 月曆連用 ≥ 90 天
      </p>
    </div>

    <template v-else>
      <div v-for="link in links" :key="link.product_slug" class="bg-white rounded-3xl shadow-sm p-5 space-y-2">
        <p class="text-stone-700 leading-relaxed">{{ link.message }}</p>
        <a :href="link.mother_url" target="_blank" rel="noopener" class="inline-block text-sm text-brand-600 underline">
          看看商品 →
        </a>
      </div>
      <p v-if="!links.length" class="text-center text-sm text-stone-400">
        最近沒有要特別建議的商品 — 朵朵只在妳真的需要時才提。
      </p>
    </template>
  </div>
</template>
