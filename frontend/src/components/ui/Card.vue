<script setup lang="ts">
import { computed } from 'vue'

type Tone = 'cream' | 'peach' | 'sakura' | 'lavender' | 'sage' | 'plain'

const props = withDefaults(
  defineProps<{
    tone?: Tone
    padded?: boolean
    interactive?: boolean
  }>(),
  { tone: 'plain', padded: true, interactive: false },
)

const cls = computed(() => {
  const toneMap: Record<Tone, string> = {
    plain: 'bg-white/92 backdrop-blur',
    cream: 'bg-cream-50/95 backdrop-blur',
    peach: 'bg-peach-gradient',
    sakura: 'bg-sakura-gradient',
    lavender: 'bg-lavender-gradient',
    sage: 'bg-sage-50/95 backdrop-blur',
  }
  return [
    'rounded-3xl shadow-soft',
    toneMap[props.tone],
    props.padded ? 'p-5' : '',
    props.interactive ? 'transition-all hover:shadow-soft-lg active:scale-[0.99] cursor-pointer' : '',
  ].join(' ')
})
</script>

<template>
  <div :class="cls"><slot /></div>
</template>
