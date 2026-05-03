<script setup lang="ts">
/**
 * AmbientSparkles — 背景療癒粒子層
 *
 * 全局 mount 一次（在 App.vue），在 router-view 之下飄浮 sparkle 粒子，
 * 增加療癒氛圍但不擋互動。
 *
 * Accessibility / 性能：
 * - prefers-reduced-motion: 不顯示
 * - inclusive mode: 不顯示（更中性、更專注）
 * - mobile (< 640px)：粒子數減半
 * - pointer-events: none 不擋點擊
 * - z-index: 0 在 router-view 之下
 */
import { computed, onMounted, onUnmounted, ref } from 'vue'

interface Sparkle {
  id: number
  left: number // %
  top: number // %
  size: number // px
  delay: number // s
  duration: number // s
  hue: 'peach' | 'sakura' | 'lavender'
}

const reduceMotion = ref(false)
const isMobile = ref(false)
const inclusive = ref(false)

function detectInclusive(): boolean {
  try {
    return localStorage.getItem('inclusive_mode') === 'true'
  } catch {
    return false
  }
}

let mql: MediaQueryList | null = null
let mqlMobile: MediaQueryList | null = null

function applyMediaState() {
  reduceMotion.value = mql?.matches ?? false
  isMobile.value = mqlMobile?.matches ?? false
}

onMounted(() => {
  inclusive.value = detectInclusive()
  if (typeof window !== 'undefined' && window.matchMedia) {
    mql = window.matchMedia('(prefers-reduced-motion: reduce)')
    mqlMobile = window.matchMedia('(max-width: 640px)')
    applyMediaState()
    mql.addEventListener('change', applyMediaState)
    mqlMobile.addEventListener('change', applyMediaState)
  }
})

onUnmounted(() => {
  mql?.removeEventListener('change', applyMediaState)
  mqlMobile?.removeEventListener('change', applyMediaState)
})

const visible = computed(() => !reduceMotion.value && !inclusive.value)

const sparkles = computed<Sparkle[]>(() => {
  const count = isMobile.value ? 4 : 8
  const hues: Sparkle['hue'][] = ['peach', 'sakura', 'lavender']
  return Array.from({ length: count }, (_, i) => ({
    id: i,
    left: Math.random() * 100,
    top: 60 + Math.random() * 40, // 從下半部出發向上飄
    size: 6 + Math.random() * 10,
    delay: Math.random() * 6,
    duration: 8 + Math.random() * 6,
    hue: hues[i % hues.length],
  }))
})

const hueColor: Record<Sparkle['hue'], string> = {
  peach: '#FFAE7A',
  sakura: '#F0A0B2',
  lavender: '#B59AD7',
}
</script>

<template>
  <div
    v-if="visible"
    class="ambient-sparkles"
    aria-hidden="true"
  >
    <span
      v-for="s in sparkles"
      :key="s.id"
      class="sparkle"
      :style="{
        left: s.left + '%',
        top: s.top + '%',
        width: s.size + 'px',
        height: s.size + 'px',
        background: hueColor[s.hue],
        animationDelay: s.delay + 's',
        animationDuration: s.duration + 's',
      }"
    />
  </div>
</template>

<style scoped>
.ambient-sparkles {
  position: fixed;
  inset: 0;
  z-index: 0;
  pointer-events: none;
  overflow: hidden;
}

.sparkle {
  position: absolute;
  border-radius: 50%;
  opacity: 0;
  filter: blur(0.5px);
  box-shadow: 0 0 12px currentColor;
  animation-name: ambient-float;
  animation-iteration-count: infinite;
  animation-timing-function: ease-in-out;
  will-change: transform, opacity;
}

@keyframes ambient-float {
  0% {
    transform: translateY(0) scale(0.6) rotate(0deg);
    opacity: 0;
  }
  20% {
    opacity: 0.55;
  }
  50% {
    opacity: 0.7;
    transform: translateY(-60px) scale(1) rotate(120deg);
  }
  80% {
    opacity: 0.4;
  }
  100% {
    transform: translateY(-140px) scale(0.7) rotate(240deg);
    opacity: 0;
  }
}
</style>
