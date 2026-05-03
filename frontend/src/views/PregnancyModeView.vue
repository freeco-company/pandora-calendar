<script setup lang="ts">
/**
 * P4 孕期模式主畫面 — 顯示當前週數 / trimester / 距 due date / 胎兒大小 / 朵朵 message / suggested actions
 *
 * 進入路徑：
 *   - Profile「個人化」section toggle 開啟孕期模式 → 跳這裡
 *   - Calendar 主頁 banner「妳目前在孕期模式」link
 *
 * 模式結束：
 *   - 底部「結束孕期模式」按鈕 → confirm dialog 列 4 reasons
 *   - reason=miscarriage → 朵朵特別 sensitive 文案 + 心理諮詢專線
 */
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { PregnancyApi, type PregnancyEndReason, type PregnancyWeekContent } from '../api'
import { usePregnancyMode } from '../composables/usePregnancyMode'
import Card from '../components/ui/Card.vue'
import Button from '../components/ui/Button.vue'
import Spinner from '../components/ui/Spinner.vue'
import Modal from '../components/ui/Modal.vue'
import { useTone } from '../composables/useTone'
import { useSfx } from '../lib/sound'

const router = useRouter()
const { t } = useTone()
const sfx = useSfx()
const { state, loading, refresh, end } = usePregnancyMode()

const blocked = ref(false)
const previewWeek = ref<number | null>(null)
const preview = ref<PregnancyWeekContent | null>(null)
const previewLoading = ref(false)

// confirmation dialog state
const showEndDialog = ref(false)
const endReason = ref<PregnancyEndReason | null>(null)
const ending = ref(false)

const displayWeek = computed(() => previewWeek.value ?? state.value?.gestational_week ?? 1)
const displayContent = computed(() => {
  if (preview.value) {
    return {
      week: preview.value.week,
      trimester: preview.value.trimester,
      fetal_size: preview.value.fetal_size,
      dodo_message: preview.value.dodo_message,
      suggested_actions: preview.value.suggested_actions,
    }
  }
  if (!state.value) return null
  return {
    week: state.value.gestational_week,
    trimester: state.value.trimester,
    fetal_size: state.value.fetal_size,
    dodo_message: state.value.this_week.dodo_message,
    suggested_actions: state.value.this_week.suggested_actions,
  }
})

async function loadPreview(week: number) {
  previewLoading.value = true
  try {
    const res = await PregnancyApi.week(week)
    preview.value = res.data.data
    previewWeek.value = week
  } catch {
    preview.value = null
  } finally {
    previewLoading.value = false
  }
}

function resetPreview() {
  preview.value = null
  previewWeek.value = null
}

async function shiftWeek(delta: number) {
  const current = displayWeek.value
  const target = Math.max(1, Math.min(42, current + delta))
  if (target === state.value?.gestational_week) {
    resetPreview()
    return
  }
  await loadPreview(target)
  sfx.play('ui_tap')
}

function openEndDialog() {
  showEndDialog.value = true
  endReason.value = null
  sfx.play('ui_open')
}

function closeEndDialog() {
  if (ending.value) return
  showEndDialog.value = false
  sfx.play('ui_close')
}

async function confirmEnd() {
  if (!endReason.value || ending.value) return
  ending.value = true
  try {
    await end(endReason.value)
    sfx.play('notify')
    showEndDialog.value = false
    // 結束後回 Profile（孕期模式 toggle 自然會 reflect）
    router.push('/me')
  } catch {
    sfx.play('wrong')
  } finally {
    ending.value = false
  }
}

onMounted(async () => {
  await refresh()
  // 如果沒 active state（free user / 沒啟用） → 跳 Profile 提示先啟用
  if (!loading.value && !state.value) {
    blocked.value = true
  }
})
</script>

<template>
  <div class="px-5 pt-8 pb-10 max-w-md mx-auto space-y-4">
    <button
      @click="router.push('/me')"
      class="font-zen text-sm text-peach-500 hover:text-peach-400"
    >
      {{ t('common_back_to_me') }}
    </button>

    <Spinner v-if="loading" :label="t('pregnancy_loading')" />

    <Card v-else-if="blocked" tone="lavender" class="text-center space-y-3">
      <div class="text-4xl">🌸</div>
      <p class="font-zen text-stone-700">{{ t('pregnancy_not_active_blurb') }}</p>
      <Button variant="primary" sfx="ui_open" @click="router.push('/me')">
        {{ t('pregnancy_back_to_profile') }}
      </Button>
    </Card>

    <template v-else-if="state && displayContent">
      <!-- Hero：當前週數大字 + trimester + 距 due date -->
      <Card tone="cream" class="text-center space-y-2" data-test="pregnancy-hero">
        <p class="font-zen text-xs text-stone-500 tracking-widest uppercase">
          {{ t('pregnancy_eyebrow') }}
        </p>
        <h1
          class="font-display text-5xl font-bold text-peach-500"
          data-test="pregnancy-week-big"
        >
          {{ t('pregnancy_week_n', { n: displayContent.week }) }}
        </h1>
        <p class="font-zen text-sm text-stone-600">
          {{ t('pregnancy_trimester_n', { n: displayContent.trimester }) }}
        </p>
        <p
          v-if="state.days_until_due >= 0 && previewWeek === null"
          class="font-zen text-xs text-stone-500"
          data-test="pregnancy-days-until-due"
        >
          {{ t('pregnancy_days_until_due', { days: state.days_until_due }) }}
        </p>
        <p
          v-else-if="previewWeek !== null"
          class="font-zen text-xs text-sakura-500"
          data-test="pregnancy-preview-banner"
        >
          {{ t('pregnancy_preview_mode') }}
          <button
            class="ml-2 underline text-peach-500"
            @click="resetPreview"
          >{{ t('pregnancy_back_to_current_week') }}</button>
        </p>
      </Card>

      <!-- 胎兒大小卡 -->
      <Card tone="plain" class="text-center space-y-2" data-test="pregnancy-fetal-size">
        <p class="font-zen text-xs text-stone-500">{{ t('pregnancy_baby_size_label') }}</p>
        <div class="text-5xl">{{ displayContent.fetal_size.emoji }}</div>
        <p class="font-zen text-sm text-stone-700 leading-relaxed">
          {{ t('pregnancy_baby_size_blurb', { size: displayContent.fetal_size.label }) }}
        </p>
      </Card>

      <!-- 朵朵 message 卡 -->
      <Card tone="lavender" class="space-y-2" data-test="pregnancy-dodo-message">
        <p class="font-zen text-xs text-peach-500">{{ t('dodo_say') }}</p>
        <p class="font-zen text-sm text-stone-700 leading-relaxed">
          {{ displayContent.dodo_message }}
        </p>
      </Card>

      <!-- This week suggested actions -->
      <Card
        v-if="displayContent.suggested_actions.length"
        tone="plain"
        class="space-y-2"
        data-test="pregnancy-actions"
      >
        <h3 class="font-display font-bold text-peach-500 text-sm">
          {{ t('pregnancy_actions_title') }}
        </h3>
        <ul class="space-y-2">
          <li
            v-for="(a, i) in displayContent.suggested_actions"
            :key="i"
            class="flex gap-2 items-start text-sm font-zen text-stone-700 leading-relaxed"
          >
            <span class="text-peach-400 mt-0.5">·</span>
            <span>{{ a.label }}</span>
          </li>
        </ul>
      </Card>

      <!-- Week scrubber：往前 / 往後 -->
      <Card tone="plain" class="space-y-2" data-test="pregnancy-scrubber">
        <p class="font-zen text-xs text-stone-500">{{ t('pregnancy_scrubber_label') }}</p>
        <div class="flex items-center justify-between gap-2">
          <Button
            size="sm"
            variant="secondary"
            :disabled="previewLoading || displayWeek <= 1"
            data-test="pregnancy-prev-week"
            @click="shiftWeek(-1)"
          >← {{ t('pregnancy_prev_week') }}</Button>
          <span class="font-zen text-sm text-stone-700">
            {{ t('pregnancy_week_n', { n: displayWeek }) }}
          </span>
          <Button
            size="sm"
            variant="secondary"
            :disabled="previewLoading || displayWeek >= 42"
            data-test="pregnancy-next-week"
            @click="shiftWeek(1)"
          >{{ t('pregnancy_next_week') }} →</Button>
        </div>
      </Card>

      <!-- 醫療諮詢提醒（合規）-->
      <Card tone="cream" class="space-y-1.5">
        <p class="font-zen text-[12px] text-stone-600 leading-relaxed">
          {{ t('pregnancy_medical_disclaimer') }}
        </p>
      </Card>

      <!-- 結束按鈕（溫柔放在底）-->
      <div class="pt-4">
        <button
          @click="openEndDialog"
          data-test="pregnancy-end-btn"
          class="w-full text-[12px] font-zen text-stone-400 hover:text-sakura-500 underline transition-colors"
        >
          {{ t('pregnancy_end_btn') }}
        </button>
      </div>
    </template>

    <!-- 結束 confirm dialog -->
    <Modal
      :open="showEndDialog"
      :title="t('pregnancy_end_dialog_title')"
      data-test="pregnancy-end-dialog"
      @close="closeEndDialog"
    >
      <div class="space-y-3">
        <p class="font-zen text-sm text-stone-700 leading-relaxed">
          {{ t('pregnancy_end_dialog_blurb') }}
        </p>

        <div class="space-y-2">
          <label
            v-for="r in (['birth', 'miscarriage', 'cancelled', 'false_alarm'] as PregnancyEndReason[])"
            :key="r"
            class="flex items-start gap-2 cursor-pointer p-2 rounded-2xl hover:bg-cream-50"
            :class="endReason === r ? 'bg-cream-100' : ''"
            :data-test="`pregnancy-reason-${r}`"
          >
            <input
              v-model="endReason"
              type="radio"
              :value="r"
              :name="'pregnancy-end-reason'"
              class="mt-1"
            />
            <div class="flex-1">
              <p class="font-zen text-sm text-stone-700">{{ t('pregnancy_reason_' + r + '_title') }}</p>
              <p class="font-zen text-[11px] text-stone-500 leading-relaxed">
                {{ t('pregnancy_reason_' + r + '_help') }}
              </p>
            </div>
          </label>
        </div>

        <!-- miscarriage sensitive -->
        <div
          v-if="endReason === 'miscarriage'"
          data-test="pregnancy-miscarriage-care"
          class="bg-sakura-50 rounded-2xl p-3 space-y-2"
        >
          <p class="font-zen text-sm text-stone-700 leading-relaxed">
            {{ t('pregnancy_miscarriage_dodo_say') }}
          </p>
          <p class="font-zen text-[11px] text-stone-500">
            {{ t('pregnancy_miscarriage_hotline') }}
          </p>
        </div>

        <div class="flex gap-2 pt-2">
          <Button variant="secondary" full :disabled="ending" @click="closeEndDialog">
            {{ t('common_cancel') }}
          </Button>
          <Button
            variant="primary"
            full
            :loading="ending"
            :disabled="!endReason"
            data-test="pregnancy-end-confirm"
            @click="confirmEnd"
          >
            {{ t('pregnancy_end_confirm_btn') }}
          </Button>
        </div>
      </div>
    </Modal>
  </div>
</template>
