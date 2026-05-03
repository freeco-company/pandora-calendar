<script setup lang="ts">
/**
 * TodayActionCard — 個人化「今天朵朵建議妳做這 1 件小事」
 *
 * 嵌在 Calendar 主頁 above-the-fold 與 DailyActionView 主視覺。
 * 三狀態：empty (API null) / pending（未完成）/ feedback（完成後問有沒有用）
 */
import { computed, onMounted } from 'vue'
import { useTone } from '../composables/useTone'
import { useDailyAction } from '../composables/useDailyAction'
import type { ActionFeedback, ActionDifficulty } from '../api'

const props = defineProps<{
  /** 是否 compact (Calendar 主頁版) — 預設 false 給 DailyActionView 用 */
  compact?: boolean
}>()

const { t } = useTone()
const {
  todayAction,
  loading,
  error,
  completed,
  feedbackSubmitted,
  loadToday,
  complete,
  submitFeedback,
} = useDailyAction()

onMounted(loadToday)

const phaseLabel = computed(() => {
  const p = todayAction.value?.phase
  if (!p) return ''
  return t(`action_phase_${p}`)
})

const difficultyLabel = computed(() => {
  const d: ActionDifficulty | undefined = todayAction.value?.difficulty
  if (!d) return ''
  return t(`action_difficulty_${d}`)
})

const minutesLabel = computed(() => {
  const m = todayAction.value?.time_minutes
  if (m == null) return ''
  return t('action_minutes', { n: m })
})

async function onComplete() {
  const a = todayAction.value
  if (!a) return
  try {
    await complete(a.id)
  } catch {
    /* error already on state */
  }
}

async function onFeedback(fb: ActionFeedback) {
  const a = todayAction.value
  if (!a) return
  try {
    await submitFeedback(a.id, fb)
  } catch {
    /* swallow */
  }
}
</script>

<template>
  <div class="rounded-3xl shadow-soft bg-gradient-to-br from-cream-50 to-peach-50 p-5" data-test="today-action-card">
    <!-- Loading skeleton -->
    <div v-if="loading" class="space-y-3 animate-pulse">
      <div class="h-3 w-24 bg-stone-200 rounded" />
      <div class="h-6 w-3/4 bg-stone-200 rounded" />
      <div class="h-4 w-full bg-stone-200 rounded" />
      <div class="h-10 w-32 bg-stone-200 rounded-full" />
    </div>

    <!-- Error -->
    <div v-else-if="error" class="text-center py-4">
      <p class="text-sm text-stone-500 font-zen mb-3">{{ t('action_error_load') }}</p>
      <button
        type="button"
        @click="loadToday"
        class="text-peach-500 text-sm font-zen underline"
      >
        {{ t('btn_retry') }}
      </button>
    </div>

    <!-- Empty: backend returned null -->
    <div v-else-if="!todayAction" class="text-center py-4" data-test="today-action-empty">
      <p class="text-3xl mb-2">🌱</p>
      <p class="font-zen text-sm text-stone-500 leading-relaxed">{{ t('action_empty_state') }}</p>
    </div>

    <!-- Action: pending or feedback -->
    <div v-else>
      <!-- Header -->
      <p class="font-zen text-[11px] uppercase tracking-widest text-peach-500/80">
        {{ t('action_today_heading') }}
      </p>

      <!-- Title -->
      <h2
        class="font-display text-xl font-bold text-stone-700 mt-1.5 leading-tight"
        :class="completed && 'line-through text-stone-400'"
        data-test="today-action-title"
      >
        {{ todayAction.title }}
      </h2>

      <!-- Body -->
      <p
        v-if="!props.compact"
        class="font-zen text-sm text-stone-600 mt-2 leading-relaxed"
        data-test="today-action-body"
      >
        {{ todayAction.body }}
      </p>

      <!-- Meta row -->
      <div class="flex flex-wrap gap-2 mt-3 text-[11px] font-zen text-stone-500">
        <span v-if="minutesLabel" class="bg-white/70 px-2.5 py-1 rounded-full">{{ minutesLabel }}</span>
        <span v-if="phaseLabel" class="bg-white/70 px-2.5 py-1 rounded-full">{{ phaseLabel }}</span>
        <span v-if="difficultyLabel" class="bg-white/70 px-2.5 py-1 rounded-full">{{ difficultyLabel }}</span>
      </div>

      <!-- Pending state CTA -->
      <div v-if="!completed" class="mt-4">
        <button
          type="button"
          @click="onComplete"
          class="w-full sm:w-auto px-6 py-3 rounded-full bg-peach-gradient text-white font-display font-bold text-base shadow-soft active:scale-95 transition-transform"
          data-test="today-action-complete-btn"
        >
          {{ t('action_btn_done') }}
        </button>
      </div>

      <!-- Completed but no feedback yet → ask -->
      <div v-else-if="!feedbackSubmitted" class="mt-4" data-test="today-action-feedback">
        <p class="font-zen text-sm text-stone-600 mb-2.5">{{ t('action_feedback_prompt') }}</p>
        <div class="flex gap-2">
          <button
            type="button"
            @click="onFeedback('helpful')"
            class="flex-1 py-2.5 rounded-2xl bg-white shadow-soft active:scale-95 transition-transform flex flex-col items-center gap-0.5"
            data-test="today-action-fb-helpful"
          >
            <span class="text-xl">💛</span>
            <span class="font-zen text-[11px] text-stone-600">{{ t('action_feedback_helpful') }}</span>
          </button>
          <button
            type="button"
            @click="onFeedback('neutral')"
            class="flex-1 py-2.5 rounded-2xl bg-white shadow-soft active:scale-95 transition-transform flex flex-col items-center gap-0.5"
            data-test="today-action-fb-neutral"
          >
            <span class="text-xl">😐</span>
            <span class="font-zen text-[11px] text-stone-600">{{ t('action_feedback_neutral') }}</span>
          </button>
          <button
            type="button"
            @click="onFeedback('unhelpful')"
            class="flex-1 py-2.5 rounded-2xl bg-white shadow-soft active:scale-95 transition-transform flex flex-col items-center gap-0.5"
            data-test="today-action-fb-unhelpful"
          >
            <span class="text-xl">🌧️</span>
            <span class="font-zen text-[11px] text-stone-600">{{ t('action_feedback_unhelpful') }}</span>
          </button>
        </div>
      </div>

      <!-- Feedback already in -->
      <div v-else class="mt-4 flex items-center gap-2 text-sage-500 font-zen text-sm" data-test="today-action-thanks">
        <span aria-hidden="true">✓</span>
        <span>{{ t('action_dodo_thanks') }}</span>
      </div>
    </div>
  </div>
</template>
