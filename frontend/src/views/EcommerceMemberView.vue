<script setup lang="ts">
/**
 * 婕樂纖會員深層頁（P5）— /me/jerosse-member
 *
 * 紅線：
 * - 此頁面僅由 Profile.vue 中的「婕樂纖會員」連結進入；該連結本身已 gate（v-if="eligible"）。
 * - Gate fail 時 backend recommendations endpoint 回 403；此頁面顯示 empty state。
 * - 不放任何 ad / banner / paid placement，純文字 + 朵朵語氣。
 * - 商品文案在 backend MotherEcommerceConnector 已過 LegalContentSanitizer。
 */
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { EcommerceApi, type ProductLink } from '../api'
import { useEcommerceGate } from '../composables/useEcommerceGate'
import Card from '../components/ui/Card.vue'
import Spinner from '../components/ui/Spinner.vue'
import EmptyState from '../components/ui/EmptyState.vue'
import { useTone } from '../composables/useTone'

const router = useRouter()
const { t } = useTone()
const gate = useEcommerceGate()

const links = ref<ProductLink[]>([])
const loading = ref(true)

onMounted(async () => {
  try {
    await gate.load()
    if (!gate.eligible.value) {
      loading.value = false
      return
    }
    const res = await EcommerceApi.recommendations()
    links.value = res.data.data
  } catch {
    // 任何錯誤 → 保守不顯示商品（紅線：fail closed）
    links.value = []
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <div class="px-5 pt-8 pb-6 max-w-md mx-auto space-y-4">
    <button
      type="button"
      @click="router.push('/me')"
      class="font-zen text-sm text-peach-500 hover:text-peach-400"
    >
      {{ t('jerosse_back') }}
    </button>

    <header>
      <h1 class="font-display text-2xl font-bold text-peach-500">
        {{ t('ecommerce_section_title') }}
      </h1>
      <p class="font-zen text-sm text-stone-500 mt-1">{{ t('jerosse_subtitle') }}</p>
    </header>

    <Spinner v-if="loading" :label="t('jerosse_loading')" />

    <Card
      v-else-if="!gate.eligible.value"
      tone="plain"
      class="text-center space-y-2 text-sm text-stone-600"
    >
      <p class="font-zen leading-relaxed">{{ t('ecommerce_locked_message') }}</p>
      <p class="text-xs text-stone-400 font-zen leading-relaxed">
        {{ t('jerosse_gate_conditions') }}
      </p>
    </Card>

    <template v-else>
      <h2 class="font-display text-base text-stone-700 mt-2">
        {{ t('ecommerce_recommendation_title') }}
      </h2>

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
          data-test="ecommerce-product-link"
        >
          {{ t('ecommerce_cta') }}
        </a>
      </Card>

      <EmptyState
        v-if="!links.length"
        :show-dodo="true"
        :title="t('jerosse_empty_title')"
        :subtitle="t('jerosse_empty_subtitle')"
      />
    </template>
  </div>
</template>
