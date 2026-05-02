<script setup lang="ts">
import { computed } from 'vue'
import { useSfx } from '../../lib/sound'

type Variant = 'primary' | 'secondary' | 'ghost' | 'danger'
type Size = 'sm' | 'md' | 'lg'

const props = withDefaults(
  defineProps<{
    variant?: Variant
    size?: Size
    disabled?: boolean
    loading?: boolean
    full?: boolean
    sfx?: string | null // override; default 'ui_tap'
  }>(),
  { variant: 'primary', size: 'md', disabled: false, loading: false, full: false, sfx: 'ui_tap' },
)

const emit = defineEmits<{ click: [ev: MouseEvent] }>()
const sfx = useSfx()

const cls = computed(() => {
  const base =
    'relative inline-flex items-center justify-center gap-2 font-zen font-medium ' +
    'rounded-full transition-all duration-200 select-none ' +
    'active:scale-[0.97] disabled:opacity-50 disabled:cursor-not-allowed disabled:active:scale-100 ' +
    'focus:outline-none focus-visible:ring-4 focus-visible:ring-peach-200/60'

  const sz: Record<Size, string> = {
    sm: 'px-4 py-2 text-xs',
    md: 'px-6 py-3 text-sm',
    lg: 'px-7 py-3.5 text-base',
  }

  const v: Record<Variant, string> = {
    primary:
      'text-white bg-gradient-to-br from-peach-300 to-peach-500 shadow-soft ' +
      'hover:shadow-soft-lg hover:from-peach-400 hover:to-peach-500',
    secondary:
      'text-peach-500 bg-white/90 border border-peach-200 shadow-soft ' +
      'hover:bg-peach-50',
    ghost:
      'text-peach-500 bg-transparent hover:bg-peach-50/80',
    danger:
      'text-white bg-gradient-to-br from-sakura-400 to-sakura-500 shadow-soft ' +
      'hover:shadow-soft-lg',
  }

  return [base, sz[props.size], v[props.variant], props.full ? 'w-full' : ''].join(' ')
})

function onClick(ev: MouseEvent) {
  if (props.disabled || props.loading) return
  if (props.sfx) sfx.play(props.sfx as any)
  emit('click', ev)
}
</script>

<template>
  <button :class="cls" :disabled="disabled || loading" @click="onClick">
    <span
      v-if="loading"
      class="inline-block w-4 h-4 border-2 border-current border-t-transparent rounded-full animate-spin"
      aria-hidden="true"
    />
    <slot v-else />
  </button>
</template>
