<script setup lang="ts">
/**
 * TodayActionCard — 個人化「今天朵朵建議妳做這 1 件小事」
 *
 * 嵌在 Calendar 主頁 above-the-fold 與 DailyActionView 主視覺。
 * 三狀態：empty (API null) / pending（未完成）/ feedback（完成後問有沒有用）
 */
import { computed, onMounted, ref } from 'vue'
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

// difficulty → 燈號 emoji + 配色
const difficultyDot = computed(() => {
  const d: ActionDifficulty | undefined = todayAction.value?.difficulty
  if (d === 'easy') return { emoji: '🟢', color: 'text-sage-500' }
  if (d === 'medium') return { emoji: '🟡', color: 'text-peach-500' }
  if (d === 'hard') return { emoji: '🔴', color: 'text-sakura-500' }
  return { emoji: '', color: '' }
})

const phaseEmoji = computed(() => {
  const p = todayAction.value?.phase
  if (p === 'menstrual') return '🌸'
  if (p === 'follicular') return '🌱'
  if (p === 'ovulation') return '☀️'
  if (p === 'luteal') return '🌙'
  return '🌙'
})

// optional 給朵朵的 textarea
const feedbackNote = ref('')
const tappedFb = ref<ActionFeedback | null>(null)

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
  tappedFb.value = fb
  try {
    await submitFeedback(a.id, fb)
  } catch {
    /* swallow */
  }
}
</script>

<template>
  <div class="relative rounded-3xl shadow-soft bg-gradient-to-br from-cream-50 via-peach-50 to-sakura-50 p-5 overflow-hidden" data-test="today-action-card">
    <!-- decorative blur -->
    <div class="absolute -top-12 -right-12 w-32 h-32 bg-peach-200/30 rounded-full blur-2xl pointer-events-none" aria-hidden="true" />

    <!-- Loading skeleton -->
    <div v-if="loading" class="space-y-3 animate-pulse relative">
      <div class="h-3 w-24 bg-stone-200 rounded" />
      <div class="h-6 w-3/4 bg-stone-200 rounded" />
      <div class="h-4 w-full bg-stone-200 rounded" />
      <div class="h-10 w-full bg-stone-200 rounded-2xl" />
    </div>

    <!-- Error -->
    <div v-else-if="error" class="text-center py-4 relative">
      <p class="text-sm text-stone-500 font-zen mb-3">{{ t('action_error_load') }}</p>
      <button
        type="button"
        @click="loadToday"
        class="text-peach-500 text-sm font-zen underline"
      >
        {{ t('btn_retry') }}
      </button>
    </div>

    <!-- Empty -->
    <div v-else-if="!todayAction" class="text-center py-6 relative" data-test="today-action-empty">
      <p class="text-4xl mb-2">🌱</p>
      <p class="font-display font-bold text-stone-700 text-base mb-1">
        {{ t('action_today_heading') }}
      </p>
      <p class="font-zen text-sm text-stone-500 leading-relaxed max-w-[260px] mx-auto">
        {{ t('action_empty_state') }}
      </p>
    </div>

    <!-- Action：pending or feedback -->
    <div v-else class="relative">
      <!-- Header：朵朵 emoji + label -->
      <div class="flex items-center gap-2 mb-2">
        <span class="text-lg" aria-hidden="true">🐣</span>
        <p class="font-zen text-[11px] tracking-[0.2em] text-peach-500/80 uppercase">
          {{ t('action_today_heading') }}
        </p>
      </div>

      <!-- Title -->
      <h2
        class="font-display text-[19px] font-bold text-stone-700 leading-snug"
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

      <!-- Meta line：⏱ N 分 · 🌙 phase · 燈號 difficulty -->
      <div class="flex flex-wrap items-center gap-x-3 gap-y-1 mt-3 text-[12px] font-zen">
        <span v-if="minutesLabel" class="inline-flex items-center gap-1 text-stone-600">
          <span aria-hidden="true">⏱</span> {{ minutesLabel }}
        </span>
        <span v-if="phaseLabel" class="inline-flex items-center gap-1 text-stone-600">
          <span aria-hidden="true">{{ phaseEmoji }}</span> {{ phaseLabel }}
        </span>
        <span v-if="difficultyLabel" class="inline-flex items-center gap-1" :class="difficultyDot.color">
          <span aria-hidden="true">{{ difficultyDot.emoji }}</span> {{ difficultyLabel }}
        </span>
      </div>

      <!-- Pending state CTA：全寬大按鈕 + peach gradient -->
      <div v-if="!completed" class="mt-5">
        <button
          type="button"
          @click="onComplete"
          class="w-full px-6 py-3.5 rounded-2xl bg-peach-gradient text-white font-display font-black text-base shadow-soft active:scale-[0.98] transition-transform"
          data-test="today-action-complete-btn"
        >
          {{ t('action_btn_done') }} ✓
        </button>
      </div>

      <!-- Feedback prompt 3 emoji button + optional textarea -->
      <div v-else-if="!feedbackSubmitted" class="mt-5" data-test="today-action-feedback">
        <p class="font-zen text-sm text-stone-700 mb-3 text-center">
          {{ t('action_feedback_prompt') }}
        </p>
        <div class="grid grid-cols-3 gap-2">
          <button
            type="button"
            @click="onFeedback('helpful')"
            class="fb-btn"
            :class="tappedFb === 'helpful' && 'fb-btn-active'"
            data-test="today-action-fb-helpful"
          >
            <span class="fb-emoji">💛</span>
            <span class="font-zen text-[11px] text-stone-600">{{ t('action_feedback_helpful') }}</span>
          </button>
          <button
            type="button"
            @click="onFeedback('neutral')"
            class="fb-btn"
            :class="tappedFb === 'neutral' && 'fb-btn-active'"
            data-test="today-action-fb-neutral"
          >
            <span class="fb-emoji">😐</span>
            <span class="font-zen text-[11px] text-stone-600">{{ t('action_feedback_neutral') }}</span>
          </button>
          <button
            type="button"
            @click="onFeedback('unhelpful')"
            class="fb-btn"
            :class="tappedFb === 'unhelpful' && 'fb-btn-active'"
            data-test="today-action-fb-unhelpful"
          >
            <span class="fb-emoji">🌧️</span>
            <span class="font-zen text-[11px] text-stone-600">{{ t('action_feedback_unhelpful') }}</span>
          </button>
        </div>

        <!-- optional 對朵朵說的話 -->
        <textarea
          v-if="!props.compact"
          v-model="feedbackNote"
          rows="2"
          maxlength="500"
          :placeholder="t('action_feedback_note_placeholder') || '想跟朵朵說什麼？（可不填）'"
          class="mt-3 w-full px-3.5 py-2.5 rounded-2xl border border-cream-200 bg-white/80 focus:outline-none focus:border-peach-300 focus:ring-2 focus:ring-peach-100 font-zen text-[13px] leading-relaxed resize-none"
        />
      </div>

      <!-- Feedback already in -->
      <div v-else class="mt-5 flex items-center justify-center gap-2 text-sage-500 font-zen text-sm" data-test="today-action-thanks">
        <span aria-hidden="true">✓</span>
        <span>{{ t('action_dodo_thanks') }}</span>
      </div>
    </div>
  </div>
</template>

<style scoped>
/* Feedback emoji button */
.fb-btn {
  @apply py-3 rounded-2xl bg-white shadow-soft flex flex-col items-center gap-1 transition-all duration-200;
}
.fb-btn:active {
  transform: scale(0.95);
}
.fb-btn-active {
  @apply ring-2 ring-peach-300 bg-peach-50;
}
/* Emoji micro-animation：scale 1.1 → 1（pop） */
.fb-emoji {
  @apply text-2xl inline-block;
  transition: transform 200ms cubic-bezier(0.34, 1.56, 0.64, 1);
}
.fb-btn:hover .fb-emoji,
.fb-btn:focus-visible .fb-emoji {
  transform: scale(1.15);
}
.fb-btn-active .fb-emoji {
  animation: pop 320ms cubic-bezier(0.34, 1.56, 0.64, 1);
}
@keyframes pop {
  0% { transform: scale(1); }
  50% { transform: scale(1.25); }
  100% { transform: scale(1); }
}
</style>
