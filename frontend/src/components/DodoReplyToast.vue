<script setup lang="ts">
/**
 * DodoReplyToast — listens for `pandora:dodo-reply` and shows a soft cream toast.
 * Mirrors XpToast / AchievementToast style; mounted globally in App.vue.
 */
import { onMounted, onUnmounted, ref } from 'vue'

interface Item { id: number; text: string }

const queue = ref<Item[]>([])
let nextId = 0

function onReply(ev: Event) {
  const ce = ev as CustomEvent<{ text: string }>
  const text = ce.detail?.text
  if (!text) return
  const id = ++nextId
  queue.value.push({ id, text })
  setTimeout(() => {
    queue.value = queue.value.filter((q) => q.id !== id)
  }, 4500)
}

onMounted(() => window.addEventListener('pandora:dodo-reply', onReply))
onUnmounted(() => window.removeEventListener('pandora:dodo-reply', onReply))
</script>

<template>
  <div
    class="fixed bottom-24 left-1/2 -translate-x-1/2 z-[60] flex flex-col gap-2 pointer-events-none w-full max-w-sm px-4"
    aria-live="polite"
  >
    <TransitionGroup name="ach" tag="div" class="space-y-2">
      <div
        v-for="item in queue"
        :key="item.id"
        class="rounded-3xl bg-gradient-to-br from-cream-100 to-peach-100 px-4 py-3 shadow-soft-lg flex items-start gap-3 backdrop-blur-sm"
        data-test="dodo-reply-toast"
      >
        <img
          src="/character/anchors/dodo-portrait.png"
          alt=""
          class="w-10 h-10 rounded-full shrink-0 object-cover"
          aria-hidden="true"
          @error="($event.target as HTMLImageElement).style.display = 'none'"
        />
        <div class="flex-1 min-w-0">
          <p class="font-zen text-[10px] tracking-widest text-peach-500/80">{{ 'dodo' }}</p>
          <p class="font-zen text-sm text-stone-700 leading-snug">{{ item.text }}</p>
        </div>
      </div>
    </TransitionGroup>
  </div>
</template>
