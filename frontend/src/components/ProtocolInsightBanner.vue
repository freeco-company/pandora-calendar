<script setup lang="ts">
/**
 * ProtocolInsightBanner — 朵朵主動報「我發現 X 對妳 work」。
 *
 * 嵌在 Calendar 主頁 GamificationStrip 之後 + TodayActionCard 之前。
 * 沒 insight → 不 render（不占空間）。
 *
 * 點「下次再說」→ POST dismiss endpoint，本地隱藏 + 7 天內後端不再 return 同 key。
 * 點「試試這個」→ 跳 /me/action-today（讓朵朵 daily action 入口自然帶到今天的卡）。
 */
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { ProtocolInsightApi, type ProtocolInsight } from '../api'
import { useTone } from '../composables/useTone'
import Icon from './icons/Icon.vue'

const { t } = useTone()
const router = useRouter()

const insight = ref<ProtocolInsight | null>(null)
const loading = ref(true)
const dismissing = ref(false)

async function load() {
  loading.value = true
  try {
    const r = await ProtocolInsightApi.active()
    insight.value = r.data.data
  } catch {
    insight.value = null
  } finally {
    loading.value = false
  }
}

async function onDismiss() {
  if (!insight.value || dismissing.value) return
  dismissing.value = true
  const key = insight.value.insight_key
  // 樂觀隱藏：先隱藏，後端 fail 也不再彈出（7 天 cooldown 是後端權威）
  insight.value = null
  try {
    await ProtocolInsightApi.dismiss(key)
  } catch {
    /* 已隱藏，下次 load 後端若仍 return 會再出（自然 fallback） */
  } finally {
    dismissing.value = false
  }
}

function onTry() {
  router.push('/me/action-today')
}

onMounted(load)
</script>

<template>
  <Transition name="ach">
    <section
      v-if="!loading && insight"
      class="rounded-3xl px-4 py-4 mb-4 shadow-soft border border-peach-200"
      style="background: linear-gradient(135deg, #ffe8d8 0%, #fff3e6 60%, #fce8e0 100%)"
      data-test="protocol-insight-banner"
    >
      <div class="flex items-start gap-3">
        <Icon name="dodo" :size="28" animated decorative class="shrink-0" />
        <div class="flex-1 min-w-0">
          <p class="font-zen text-[11px] text-peach-500 tracking-wide mb-1">
            {{ t('dodo_say') }}
          </p>
          <p class="text-[14px] leading-relaxed text-stone-700 font-zen">
            {{ insight.message }}
          </p>
          <div class="flex gap-2 mt-3">
            <button
              type="button"
              class="text-[12px] font-zen text-white bg-peach-500 hover:bg-peach-600 active:scale-[0.98] rounded-full px-4 py-1.5 transition"
              data-test="protocol-insight-try"
              @click="onTry"
            >
              {{ t('protocol_insight_try_btn') }}
            </button>
            <button
              type="button"
              class="text-[12px] font-zen text-stone-500 hover:text-stone-700 rounded-full px-3 py-1.5"
              :disabled="dismissing"
              data-test="protocol-insight-dismiss"
              @click="onDismiss"
            >
              {{ t('protocol_insight_dismiss_btn') }}
            </button>
          </div>
        </div>
      </div>
    </section>
  </Transition>
</template>
