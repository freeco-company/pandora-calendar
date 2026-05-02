<script setup lang="ts">
defineProps<{ title?: string; subtitle?: string; icon?: string; showDodo?: boolean }>()
</script>

<template>
  <div class="text-center py-10 px-6 space-y-3">
    <div v-if="showDodo" class="w-28 h-28 mx-auto animate-floaty">
      <img src="/character/anchors/dodo-portrait.png" alt="朵朵" class="w-full h-full object-contain" />
    </div>
    <div v-else-if="icon" class="text-5xl">{{ icon }}</div>
    <h3 v-if="title" class="font-display text-lg font-semibold text-peach-500">{{ title }}</h3>
    <p v-if="subtitle" class="text-sm text-stone-500 leading-relaxed max-w-xs mx-auto">{{ subtitle }}</p>
    <slot />
  </div>
</template>
