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
    <div class="text-center space-y-2">
      <div class="text-5xl" aria-hidden="true">💛</div>
      <h2 class="font-display text-2xl font-bold text-peach-500 leading-snug">
        {{ t('onboarding_step3_heading') }}
      </h2>
      <p class="font-zen text-sm text-stone-500 leading-relaxed">
        {{ t('onboarding_step3_help') }}
      </p>
    </div>

    <div class="space-y-2.5">
      <button
        v-for="opt in options"
        :key="opt.value"
        type="button"
        :data-test="`onboarding-goal-${opt.value}`"
        :aria-pressed="selected === opt.value"
        class="w-full px-4 py-4 rounded-2xl border-2 text-left flex items-center gap-3 transition-all active:scale-[0.99]"
        :class="
          selected === opt.value
            ? 'bg-peach-50 border-peach-400 shadow-soft scale-[1.01]'
            : 'bg-white border-cream-200 hover:bg-cream-50 hover:border-cream-300'
        "
        @click="pick(opt.value)"
      >
        <span class="text-3xl shrink-0">{{ opt.emoji }}</span>
        <div class="flex-1 min-w-0">
          <p class="font-display font-bold text-peach-500 text-base">{{ opt.title }}</p>
          <p class="font-zen text-[12px] text-stone-500 mt-0.5 leading-relaxed">{{ opt.subtitle }}</p>
        </div>
        <span
          class="w-6 h-6 rounded-full border-2 flex items-center justify-center shrink-0 transition-all"
          :class="selected === opt.value ? 'border-peach-400 bg-peach-400' : 'border-cream-300'"
        >
          <span v-if="selected === opt.value" class="text-white text-xs">✓</span>
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
