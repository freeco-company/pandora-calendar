<script setup lang="ts">
/**
 * SolarTermBanner — 24 節氣 active 期間 banner（活當下節氣的前後 3 天）
 *
 * 後端 GET /v1/solar-term/current 回 null 時不 render（無侵略性）。
 * 點擊參與 → POST /participate → +N 朵朵幣 → 顯示 ✓ 完成
 */
import { onMounted, onActivated, ref } from 'vue'
import { SolarTermApi, type SolarTermBanner } from '../api'
import { useTone } from '../composables/useTone'
import { useEconomy } from '../composables/useEconomy'

const { t } = useTone()
const economy = useEconomy()
const banner = ref<SolarTermBanner | null>(null)
const claiming = ref(false)
const error = ref<string | null>(null)

async function load() {
  try {
    const r = await SolarTermApi.current()
    banner.value = r.data?.data ?? null
  } catch {
    banner.value = null
  }
}

async function participate() {
  if (!banner.value || banner.value.participated || claiming.value) return
  claiming.value = true
  error.value = null
  try {
    await SolarTermApi.participate(banner.value.term_key)
    banner.value = { ...banner.value, participated: true }
    economy.refresh()
  } catch {
    error.value = t('solar_term_participate_failed')
  } finally {
    claiming.value = false
  }
}

onMounted(load)
onActivated(load)
</script>

<template>
  <div
    v-if="banner"
    class="rounded-3xl bg-gradient-to-br from-sage-50 via-cream-50 to-peach-50 p-4 shadow-soft mb-4"
    data-test="solar-term-banner"
  >
    <div class="flex items-start gap-3">
      <div class="shrink-0 text-3xl" aria-hidden="true">🌿</div>
      <div class="flex-1 min-w-0">
        <p class="font-zen text-[10px] uppercase tracking-widest text-sage-600">
          {{ t('solar_term_eyebrow') }}
        </p>
        <p class="font-display font-bold text-peach-500 text-lg leading-tight mt-0.5">
          {{ banner.term_name }}
        </p>
        <p class="font-zen text-[12px] text-stone-600 leading-relaxed mt-1.5">
          {{ banner.dodo_message }}
        </p>
        <div class="flex items-center gap-2 mt-2.5">
          <button
            v-if="!banner.participated"
            @click="participate"
            :disabled="claiming"
            class="bg-peach-500 text-white font-zen text-xs font-bold px-3.5 py-1.5 rounded-full active:scale-95 transition-transform disabled:opacity-60"
            data-test="solar-term-participate"
          >
            {{ claiming ? t('common_loading') : t('solar_term_join_btn', { coin: banner.reward_coin }) }}
          </button>
          <span
            v-else
            class="inline-flex items-center gap-1 bg-sage-100 text-sage-700 font-zen text-xs px-3 py-1.5 rounded-full"
            data-test="solar-term-done"
          >
            ✓ {{ t('solar_term_done') }}
          </span>
        </div>
        <p v-if="error" class="font-zen text-[11px] text-rose-500 mt-1.5">{{ error }}</p>
      </div>
    </div>
  </div>
</template>
