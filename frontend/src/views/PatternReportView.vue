<script setup lang="ts">
/**
 * PatternReportView — 妳的這個月 pattern 整理
 *
 * Sections：
 *   hero: 朵朵 message + 「妳的這個月」
 *   1. phase_summary horizontal bar
 *   2. top_actions（helpful / unhelpful）
 *   3. vs_previous（top 3 symptoms 變化）
 *   4. 過去 reports 列表（PatternReportApi.list）
 */
import { computed, onMounted, ref } from 'vue'
import {
  PatternReportApi,
  type PatternReportSummary,
  type PatternReportListRow,
  type Phase,
} from '../api'
import Card from '../components/ui/Card.vue'
import Spinner from '../components/ui/Spinner.vue'
import { useTone } from '../composables/useTone'

const { t } = useTone()

const latest = ref<PatternReportSummary | null>(null)
const list = ref<PatternReportListRow[]>([])
const loading = ref(true)
const listLoading = ref(false)
const error = ref<string | null>(null)

async function loadLatest() {
  loading.value = true
  error.value = null
  try {
    const r = await PatternReportApi.latest()
    latest.value = r.data.data
  } catch (e: any) {
    error.value = e?.response?.data?.message ?? t('action_error_load')
  } finally {
    loading.value = false
  }
}

async function loadList() {
  listLoading.value = true
  try {
    const r = await PatternReportApi.list()
    list.value = r.data.data
  } catch {
    list.value = []
  } finally {
    listLoading.value = false
  }
}

onMounted(() => {
  loadLatest()
  loadList()
})

const phaseOrder: Phase[] = ['menstrual', 'follicular', 'ovulation', 'luteal']
const phaseColors: Record<Phase, string> = {
  menstrual: 'bg-sakura-300',
  follicular: 'bg-peach-300',
  ovulation: 'bg-sage-300',
  luteal: 'bg-lavender-300',
  unknown: 'bg-stone-200',
}

const totalPhaseDays = computed(() => {
  if (!latest.value) return 0
  return phaseOrder.reduce((sum, p) => sum + (latest.value!.phase_summary[p] ?? 0), 0)
})

function phaseLabel(p: Phase) {
  return t(`action_phase_${p}`)
}

function phasePct(p: Phase) {
  if (!latest.value || totalPhaseDays.value === 0) return 0
  const days = latest.value.phase_summary[p] ?? 0
  return Math.round((days / totalPhaseDays.value) * 100)
}

function arrowFor(direction: 'up' | 'down' | 'flat') {
  return direction === 'up' ? '↑' : direction === 'down' ? '↓' : '→'
}

function arrowColor(direction: 'up' | 'down' | 'flat') {
  return direction === 'up'
    ? 'text-peach-500'
    : direction === 'down'
    ? 'text-sage-500'
    : 'text-stone-400'
}

function pctLabel(eff: number) {
  return `${Math.round(eff * 100)}%`
}

function fmtDate(s: string) {
  return s.slice(0, 10)
}
</script>

<template>
  <div class="px-5 md:px-8 pt-10 pb-24 max-w-md md:max-w-3xl mx-auto space-y-6" data-test="pattern-report-view">
    <Spinner v-if="loading" />

    <Card v-else-if="error" tone="cream">
      <p class="text-sm font-zen text-stone-500">{{ error }}</p>
      <button
        type="button"
        @click="loadLatest"
        class="text-peach-500 text-sm font-zen underline mt-2"
      >
        {{ t('btn_retry') }}
      </button>
    </Card>

    <Card v-else-if="!latest" tone="cream" class="text-center py-8" data-test="pattern-report-empty">
      <p class="text-3xl mb-3">🌙</p>
      <p class="font-display font-bold text-peach-500 text-base mb-2">
        {{ t('pattern_report_heading') }}
      </p>
      <p class="font-zen text-sm text-stone-500 leading-relaxed">{{ t('pattern_report_empty') }}</p>
    </Card>

    <template v-else>
      <!-- hero: dodo message + heading -->
      <Card tone="peach" class="space-y-3" data-test="pattern-report-hero">
        <p class="font-zen text-[11px] uppercase tracking-widest text-white/80">
          {{ latest.month_label || t('pattern_report_subtitle') }}
        </p>
        <h1 class="font-display text-3xl font-bold text-white leading-tight">
          {{ t('pattern_report_heading') }}
        </h1>
        <p class="font-zen text-sm text-white/95 leading-relaxed whitespace-pre-line">
          {{ latest.dodo_message || t('dodo_message_default') }}
        </p>
        <p class="font-zen text-[10px] text-white/60">
          {{ t('pattern_report_generated_at', { at: fmtDate(latest.generated_at) }) }}
        </p>
      </Card>

      <!-- Section 1: phase distribution -->
      <section>
        <h2 class="font-display font-bold text-peach-500 text-base mb-3">
          {{ t('pattern_report_phase_distribution') }}
        </h2>
        <Card tone="cream" class="space-y-3" data-test="pattern-phase-bar">
          <div v-if="totalPhaseDays > 0" class="flex h-3 rounded-full overflow-hidden">
            <div
              v-for="p in phaseOrder"
              :key="p"
              :class="phaseColors[p]"
              :style="{ width: `${phasePct(p)}%` }"
              :title="`${phaseLabel(p)} ${phasePct(p)}%`"
            />
          </div>
          <ul class="grid grid-cols-2 gap-2 text-[12px] font-zen">
            <li v-for="p in phaseOrder" :key="p" class="flex items-center gap-2">
              <span class="w-2.5 h-2.5 rounded-full" :class="phaseColors[p]" aria-hidden="true" />
              <span class="text-stone-600 flex-1">{{ phaseLabel(p) }}</span>
              <span class="text-stone-500 text-[11px]">
                {{ t('pattern_report_phase_days', { n: latest.phase_summary[p] ?? 0 }) }}
              </span>
            </li>
          </ul>
        </Card>
      </section>

      <!-- Section 2: top actions -->
      <section v-if="latest.top_actions?.length">
        <h2 class="font-display font-bold text-peach-500 text-base mb-3">
          {{ t('pattern_report_top_helpful') }}
        </h2>
        <Card tone="cream" class="space-y-2" data-test="pattern-top-helpful">
          <ul class="space-y-1.5">
            <li
              v-for="a in latest.top_actions.slice(0, 3)"
              :key="a.action_key"
              class="flex items-center justify-between gap-2 text-sm font-zen"
            >
              <span class="text-stone-700 flex-1 min-w-0">{{ a.title }}</span>
              <span class="text-[11px] text-sage-500 font-bold shrink-0">
                {{ pctLabel(a.effectiveness) }}
              </span>
            </li>
          </ul>
        </Card>
      </section>

      <section v-if="latest.top_unhelpful?.length">
        <h2 class="font-display font-bold text-stone-500 text-base mb-3">
          {{ t('pattern_report_top_unhelpful') }}
        </h2>
        <Card tone="cream" class="space-y-2" data-test="pattern-top-unhelpful">
          <ul class="space-y-1.5">
            <li
              v-for="a in latest.top_unhelpful.slice(0, 3)"
              :key="a.action_key"
              class="flex items-center justify-between gap-2 text-sm font-zen"
            >
              <span class="text-stone-500 flex-1 min-w-0">{{ a.title }}</span>
              <span class="text-[11px] text-stone-400 font-bold shrink-0">
                {{ pctLabel(a.effectiveness) }}
              </span>
            </li>
          </ul>
        </Card>
      </section>

      <!-- Section 3: vs previous -->
      <section v-if="latest.vs_previous?.length">
        <h2 class="font-display font-bold text-peach-500 text-base mb-3">
          {{ t('pattern_report_vs_previous') }}
        </h2>
        <Card tone="cream" class="space-y-2" data-test="pattern-vs-previous">
          <ul class="space-y-1.5">
            <li
              v-for="(d, i) in latest.vs_previous.slice(0, 3)"
              :key="d.symptom + i"
              class="flex items-center gap-3 text-sm font-zen"
            >
              <span class="text-xl shrink-0" :class="arrowColor(d.direction)" aria-hidden="true">
                {{ arrowFor(d.direction) }}
              </span>
              <span class="text-stone-700 flex-1 min-w-0">{{ d.symptom }}</span>
              <span class="text-[11px] text-stone-500 shrink-0">
                {{
                  d.direction === 'up'
                    ? t('pattern_report_delta_up')
                    : d.direction === 'down'
                    ? t('pattern_report_delta_down')
                    : t('pattern_report_delta_flat')
                }}
                <span v-if="d.delta !== 0" class="ml-1">{{ d.delta > 0 ? '+' : '' }}{{ d.delta }}</span>
              </span>
            </li>
          </ul>
        </Card>
      </section>
    </template>

    <!-- Section 4: 過去 reports（不論 latest 是否 null 都顯示） -->
    <section v-if="list.length > 0">
      <h2 class="font-display font-bold text-peach-500 text-base mb-3">
        {{ t('pattern_report_history_title') }}
      </h2>
      <Card tone="cream" class="space-y-1.5">
        <ul class="space-y-1">
          <li
            v-for="row in list"
            :key="row.id"
            class="flex items-center justify-between text-sm font-zen py-1.5 border-b border-stone-100 last:border-0"
          >
            <span class="text-stone-700">{{ row.month_label }}</span>
            <span class="text-[11px] text-stone-400">{{ fmtDate(row.generated_at) }}</span>
          </li>
        </ul>
      </Card>
    </section>
  </div>
</template>
