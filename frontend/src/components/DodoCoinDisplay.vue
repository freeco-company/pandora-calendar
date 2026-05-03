<script setup lang="ts">
/**
 * DodoCoinDisplay — 朵朵幣徽章 + balance + 變動 burst 動畫
 *
 * 用法：
 *   <DodoCoinDisplay /> — 自己 fetch + 顯示
 *   <DodoCoinDisplay :balance="123" :delta="50" /> — 受控
 *
 * 點擊：toggle tooltip，說明朵朵幣怎麼賺 / 怎麼用。
 */
import { computed, onMounted, onActivated, ref } from 'vue'
import { useEconomy } from '../composables/useEconomy'
import { useTone } from '../composables/useTone'

const props = defineProps<{
  balance?: number | null
  delta?: number | null
  size?: 'sm' | 'md' | 'lg'
  hideTooltip?: boolean
}>()

const { t } = useTone()
const economy = useEconomy()
const showTooltip = ref(false)

const displayBalance = computed(() =>
  typeof props.balance === 'number' ? props.balance : economy.balance.value,
)
const showBurst = computed(() => {
  if (typeof props.delta === 'number' && props.delta > 0) return true
  return economy.showCoinBurst.value
})
const burstDelta = computed(() => props.delta ?? economy.lastDelta.value)

const sizeClasses = computed(() => {
  switch (props.size ?? 'md') {
    case 'sm':
      return { wrap: 'gap-1.5 text-xs', coin: 'w-5 h-5', num: 'text-sm' }
    case 'lg':
      return { wrap: 'gap-2.5 text-base', coin: 'w-9 h-9', num: 'text-2xl' }
    default:
      return { wrap: 'gap-2 text-sm', coin: 'w-7 h-7', num: 'text-lg' }
  }
})

onMounted(() => {
  if (typeof props.balance !== 'number') economy.refresh()
})
onActivated(() => {
  if (typeof props.balance !== 'number') economy.refresh()
})

function toggleTooltip() {
  if (props.hideTooltip) return
  showTooltip.value = !showTooltip.value
}
</script>

<template>
  <div class="relative inline-block">
    <button
      type="button"
      @click="toggleTooltip"
      class="inline-flex items-center font-zen font-bold text-peach-500 active:scale-95 transition-transform"
      :class="sizeClasses.wrap"
      :title="t('coin_short_label')"
      data-test="dodo-coin-display"
    >
      <!-- coin SVG (peach/gold gradient circle with dodo silhouette) -->
      <span class="relative shrink-0" :class="sizeClasses.coin">
        <svg viewBox="0 0 32 32" class="w-full h-full" aria-hidden="true">
          <defs>
            <radialGradient id="coin-grad" cx="0.35" cy="0.3" r="0.85">
              <stop offset="0%" stop-color="#FFE6CC" />
              <stop offset="55%" stop-color="#F8B98E" />
              <stop offset="100%" stop-color="#D67E51" />
            </radialGradient>
          </defs>
          <circle cx="16" cy="16" r="14" fill="url(#coin-grad)" stroke="#B05E32" stroke-width="1.2" />
          <circle cx="16" cy="16" r="11" fill="none" stroke="#B05E3266" stroke-width="0.6" stroke-dasharray="2 2" />
          <text x="16" y="20.5" text-anchor="middle" font-size="11" font-weight="700" fill="#7A3B1B" font-family="ui-rounded, system-ui">朵</text>
        </svg>
        <!-- burst animation -->
        <transition name="coin-burst">
          <span
            v-if="showBurst && burstDelta > 0"
            class="absolute -top-3 -right-2 text-peach-500 font-bold text-xs pointer-events-none"
            data-test="coin-burst"
          >
            +{{ burstDelta }}
          </span>
        </transition>
      </span>
      <span class="font-display tabular-nums" :class="sizeClasses.num" data-test="coin-balance">
        {{ displayBalance.toLocaleString() }}
      </span>
    </button>

    <!-- tooltip -->
    <div
      v-if="showTooltip && !hideTooltip"
      class="absolute z-30 top-full mt-2 left-1/2 -translate-x-1/2 w-60 bg-white rounded-2xl shadow-card p-3 text-left"
      role="tooltip"
      data-test="coin-tooltip"
    >
      <p class="font-zen text-[12px] text-stone-700 leading-relaxed">
        {{ t('coin_tooltip_what') }}
      </p>
      <p class="font-zen text-[11px] text-stone-500 mt-1.5">{{ t('coin_tooltip_earn') }}</p>
      <p class="font-zen text-[11px] text-stone-500 mt-0.5">{{ t('coin_tooltip_spend') }}</p>
      <button
        @click="showTooltip = false"
        class="mt-2 text-[10px] font-zen text-peach-500 hover:underline"
      >
        {{ t('common_got_it') }}
      </button>
    </div>
  </div>
</template>

<style scoped>
.coin-burst-enter-active {
  transition: all 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
}
.coin-burst-leave-active {
  transition: all 0.6s ease-in;
}
.coin-burst-enter-from {
  opacity: 0;
  transform: translateY(0) scale(0.6);
}
.coin-burst-leave-to {
  opacity: 0;
  transform: translateY(-18px) scale(1.1);
}
@media (prefers-reduced-motion: reduce) {
  .coin-burst-enter-active,
  .coin-burst-leave-active {
    transition: opacity 0.2s;
  }
}
</style>
