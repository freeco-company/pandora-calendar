<script setup lang="ts">
defineProps<{ label?: string; size?: 'sm' | 'md' | 'lg' }>()
</script>

<template>
  <div class="flex flex-col items-center justify-center gap-3 py-6">
    <div
      class="border-[3px] border-peach-200 border-t-peach-400 rounded-full animate-spin"
      :class="{
        'w-5 h-5': size === 'sm',
        'w-8 h-8': !size || size === 'md',
        'w-12 h-12': size === 'lg',
      }"
      aria-hidden="true"
    />
    <p v-if="label" class="text-xs text-stone-500 font-zen">{{ label }}</p>
  </div>
</template>
