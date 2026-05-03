<script setup lang="ts">
import { ref, computed } from 'vue'
import { useTone } from '../../composables/useTone'

const { t } = useTone()

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

const options = computed<Array<{ value: OnboardingGoal; emoji: string; title: string; subtitle: string }>>(() => [
  { value: 'health', emoji: '🌸', title: t('onboarding_goal_health_title'), subtitle: t('onboarding_goal_health_subtitle') },
  { value: 'conceive', emoji: '🌱', title: t('onboarding_goal_conceive_title'), subtitle: t('onboarding_goal_conceive_subtitle') },
  { value: 'avoid', emoji: '🛡', title: t('onboarding_goal_avoid_title'), subtitle: t('onboarding_goal_avoid_subtitle') },
  { value: 'unsure', emoji: '💭', title: t('onboarding_goal_unsure_title'), subtitle: t('onboarding_goal_unsure_subtitle') },
])

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
        {{ t('onboarding_step3_heading') }}
      </h2>
      <p class="font-zen text-sm text-stone-500">
        {{ t('onboarding_step3_help') }}
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
        {{ t('onboarding_step3_btn_back') }}
      </button>
      <button
        type="button"
        :disabled="!selected || loading"
        data-test="onboarding-submit"
        class="py-3 rounded-2xl bg-peach-gradient text-white font-display font-bold text-base shadow-soft transition-all active:scale-[0.99] disabled:opacity-50"
        @click="submit"
      >
        {{ loading ? t('onboarding_step3_btn_loading') : t('onboarding_step3_btn_submit') }}
      </button>
    </div>
  </section>
</template>
