<script setup lang="ts">
/**
 * ErrorBanner — 統一 4xx / 5xx / network 錯誤呈現。
 *
 * 設計：
 *   - icon + title + body + 可選 retry
 *   - tone: error (sakura) / warning (peach)
 *   - 不只用紅色 — 加 ⚠ icon 與粗體文字（A11y）
 */
const props = withDefaults(
  defineProps<{
    title?: string
    message?: string
    tone?: 'error' | 'warning'
    retryLabel?: string
  }>(),
  { tone: 'error' },
)
defineEmits<{ retry: [] }>()
</script>

<template>
  <div
    class="rounded-2xl border px-4 py-3 flex items-start gap-3 font-zen"
    :class="tone === 'error'
      ? 'bg-sakura-50/95 border-sakura-200 text-sakura-500'
      : 'bg-peach-50/95 border-peach-200 text-peach-500'"
    role="alert"
    data-test="error-banner"
  >
    <span class="text-lg shrink-0" aria-hidden="true">{{ tone === 'error' ? '⚠️' : '⏳' }}</span>
    <div class="flex-1 min-w-0">
      <p v-if="title" class="font-semibold text-sm leading-snug">{{ title }}</p>
      <p class="text-stone-600 text-[13px] leading-relaxed mt-0.5">
        <slot>{{ message }}</slot>
      </p>
      <button
        v-if="retryLabel"
        type="button"
        class="mt-2 text-[12px] underline underline-offset-2 active:opacity-60"
        @click="$emit('retry')"
      >
        {{ retryLabel }}
      </button>
    </div>
  </div>
</template>
