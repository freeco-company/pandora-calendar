<script setup lang="ts">
import { onMounted, onUnmounted, ref } from 'vue'
import { useSfx } from '../lib/sound'
import type { XpDetail } from '../lib/gamification'

const sfx = useSfx()
const queue = ref<(XpDetail & { id: number })[]>([])
let nextId = 0

function onXp(ev: Event) {
  const ce = ev as CustomEvent<XpDetail>
  if (!ce.detail) return
  const id = ++nextId
  queue.value.push({ ...ce.detail, id })
  sfx.play('xp')
  setTimeout(() => {
    queue.value = queue.value.filter((q) => q.id !== id)
  }, 3000)
}

onMounted(() => window.addEventListener('pandora:xp', onXp))
onUnmounted(() => window.removeEventListener('pandora:xp', onXp))
</script>

<template>
  <div
    class="fixed top-4 right-4 z-[60] flex flex-col gap-2 pointer-events-none"
    style="padding-top: env(safe-area-inset-top)"
    aria-live="polite"
  >
    <div
      v-for="item in queue"
      :key="item.id"
      class="surface-card animate-slidein px-4 py-3 flex items-center gap-3 min-w-[200px] bg-peach-gradient text-white shadow-soft-lg"
      data-test="xp-toast"
    >
      <span class="text-2xl animate-sparkle">✨</span>
      <div class="leading-tight">
        <p class="font-display font-bold text-lg">+{{ item.amount }} XP</p>
        <p v-if="item.reason" class="text-xs opacity-90 font-zen">{{ item.reason }}</p>
      </div>
    </div>
  </div>
</template>
