<script setup lang="ts">
import { watch } from 'vue'
import { useSfx } from '../../lib/sound'

const props = defineProps<{ open: boolean; title?: string; dismissible?: boolean }>()
const emit = defineEmits<{ close: [] }>()
const sfx = useSfx()

watch(
  () => props.open,
  (now, prev) => {
    if (now && !prev) sfx.play('ui_open')
    if (!now && prev) sfx.play('ui_close')
  },
)

function onBackdrop() {
  if (props.dismissible !== false) emit('close')
}
</script>

<template>
  <Transition name="modal">
    <div
      v-if="open"
      class="fixed inset-0 z-50 flex items-end sm:items-center justify-center px-4 pb-6 sm:pb-0"
      @click.self="onBackdrop"
    >
      <div class="absolute inset-0 bg-black/30 backdrop-blur-sm animate-fadein" />
      <div
        class="relative w-full max-w-md surface-card p-6 animate-pop max-h-[85vh] overflow-y-auto"
      >
        <header v-if="title || $slots.title" class="mb-4">
          <slot name="title">
            <h2 class="font-display text-xl font-bold text-peach-500">{{ title }}</h2>
          </slot>
        </header>
        <slot />
        <footer v-if="$slots.footer" class="mt-5 flex gap-2 justify-end">
          <slot name="footer" />
        </footer>
      </div>
    </div>
  </Transition>
</template>

<style scoped>
.modal-enter-active,
.modal-leave-active {
  transition: opacity 0.25s ease;
}
.modal-enter-from,
.modal-leave-to {
  opacity: 0;
}
</style>
