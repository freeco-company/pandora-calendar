<script setup lang="ts">
import { ref, computed } from 'vue'

const props = defineProps<{
  modelValue: number
}>()

const emit = defineEmits<{
  (e: 'update:modelValue', v: number): void
  (e: 'next'): void
  (e: 'back'): void
}>()

const length = ref<number>(props.modelValue || 28)

const hint = computed(() => {
  if (length.value < 24) return '比平均偏短，朵朵會貼近妳的節奏。'
  if (length.value > 35) return '比平均偏長，朵朵會貼近妳的節奏。'
  return '常見區間，週期穩定的朋友多在這裡。'
})

function next() {
  emit('update:modelValue', length.value)
  emit('next')
}
</script>

<template>
  <section class="space-y-5" data-test="onboarding-step-2">
    <div class="space-y-1.5">
      <p class="font-zen text-xs text-stone-500 tracking-widest uppercase">Step 2 / 3</p>
      <h2 class="font-display text-2xl font-bold text-peach-500 leading-snug">
        妳的週期通常多長？
      </h2>
      <p class="font-zen text-sm text-stone-500">
        從月經第一天到下一次月經第一天的天數。不確定的話 28 天是常見值。
      </p>
    </div>

    <div class="bg-cream-50 rounded-3xl p-5 text-center space-y-3">
      <p class="font-display font-bold text-peach-500 text-5xl leading-none">
        {{ length }}<span class="text-base text-stone-400 ml-1 font-zen">天</span>
      </p>
      <input
        v-model.number="length"
        type="range"
        min="21"
        max="45"
        step="1"
        data-test="onboarding-cycle-length"
        class="w-full accent-peach-400"
      />
      <div class="flex justify-between text-[11px] font-zen text-stone-400 px-1">
        <span>21</span>
        <span>28</span>
        <span>35</span>
        <span>45</span>
      </div>
      <p class="font-zen text-[12px] text-stone-500">{{ hint }}</p>
    </div>

    <div class="grid grid-cols-2 gap-3">
      <button
        type="button"
        data-test="onboarding-step-2-back"
        class="py-3 rounded-2xl bg-white border border-cream-200 text-stone-500 font-zen text-sm transition-all active:scale-[0.99]"
        @click="emit('back')"
      >
        ← 上一步
      </button>
      <button
        type="button"
        data-test="onboarding-step-2-next"
        class="py-3 rounded-2xl bg-peach-gradient text-white font-display font-bold text-base shadow-soft transition-all active:scale-[0.99]"
        @click="next"
      >
        下一步 →
      </button>
    </div>
  </section>
</template>
