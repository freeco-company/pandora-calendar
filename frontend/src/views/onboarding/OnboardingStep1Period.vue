<script setup lang="ts">
import { ref, computed } from 'vue'
import { useTone } from '../../composables/useTone'

const { t } = useTone()

const props = defineProps<{
  modelValue: string | null
  unsure: boolean
}>()

const emit = defineEmits<{
  (e: 'update:modelValue', v: string | null): void
  (e: 'update:unsure', v: boolean): void
  (e: 'next'): void
}>()

const today = new Date().toISOString().slice(0, 10)
const localDate = ref<string>(props.modelValue ?? '')
const localUnsure = ref<boolean>(props.unsure)

const canContinue = computed(() => localUnsure.value || !!localDate.value)

function next() {
  if (!canContinue.value) return
  emit('update:modelValue', localUnsure.value ? null : localDate.value)
  emit('update:unsure', localUnsure.value)
  emit('next')
}

function pickUnsure() {
  localUnsure.value = true
  localDate.value = ''
}

function onDateInput(e: Event) {
  localDate.value = (e.target as HTMLInputElement).value
  localUnsure.value = false
}
</script>

<template>
  <section class="space-y-5" data-test="onboarding-step-1">
    <div class="space-y-1.5">
      <p class="font-zen text-xs text-stone-500 tracking-widest uppercase">Step 1 / 3</p>
      <h2 class="font-display text-2xl font-bold text-peach-500 leading-snug">
        {{ t('onboarding_step1_heading') }}
      </h2>
      <p class="font-zen text-sm text-stone-500">
        {{ t('onboarding_step1_help') }}
      </p>
    </div>

    <label class="block">
      <span class="font-zen text-xs text-stone-500">{{ t('onboarding_step1_date_label') }}</span>
      <input
        :value="localDate"
        type="date"
        :max="today"
        data-test="onboarding-last-period"
        class="mt-1 w-full px-4 py-3 rounded-2xl border bg-cream-50 focus:outline-none focus:bg-white text-base font-zen transition-colors"
        :class="localUnsure ? 'border-cream-200 opacity-60' : 'border-peach-300'"
        @input="onDateInput"
      />
    </label>

    <button
      type="button"
      data-test="onboarding-unsure"
      class="w-full py-2.5 rounded-2xl border font-zen text-sm transition-all active:scale-[0.99]"
      :class="
        localUnsure
          ? 'bg-peach-gradient text-white border-transparent shadow-soft'
          : 'bg-white border-cream-200 text-stone-500 hover:bg-cream-50'
      "
      @click="pickUnsure"
    >
      {{ t('onboarding_step1_btn_unsure') }}
    </button>

    <button
      type="button"
      data-test="onboarding-step-1-next"
      :disabled="!canContinue"
      class="w-full py-3 rounded-2xl bg-peach-gradient text-white font-display font-bold text-base shadow-soft disabled:opacity-50 transition-all active:scale-[0.99]"
      @click="next"
    >
      {{ t('onboarding_step1_btn_next') }}
    </button>
  </section>
</template>
