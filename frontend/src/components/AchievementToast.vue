<script setup lang="ts">
import { onMounted, onUnmounted, ref } from 'vue'
import { useSfx } from '../lib/sound'
import type { AchievementDetail } from '../lib/gamification'

const sfx = useSfx()
const item = ref<AchievementDetail | null>(null)

function onAchievement(ev: Event) {
  const ce = ev as CustomEvent<AchievementDetail>
  if (!ce.detail) return
  item.value = ce.detail
  sfx.play('achievement')
  setTimeout(() => {
    item.value = null
  }, 4000)
}

onMounted(() => window.addEventListener('pandora:achievement', onAchievement))
onUnmounted(() => window.removeEventListener('pandora:achievement', onAchievement))
</script>

<template>
  <Transition name="ach">
    <div
      v-if="item"
      class="fixed top-1/3 left-1/2 -translate-x-1/2 z-[65] pointer-events-none"
      data-test="achievement-toast"
    >
      <div class="surface-card animate-pop bg-gradient-to-br from-cream-100 to-peach-100 px-6 py-5 text-center shadow-soft-lg max-w-xs">
        <p class="text-3xl mb-1">{{ item.icon || '🏆' }}</p>
        <p class="font-zen text-[10px] tracking-widest text-peach-500">ACHIEVEMENT</p>
        <h3 class="font-display text-lg font-bold text-peach-500 mt-1">{{ item.title }}</h3>
        <p v-if="item.description" class="text-xs text-stone-600 mt-1 font-zen">{{ item.description }}</p>
      </div>
    </div>
  </Transition>
</template>

<style scoped>
.ach-enter-active,
.ach-leave-active {
  transition: opacity 0.4s ease, transform 0.4s ease;
}
.ach-enter-from {
  opacity: 0;
  transform: translateX(-50%) translateY(-12px);
}
.ach-leave-to {
  opacity: 0;
  transform: translateX(-50%) translateY(-12px);
}
</style>
