<script setup lang="ts">
/**
 * Heading — 統一 h1/h2/h3 的 display font + tone。
 * Spec：見 style.css `.heading-1/2/3 + .eyebrow`。
 *
 * 用法：
 *   <Heading level="1" eyebrow="今天">妳好</Heading>
 */
import { computed } from 'vue'

const props = withDefaults(
  defineProps<{
    level?: '1' | '2' | '3'
    eyebrow?: string
    align?: 'left' | 'center'
  }>(),
  { level: '2', align: 'left' },
)

const tag = computed(() => `h${props.level}`)
const cls = computed(() => {
  const base = props.level === '1' ? 'heading-1' : props.level === '3' ? 'heading-3' : 'heading-2'
  return [base, props.align === 'center' ? 'text-center' : ''].join(' ')
})
</script>

<template>
  <div :class="align === 'center' ? 'text-center' : ''">
    <p v-if="eyebrow" class="eyebrow mb-1" :class="align === 'center' ? 'text-center' : ''">{{ eyebrow }}</p>
    <component :is="tag" :class="cls"><slot /></component>
  </div>
</template>
