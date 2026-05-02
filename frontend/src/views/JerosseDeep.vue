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
import Card from '../components/ui/Card.vue'
import Spinner from '../components/ui/Spinner.vue'
import EmptyState from '../components/ui/EmptyState.vue'

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
  <div class="px-5 pt-8 pb-6 max-w-md mx-auto space-y-4">
    <button @click="router.push('/me')" class="font-zen text-sm text-peach-500 hover:text-peach-400">
      ← 回我的
    </button>

    <header>
      <h1 class="font-display text-2xl font-bold text-peach-500">婕樂纖會員專區</h1>
      <p class="font-zen text-sm text-stone-500 mt-1">為長期使用月曆 + 婕樂纖會員的妳開的角落</p>
    </header>

    <Spinner v-if="loading" label="載入中..." />

    <Card v-else-if="!gatePassed" tone="plain" class="text-center space-y-2 text-sm text-stone-600">
      <p class="font-zen">這個區域目前對妳還沒開通。</p>
      <p class="text-xs text-stone-400 font-zen leading-relaxed">
        條件：在婕樂纖商店有過 1 次以上消費 · 訂閱 Premium · 月曆連用 ≥ 90 天
      </p>
    </Card>

    <template v-else>
      <Card
        v-for="link in links"
        :key="link.product_slug"
        tone="cream"
        class="space-y-2"
      >
        <p class="text-stone-700 leading-relaxed font-zen">{{ link.message }}</p>
        <a
          :href="link.mother_url"
          target="_blank"
          rel="noopener"
          class="inline-block text-sm text-peach-500 underline font-zen hover:text-peach-400"
        >
          看看商品 →
        </a>
      </Card>
      <EmptyState
        v-if="!links.length"
        :show-dodo="true"
        title="最近沒有要特別建議的商品"
        subtitle="朵朵只在妳真的需要時才提。"
      />
    </template>
  </div>
</template>
