<script setup lang="ts">
/**
 * LoadingSkeleton — 取代散用 Spinner 在「卡片 / 列表 / 文字塊」場景。
 * Spinner 仍可保留給 inline action（button loading）；長內容 placeholder 用 skeleton。
 *
 * variant:
 *   card   — 整張卡的 placeholder（標題 + 兩行 + cta）
 *   line   — 單行 text bar（適合 list）
 *   list   — 數筆 line 排列
 */
const props = withDefaults(
  defineProps<{
    variant?: 'card' | 'line' | 'list'
    rows?: number
  }>(),
  { variant: 'card', rows: 3 },
)
</script>

<template>
  <div v-if="variant === 'card'" class="space-y-3" data-test="skeleton-card">
    <div class="skeleton-block h-3 w-24" />
    <div class="skeleton-block h-6 w-3/4" />
    <div class="skeleton-block h-4 w-full" />
    <div class="skeleton-block h-4 w-5/6" />
    <div class="skeleton-block h-10 w-32 rounded-full" />
  </div>
  <div v-else-if="variant === 'list'" class="space-y-2.5">
    <div v-for="i in rows" :key="i" class="skeleton-block h-12 w-full" />
  </div>
  <div v-else class="skeleton-block h-4 w-full" />
</template>
