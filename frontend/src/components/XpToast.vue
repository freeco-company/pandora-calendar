<script setup lang="ts">
import { onMounted, onUnmounted, ref } from 'vue'
import { useSfx } from '../lib/sound'
import Icon from './icons/Icon.vue'
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
      class="animate-slidein px-4 py-3 flex items-center gap-3 min-w-[200px] bg-gradient-to-br from-peach-500 to-sakura-500 text-white shadow-lg rounded-3xl ring-2 ring-white/40"
      style="text-shadow: 0 1px 3px rgba(80,40,30,0.35);"
      data-test="xp-toast"
    >
      <Icon name="sparkle" size="lg" animated decorative class="text-white drop-shadow" />
      <div class="leading-tight">
        <p class="font-zen font-bold text-lg">+{{ item.amount }} XP</p>
        <p v-if="item.reason" class="text-xs text-white/95 font-zen">{{ item.reason }}</p>
      </div>
    </div>
  </div>
</template>
