<script setup lang="ts">
/**
 * RandomEventCard — 每天首訪可能彈出的隨機事件卡
 *
 * GET /v1/me/random-event/today，後端做 7-day cooldown / 同類型不重複。
 * 沒事件 → 不 render；有事件 → emoji + dodo dialog + claim button → +coin 動畫
 */
import { onMounted, onActivated, ref } from 'vue'
import { RandomEventApi, type RandomEvent } from '../api'
import { useTone } from '../composables/useTone'
import { useEconomy } from '../composables/useEconomy'

const { t } = useTone()
const economy = useEconomy()
const event = ref<RandomEvent | null>(null)
const claiming = ref(false)
const dismissed = ref(false)
const justClaimed = ref(false)

async function load() {
  try {
    const r = await RandomEventApi.today()
    event.value = r.data?.data ?? null
  } catch {
    event.value = null
  }
}

async function claim() {
  if (!event.value || event.value.claimed || claiming.value) return
  claiming.value = true
  try {
    await RandomEventApi.claim(event.value.id)
    justClaimed.value = true
    event.value = { ...event.value, claimed: true }
    economy.refresh()
    setTimeout(() => (dismissed.value = true), 1800)
  } catch {
    // graceful fail — keep card showing
  } finally {
    claiming.value = false
  }
}

function skip() {
  dismissed.value = true
}

onMounted(load)
onActivated(() => {
  if (!event.value && !dismissed.value) load()
})
</script>

<template>
  <transition name="event-fade">
    <div
      v-if="event && !dismissed"
      class="rounded-3xl bg-gradient-to-br from-peach-50 to-sakura-50 p-4 shadow-card mb-4 relative overflow-hidden"
      data-test="random-event-card"
    >
      <button
        @click="skip"
        class="absolute top-2 right-3 text-stone-400 hover:text-stone-600 text-xl leading-none"
        :aria-label="t('random_event_skip')"
        data-test="random-event-skip"
      >×</button>
      <div class="flex items-start gap-3">
        <div class="shrink-0 text-4xl" aria-hidden="true">{{ event.emoji }}</div>
        <div class="flex-1 min-w-0 pr-6">
          <p class="font-zen text-[10px] uppercase tracking-widest text-peach-500">
            {{ t('random_event_eyebrow') }}
          </p>
          <p class="font-display font-bold text-peach-500 text-base leading-tight mt-0.5">
            {{ event.title }}
          </p>
          <p class="font-zen text-[12px] text-stone-700 leading-relaxed mt-1.5">
            「{{ event.dodo_dialog }}」
            <span class="text-[10px] text-stone-400">— {{ t('common_dodo_says') }}</span>
          </p>
          <div class="flex items-center gap-2 mt-2.5">
            <button
              v-if="!event.claimed"
              @click="claim"
              :disabled="claiming"
              class="bg-peach-500 text-white font-zen text-xs font-bold px-3.5 py-1.5 rounded-full active:scale-95 transition-transform disabled:opacity-60"
              data-test="random-event-claim"
            >
              {{ claiming
                ? t('common_loading')
                : t('random_event_claim', { coin: event.reward_coin, xp: event.reward_xp }) }}
            </button>
            <span
              v-else
              class="inline-flex items-center gap-1 bg-sage-100 text-sage-700 font-zen text-xs px-3 py-1.5 rounded-full"
            >
              ✓ {{ t('random_event_claimed') }}
            </span>
          </div>
        </div>
      </div>
      <!-- celebration sparkle overlay -->
      <transition name="sparkle">
        <div
          v-if="justClaimed"
          class="absolute inset-0 pointer-events-none flex items-center justify-center text-5xl"
          aria-hidden="true"
        >
          ✨
        </div>
      </transition>
    </div>
  </transition>
</template>

<style scoped>
.event-fade-enter-active,
.event-fade-leave-active {
  transition: all 0.4s ease;
}
.event-fade-enter-from {
  opacity: 0;
  transform: translateY(-8px);
}
.event-fade-leave-to {
  opacity: 0;
  transform: scale(0.96);
}
.sparkle-enter-active {
  animation: sparkle-spin 1.4s ease-in-out;
}
@keyframes sparkle-spin {
  0% { opacity: 0; transform: scale(0.4) rotate(0deg); }
  50% { opacity: 1; transform: scale(1.4) rotate(180deg); }
  100% { opacity: 0; transform: scale(1) rotate(360deg); }
}
@media (prefers-reduced-motion: reduce) {
  .event-fade-enter-active,
  .event-fade-leave-active,
  .sparkle-enter-active {
    transition: opacity 0.2s;
    animation: none;
  }
}
</style>
