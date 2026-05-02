<script setup lang="ts">
defineProps<{ icon?: string; title?: string; subtitle?: string; tone?: 'peach' | 'sakura' | 'sage' | 'lavender' }>()
</script>

<template>
  <div
    class="surface-card px-4 py-3 flex items-center gap-3 min-w-[240px] max-w-[320px] animate-slidein"
    :class="{
      'bg-peach-50/95': tone === 'peach' || !tone,
      'bg-sakura-50/95': tone === 'sakura',
      'bg-sage-50/95': tone === 'sage',
      'bg-lavender-50/95': tone === 'lavender',
    }"
  >
    <span v-if="icon" class="text-2xl shrink-0">{{ icon }}</span>
    <div class="min-w-0">
      <p class="text-sm font-zen font-semibold text-peach-500 truncate">{{ title }}</p>
      <p v-if="subtitle" class="text-xs text-stone-600 mt-0.5 truncate">{{ subtitle }}</p>
    </div>
  </div>
</template>
