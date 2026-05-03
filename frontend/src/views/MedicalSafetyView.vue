<script setup lang="ts">
import { computed, ref } from 'vue'
import { useRouter } from 'vue-router'
import {
  MedicalSafetyApi,
  type MedicalContext,
  type MedicalEvaluation,
  type MedicalUrgency,
} from '../api'
import Card from '../components/ui/Card.vue'
import Button from '../components/ui/Button.vue'
import Spinner from '../components/ui/Spinner.vue'
import { useSfx } from '../lib/sound'
import { useTone } from '../composables/useTone'

const router = useRouter()
const sfx = useSfx()
const { t } = useTone()

interface ContextOption {
  value: MedicalContext
  label: string
  emoji: string
  hint: string
  needsDaysLate?: boolean
}

const CONTEXTS = computed<ContextOption[]>(() => [
  {
    value: 'period_late',
    label: t('medical_safety_ctx_period_late_label'),
    emoji: '📅',
    hint: t('medical_safety_ctx_period_late_hint'),
    needsDaysLate: true,
  },
  { value: 'heavy_flow', label: t('medical_safety_ctx_heavy_flow_label'), emoji: '🩸', hint: t('medical_safety_ctx_heavy_flow_hint') },
  { value: 'severe_cramps', label: t('medical_safety_ctx_severe_cramps_label'), emoji: '😖', hint: t('medical_safety_ctx_severe_cramps_hint') },
  { value: 'irregular', label: t('medical_safety_ctx_irregular_label'), emoji: '🌗', hint: t('medical_safety_ctx_irregular_hint') },
  { value: 'spotting', label: t('medical_safety_ctx_spotting_label'), emoji: '💧', hint: t('medical_safety_ctx_spotting_hint') },
])

const URGENCY_THEME = computed<Record<MedicalUrgency, { bg: string; ring: string; emoji: string; label: string }>>(() => ({
  low: {
    bg: 'bg-peach-50 border-peach-200',
    ring: 'text-peach-500',
    emoji: '🌷',
    label: t('medical_safety_urgency_low'),
  },
  medium: {
    bg: 'bg-amber-50 border-amber-200',
    ring: 'text-amber-600',
    emoji: '🌼',
    label: t('medical_safety_urgency_medium'),
  },
  high: {
    bg: 'bg-rose-50 border-rose-200',
    ring: 'text-rose-600',
    emoji: '🚨',
    label: t('medical_safety_urgency_high'),
  },
}))

const FALLBACK_DOCTOR_URL = 'https://www.mohw.gov.tw/'

const selected = ref<ContextOption | null>(null)
const daysLate = ref(3)
const loading = ref(false)
const error = ref<string | null>(null)
const result = ref<MedicalEvaluation | null>(null)

const findDoctorUrl = computed(() => result.value?.find_doctor_url || FALLBACK_DOCTOR_URL)

function pick(opt: ContextOption) {
  sfx.play('ui_tap')
  selected.value = opt
  result.value = null
  error.value = null
  if (!opt.needsDaysLate) daysLate.value = 0
  else daysLate.value = 3
}

async function evaluate() {
  if (!selected.value) return
  loading.value = true
  error.value = null
  result.value = null
  try {
    const params: { context: MedicalContext; days_late?: number } = {
      context: selected.value.value,
    }
    if (selected.value.needsDaysLate) params.days_late = daysLate.value
    const res = await MedicalSafetyApi.evaluate(params)
    result.value = res.data.data
    sfx.play('correct')
  } catch {
    error.value = t('medical_safety_eval_failed')
    sfx.play('wrong')
  } finally {
    loading.value = false
  }
}

function back() {
  sfx.play('ui_close')
  result.value = null
  selected.value = null
}
</script>

<template>
  <div class="px-5 pt-10 pb-10 max-w-md mx-auto space-y-5">
    <header class="space-y-1">
      <button class="text-xs text-stone-500 font-zen mb-2" @click="router.back()">{{ t('common_back') }}</button>
      <h1 class="font-display text-2xl font-bold text-peach-500">{{ t('medical_safety_title') }}</h1>
      <p class="font-zen text-xs text-stone-500 leading-relaxed">
        {{ t('medical_safety_subtitle') }}
      </p>
    </header>

    <!-- Step 1: 選 context -->
    <Card v-if="!result" tone="plain" class="space-y-3">
      <h2 class="font-display font-bold text-peach-500 text-sm">{{ t('medical_safety_q') }}</h2>
      <div class="space-y-2">
        <button
          v-for="opt in CONTEXTS"
          :key="opt.value"
          class="w-full text-left px-4 py-3 rounded-2xl border transition-all flex items-center gap-3"
          :class="
            selected?.value === opt.value
              ? 'border-peach-400 bg-peach-50 shadow-soft'
              : 'border-cream-200 bg-white hover:bg-peach-50/50'
          "
          :aria-pressed="selected?.value === opt.value"
          @click="pick(opt)"
        >
          <span class="text-2xl shrink-0">{{ opt.emoji }}</span>
          <div class="flex-1 min-w-0">
            <p class="font-zen text-sm text-stone-700 font-medium">{{ opt.label }}</p>
            <p class="font-zen text-[11px] text-stone-500 mt-0.5">{{ opt.hint }}</p>
          </div>
        </button>
      </div>
    </Card>

    <!-- Step 2: 條件參數（days_late slider） -->
    <Card v-if="selected?.needsDaysLate && !result" tone="cream" class="space-y-3">
      <label class="block">
        <span class="font-display font-bold text-peach-500 text-sm">{{ t('medical_safety_days_late_label') }}</span>
      </label>
      <div class="flex items-center gap-3">
        <input
          v-model.number="daysLate"
          type="range"
          min="1"
          max="60"
          step="1"
          class="flex-1 accent-peach-400"
          :aria-label="t('medical_safety_days_late_aria')"
        />
        <div class="px-3 py-2 rounded-2xl bg-white shadow-soft min-w-[72px] text-center">
          <p class="font-display font-bold text-peach-500 text-lg leading-none">{{ daysLate }}</p>
          <p class="font-zen text-[10px] text-stone-400 mt-0.5">{{ t('medical_safety_days_unit') }}</p>
        </div>
      </div>
    </Card>

    <Spinner v-if="loading" :label="t('medical_safety_thinking')" />

    <p v-if="error" class="text-center font-zen text-sm text-sakura-500">{{ error }}</p>

    <Button
      v-if="selected && !result && !loading"
      full
      size="lg"
      @click="evaluate"
    >
      {{ t('medical_safety_evaluate_btn') }}
    </Button>

    <!-- Step 3: 結果 -->
    <Card
      v-if="result"
      tone="plain"
      :padded="false"
      class="overflow-hidden border-2"
      :class="URGENCY_THEME[result.urgency].bg"
    >
      <div class="px-5 py-4 space-y-3">
        <div class="flex items-center gap-3">
          <div class="text-4xl">{{ URGENCY_THEME[result.urgency].emoji }}</div>
          <div>
            <p class="font-zen text-[11px] text-stone-500 tracking-wide uppercase">{{ t('medical_safety_advice_eyebrow') }}</p>
            <p class="font-display font-bold text-base" :class="URGENCY_THEME[result.urgency].ring">
              {{ URGENCY_THEME[result.urgency].label }}
            </p>
          </div>
        </div>

        <p class="font-zen text-sm text-stone-700 leading-relaxed">{{ result.message }}</p>

        <div class="bg-white/70 rounded-2xl p-3 space-y-1">
          <p class="font-zen text-[11px] text-stone-500">{{ t('medical_safety_action_label') }}</p>
          <p class="font-zen text-sm text-stone-700">{{ result.action }}</p>
        </div>

        <div v-if="result.suggest_test" class="bg-white/70 rounded-2xl p-3 flex items-start gap-3">
          <span class="text-2xl">🧪</span>
          <div>
            <p class="font-zen text-sm text-stone-700 font-medium">{{ t('medical_safety_test_label') }}</p>
            <p class="font-zen text-[11px] text-stone-500 mt-0.5">
              {{ t('medical_safety_test_hint') }}
            </p>
          </div>
        </div>
      </div>

      <div class="px-5 pb-5 pt-1 space-y-2">
        <a
          :href="findDoctorUrl"
          target="_blank"
          rel="noopener noreferrer"
          class="block w-full text-center py-3 rounded-full bg-white border border-peach-200 font-zen text-sm text-peach-500 hover:bg-peach-50 transition-all"
          @click="sfx.play('ui_open')"
        >
          {{ t('medical_safety_doctor_link') }}
        </a>
        <Button variant="ghost" full @click="back">{{ t('medical_safety_change_q') }}</Button>
      </div>
    </Card>

    <!-- Disclaimer 大字 -->
    <Card tone="cream" class="text-center space-y-2">
      <p class="font-display font-bold text-peach-500 text-base leading-relaxed">
        {{ t('medical_safety_disclaimer_title') }}
      </p>
      <p class="font-zen text-sm text-stone-600 leading-relaxed">
        {{ t('medical_safety_disclaimer_blurb') }}
      </p>
    </Card>
  </div>
</template>
