<script setup lang="ts">
/**
 * TrialBanner — peach gradient banner 顯示 freemium 7-day Premium trial 狀態。
 *
 * 4 visible variant：
 *   active        peach/cream  「Premium trial 還剩 N 天 ✨」
 *   last_2        peach+sakura「明後天 trial 就結束了」
 *   ending_today  peach+sakura「今天是 trial 最後一天」
 *   just_ended    sage/cream   「Trial 結束了 🌸」（24h 告別）
 */
import { computed } from 'vue'
import { useRouter } from 'vue-router'
import { useTrial } from '../composables/useTrial'
import { useTone } from '../composables/useTone'

const router = useRouter()
const { t } = useTone()
const trial = useTrial()

const days = computed(() => trial.daysRemaining.value ?? 0)
const variant = computed(() => trial.bannerKey.value)

const headline = computed(() => {
  switch (variant.value) {
    case 'active': return t('trial_banner_active_title', { days_left: days.value })
    case 'last_2': return t('trial_banner_last_2_title')
    case 'ending_today': return t('trial_banner_ending_title')
    case 'just_ended': return t('trial_banner_just_ended_title')
    default: return ''
  }
})

const subline = computed(() => {
  switch (variant.value) {
    case 'active': return t('trial_banner_active_body')
    case 'last_2': return t('trial_banner_last_2_body')
    case 'ending_today': return t('trial_banner_ending_body')
    case 'just_ended': return t('trial_banner_just_ended_body')
    default: return ''
  }
})

const ctaLabel = computed(() => {
  switch (variant.value) {
    case 'active': return t('trial_banner_active_cta')
    case 'last_2': return t('trial_banner_last_2_cta')
    case 'ending_today': return t('trial_banner_ending_cta')
    case 'just_ended': return t('trial_banner_just_ended_cta')
    default: return ''
  }
})

const toneClass = computed(() => {
  switch (variant.value) {
    case 'last_2':
    case 'ending_today':
      return 'from-peach-200 via-sakura-100 to-peach-50 ring-1 ring-sakura-200/50'
    case 'just_ended':
      return 'from-sage-100 via-cream-50 to-sage-50 ring-1 ring-sage-200/40'
    case 'active':
    default:
      return 'from-peach-100 via-cream-50 to-sakura-50 ring-1 ring-peach-200/50'
  }
})

function go() {
  router.push('/me/premium')
}

function dismissClick(e: Event) {
  e.stopPropagation()
  trial.dismiss()
}
</script>

<template>
  <button
    v-if="trial.showBanner.value"
    type="button"
    data-test="trial-banner"
    :data-variant="variant"
    :class="[
      'group relative w-full mb-3 rounded-3xl px-4 py-3 text-left flex items-center gap-3 bg-gradient-to-br shadow-soft transition-all active:scale-[0.99]',
      toneClass,
    ]"
    @click="go"
  >
    <span
      class="shrink-0 w-10 h-10 rounded-2xl bg-white/70 flex items-center justify-center text-xl trial-banner-sparkle"
      aria-hidden="true"
    >
      <template v-if="variant === 'just_ended'">🌸</template>
      <template v-else-if="variant === 'ending_today'">⏳</template>
      <template v-else>✨</template>
    </span>

    <div class="flex-1 min-w-0">
      <p class="font-display font-bold text-peach-600 text-[14px] leading-snug truncate">
        {{ headline }}
      </p>
      <p class="font-zen text-[11px] text-stone-600 leading-relaxed mt-0.5 truncate">
        {{ subline }}
      </p>
    </div>

    <span class="shrink-0 font-zen text-xs text-peach-500 hidden sm:inline">
      {{ ctaLabel }}
    </span>

    <button
      type="button"
      data-test="trial-banner-dismiss"
      :aria-label="t('trial_banner_dismiss_aria')"
      class="shrink-0 w-7 h-7 rounded-full text-stone-400 hover:text-stone-600 hover:bg-white/60 flex items-center justify-center text-base leading-none transition-colors"
      @click="dismissClick"
    >
      ×
    </button>
  </button>
</template>

<style scoped>
.trial-banner-sparkle {
  animation: trial-sparkle 2.4s ease-in-out infinite;
}
@keyframes trial-sparkle {
  0%, 100% { transform: scale(1) rotate(0deg); }
  50% { transform: scale(1.06) rotate(6deg); }
}
@media (prefers-reduced-motion: reduce) {
  .trial-banner-sparkle { animation: none; }
}
</style>
