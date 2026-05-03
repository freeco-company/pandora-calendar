<script setup lang="ts">
import { ref } from 'vue'

export type OnboardingGoal = 'health' | 'conceive' | 'avoid' | 'unsure'

const props = defineProps<{
  modelValue: OnboardingGoal | null
  loading?: boolean
}>()

const emit = defineEmits<{
  (e: 'update:modelValue', v: OnboardingGoal): void
  (e: 'submit'): void
  (e: 'back'): void
}>()

const selected = ref<OnboardingGoal | null>(props.modelValue)

const options: Array<{ value: OnboardingGoal; emoji: string; title: string; subtitle: string }> = [
  { value: 'health', emoji: '🌸', title: '追蹤健康', subtitle: '了解自己的身體節律' },
  { value: 'conceive', emoji: '🌱', title: '備孕中', subtitle: '掌握排卵與最佳時機' },
  { value: 'avoid', emoji: '🛡', title: '避孕中', subtitle: '搭配避孕措施做提醒' },
  { value: 'unsure', emoji: '💭', title: '還不確定', subtitle: '先記錄看看再說' },
]

function pick(v: OnboardingGoal) {
  selected.value = v
  emit('update:modelValue', v)
}

function submit() {
  if (!selected.value || props.loading) return
  emit('submit')
}
</script>

<template>
  <section class="space-y-5" data-test="onboarding-step-3">
    <div class="space-y-1.5">
      <p class="font-zen text-xs text-stone-500 tracking-widest uppercase">Step 3 / 3</p>
      <h2 class="font-display text-2xl font-bold text-peach-500 leading-snug">
        妳想用月曆做什麼？
      </h2>
      <p class="font-zen text-sm text-stone-500">
        朵朵會根據妳的目標調整提醒與建議。隨時可以改。
      </p>
    </div>

    <div class="space-y-2.5">
      <button
        v-for="opt in options"
        :key="opt.value"
        type="button"
        :data-test="`onboarding-goal-${opt.value}`"
        class="w-full px-4 py-3.5 rounded-2xl border text-left flex items-center gap-3 transition-all active:scale-[0.99]"
        :class="
          selected === opt.value
            ? 'bg-peach-50 border-peach-300 shadow-soft'
            : 'bg-white border-cream-200 hover:bg-cream-50'
        "
        @click="pick(opt.value)"
      >
        <span class="text-2xl">{{ opt.emoji }}</span>
        <div class="flex-1">
          <p class="font-display font-bold text-peach-500 text-sm">{{ opt.title }}</p>
          <p class="font-zen text-[11px] text-stone-500 mt-0.5">{{ opt.subtitle }}</p>
        </div>
        <span
          class="w-5 h-5 rounded-full border-2 flex items-center justify-center"
          :class="selected === opt.value ? 'border-peach-400 bg-peach-400' : 'border-cream-300'"
        >
          <span v-if="selected === opt.value" class="text-white text-[10px]">✓</span>
        </span>
      </button>
    </div>

    <div class="grid grid-cols-2 gap-3">
      <button
        type="button"
        :disabled="loading"
        data-test="onboarding-step-3-back"
        class="py-3 rounded-2xl bg-white border border-cream-200 text-stone-500 font-zen text-sm transition-all active:scale-[0.99] disabled:opacity-50"
        @click="emit('back')"
      >
        ← 上一步
      </button>
      <button
        type="button"
        :disabled="!selected || loading"
        data-test="onboarding-submit"
        class="py-3 rounded-2xl bg-peach-gradient text-white font-display font-bold text-base shadow-soft transition-all active:scale-[0.99] disabled:opacity-50"
        @click="submit"
      >
        {{ loading ? '正在準備…' : '開始使用 ✨' }}
      </button>
    </div>
  </section>
</template>
