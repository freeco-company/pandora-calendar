<script setup lang="ts">
/**
 * HealingGradientBg — 療癒漸層背景元件
 *
 * 給 hero / paywall / pattern-report / 年回顧 等需要強調的 section 用。
 * 提供 5 種 tone preset + 可選 animated（gradient 緩慢 shift）。
 *
 * Usage:
 *   <HealingGradientBg tone="peach" :animated="true" class="rounded-3xl p-6">
 *     <h1>...</h1>
 *   </HealingGradientBg>
 */
import { computed, onMounted, ref } from 'vue'

type Tone = 'peach' | 'sakura' | 'lavender' | 'sage' | 'mixed'

const props = withDefaults(
  defineProps<{
    tone?: Tone
    animated?: boolean
  }>(),
  {
    tone: 'peach',
    animated: false,
  },
)

const reduceMotion = ref(false)

onMounted(() => {
  if (typeof window !== 'undefined' && window.matchMedia) {
    reduceMotion.value = window.matchMedia('(prefers-reduced-motion: reduce)').matches
  }
})

const gradientMap: Record<Tone, string> = {
  peach: 'linear-gradient(135deg, #FFE4D2 0%, #FFCCA8 50%, #FFAE7A 100%)',
  sakura: 'linear-gradient(135deg, #FFF5F7 0%, #FCE4EA 50%, #F8C7D2 100%)',
  lavender: 'linear-gradient(135deg, #F6F3FB 0%, #E9E0F4 50%, #D2C0E8 100%)',
  sage: 'linear-gradient(135deg, #F2F6F0 0%, #E0EBDB 50%, #C3D8B9 100%)',
  mixed:
    'linear-gradient(135deg, #FFE4D2 0%, #FCE4EA 35%, #E9E0F4 70%, #E0EBDB 100%)',
}

const bgStyle = computed(() => {
  const animate = props.animated && !reduceMotion.value
  return {
    background: gradientMap[props.tone],
    backgroundSize: animate ? '200% 200%' : '100% 100%',
    animation: animate ? 'gradient-shift 14s ease infinite' : 'none',
  }
})
</script>

<template>
  <div class="healing-gradient-bg relative overflow-hidden" :style="bgStyle">
    <slot />
  </div>
</template>

<style scoped>
.healing-gradient-bg {
  /* 漸層動畫已透過 inline style 套用，此處保 hook 給外部 className override radius / padding */
}
</style>
