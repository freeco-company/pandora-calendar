<script setup lang="ts">
import { ref, computed } from 'vue'
import { useTone } from '../../composables/useTone'

const { t } = useTone()

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
  if (length.value < 24) return t('onboarding_step2_hint_short')
  if (length.value > 35) return t('onboarding_step2_hint_long')
  return t('onboarding_step2_hint_normal')
})

function next() {
  emit('update:modelValue', length.value)
  emit('next')
}
</script>

<template>
  <section class="space-y-5" data-test="onboarding-step-2">
    <div class="text-center space-y-2">
      <div class="text-5xl" aria-hidden="true">🌙</div>
      <h2 class="font-display text-2xl font-bold text-peach-500 leading-snug">
        {{ t('onboarding_step2_heading') }}
      </h2>
      <p class="font-zen text-sm text-stone-500 leading-relaxed">
        {{ t('onboarding_step2_help_long') }}
      </p>
    </div>

    <div class="bg-cream-50 rounded-3xl p-6 text-center space-y-4">
      <div>
        <p class="font-display font-bold text-peach-500 text-6xl leading-none tracking-tight">
          {{ length }}
        </p>
        <p class="font-zen text-sm text-stone-400 mt-1">{{ t('onboarding_step2_unit_days') }}</p>
      </div>
      <input
        v-model.number="length"
        type="range"
        min="21"
        max="45"
        step="1"
        data-test="onboarding-cycle-length"
        :aria-valuemin="21"
        :aria-valuemax="45"
        :aria-valuenow="length"
        class="w-full accent-peach-400 h-2"
      />
      <div class="flex justify-between text-[11px] font-zen text-stone-400 px-1">
        <span>21</span>
        <span>28</span>
        <span>35</span>
        <span>45</span>
      </div>
      <p class="font-zen text-[13px] text-peach-500 leading-relaxed">{{ hint }}</p>
    </div>

    <div class="grid grid-cols-2 gap-3">
      <button
        type="button"
        data-test="onboarding-step-2-back"
        class="py-3 rounded-2xl bg-white border border-cream-200 text-stone-500 font-zen text-sm transition-all active:scale-[0.99]"
        @click="emit('back')"
      >
        {{ t('onboarding_step2_btn_back') }}
      </button>
      <button
        type="button"
        data-test="onboarding-step-2-next"
        class="py-3 rounded-2xl bg-peach-gradient text-white font-display font-bold text-base shadow-soft transition-all active:scale-[0.99]"
        @click="next"
      >
        {{ t('onboarding_step2_btn_next') }}
      </button>
    </div>
  </section>
</template>
