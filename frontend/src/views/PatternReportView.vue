<script setup lang="ts">
/**
 * PatternReportView — 妳的這個月 pattern 整理（swiper 模式，YearReview 縮版）
 *
 * Cards 順序：
 *   1. hero（朵朵 message + 妳的這個月）
 *   2. phase 累積 horizontal bar
 *   3. top 3 對妳 work 的方法
 *   4. top 3 沒效（contrast，不羞辱）
 *   5. vs 上月 symptom（top 3 + 箭頭）
 *   6. closing（朵朵謝謝 + 分享）
 *
 * 底下：歷史 list（minimal text rows）。
 * Empty / loading / error 三態保留。
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
import { useSfx } from '../lib/sound'

const { t } = useTone()
const sfx = useSfx()

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
const phaseEmoji: Record<Phase, string> = {
  menstrual: '🌸',
  follicular: '🌱',
  ovulation: '☀️',
  luteal: '🌙',
  unknown: '·',
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

// === Swiper logic ===
type CardKey = 'hero' | 'phase' | 'helpful' | 'unhelpful' | 'vs' | 'closing'

const cardOrder = computed<CardKey[]>(() => {
  if (!latest.value) return []
  const arr: CardKey[] = ['hero', 'phase']
  if (latest.value.top_actions?.length) arr.push('helpful')
  if (latest.value.top_unhelpful?.length) arr.push('unhelpful')
  if (latest.value.vs_previous?.length) arr.push('vs')
  arr.push('closing')
  return arr
})

const idx = ref(0)
const current = computed(() => cardOrder.value[idx.value])
const isFirst = computed(() => idx.value === 0)
const isLast = computed(() => idx.value >= cardOrder.value.length - 1)

function next() {
  if (isLast.value) return
  sfx.play('ui_tap')
  idx.value += 1
}
function prev() {
  if (isFirst.value) return
  sfx.play('ui_tap')
  idx.value -= 1
}

async function share() {
  sfx.play('correct')
  const text = t('pattern_report_share_text', { month: latest.value?.month_label ?? '' }) || `朵朵幫我整理了這個月的記錄 💛`
  const url = window.location.href
  try {
    if (navigator.share) {
      await navigator.share({ title: t('pattern_report_heading'), text, url })
    } else {
      await navigator.clipboard?.writeText(`${text} ${url}`)
    }
  } catch {
    /* user cancelled */
  }
}
</script>

<template>
  <div class="min-h-screen bg-gradient-to-b from-cream-50 via-peach-50/40 to-lavender-50/40">
    <div class="px-5 md:px-8 pt-8 pb-24 max-w-md md:max-w-3xl mx-auto space-y-6" data-test="pattern-report-view">
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

      <!-- Empty state（沒走完 cycle） -->
      <Card v-else-if="!latest" tone="cream" class="text-center py-10" data-test="pattern-report-empty">
        <p class="text-5xl mb-3">🐣</p>
        <p class="font-display font-bold text-peach-500 text-lg mb-2">
          {{ t('pattern_report_heading') }}
        </p>
        <p class="font-zen text-sm text-stone-500 leading-relaxed max-w-[280px] mx-auto">
          {{ t('pattern_report_empty') }}
        </p>
      </Card>

      <template v-else>
        <!-- progress dots -->
        <div class="flex justify-center gap-1.5" aria-label="card progress">
          <span
            v-for="(_, i) in cardOrder"
            :key="i"
            class="h-1.5 rounded-full transition-all"
            :class="i === idx ? 'w-6 bg-peach-500' : 'w-1.5 bg-cream-200'"
          />
        </div>

        <!-- swiper card area -->
        <div class="relative" data-test="pattern-report-swiper">
          <Transition name="card-fade" mode="out-in">
            <!-- Card 1: hero -->
            <Card
              v-if="current === 'hero'"
              key="hero"
              tone="peach"
              class="space-y-4 min-h-[420px] flex flex-col justify-center text-center"
              data-test="pattern-report-hero"
            >
              <p class="font-zen text-[11px] uppercase tracking-[0.3em] text-white/80">
                {{ latest.month_label || t('pattern_report_subtitle') }}
              </p>
              <h1 class="font-display text-[32px] font-black text-white leading-tight">
                {{ t('pattern_report_heading') }}
              </h1>
              <p class="font-zen text-[15px] text-white/95 leading-relaxed whitespace-pre-line px-2">
                {{ latest.dodo_message || t('dodo_message_default') }}
              </p>
              <p class="font-zen text-[10px] text-white/60 pt-2">
                {{ t('pattern_report_generated_at', { at: fmtDate(latest.generated_at) }) }}
              </p>
            </Card>

            <!-- Card 2: phase distribution -->
            <Card
              v-else-if="current === 'phase'"
              key="phase"
              tone="cream"
              class="space-y-5 min-h-[420px] flex flex-col justify-center"
              data-test="pattern-phase-bar"
            >
              <div class="text-center space-y-1">
                <p class="font-zen text-[11px] tracking-widest text-stone-400 uppercase">02</p>
                <h2 class="font-display font-black text-peach-500 text-2xl">
                  {{ t('pattern_report_phase_distribution') }}
                </h2>
                <p class="font-zen text-xs text-stone-500">{{ totalPhaseDays }} {{ t('pattern_report_total_days') || '天' }}</p>
              </div>
              <div v-if="totalPhaseDays > 0" class="flex h-5 rounded-full overflow-hidden shadow-soft">
                <div
                  v-for="p in phaseOrder"
                  :key="p"
                  :class="phaseColors[p]"
                  :style="{ width: `${phasePct(p)}%` }"
                  :title="`${phaseLabel(p)} ${phasePct(p)}%`"
                />
              </div>
              <ul class="grid grid-cols-2 gap-3 text-sm font-zen pt-1">
                <li v-for="p in phaseOrder" :key="p" class="flex items-center gap-2">
                  <span class="w-3 h-3 rounded-full shrink-0" :class="phaseColors[p]" aria-hidden="true" />
                  <span class="text-stone-700 flex-1 font-bold">{{ phaseEmoji[p] }} {{ phaseLabel(p) }}</span>
                  <span class="text-stone-500 text-[12px]">
                    {{ latest.phase_summary[p] ?? 0 }} {{ t('pattern_report_total_days') || '天' }}
                  </span>
                </li>
              </ul>
            </Card>

            <!-- Card 3: top helpful -->
            <Card
              v-else-if="current === 'helpful'"
              key="helpful"
              tone="cream"
              class="space-y-5 min-h-[420px] flex flex-col justify-center"
              data-test="pattern-top-helpful"
            >
              <div class="text-center space-y-1">
                <p class="text-3xl">💛</p>
                <p class="font-zen text-[11px] tracking-widest text-stone-400 uppercase">03</p>
                <h2 class="font-display font-black text-peach-500 text-2xl">
                  {{ t('pattern_report_top_helpful') }}
                </h2>
              </div>
              <ul class="space-y-3">
                <li
                  v-for="(a, i) in latest.top_actions.slice(0, 3)"
                  :key="a.action_key"
                  class="space-y-1.5"
                >
                  <div class="flex items-baseline justify-between gap-2 text-sm font-zen">
                    <span class="text-stone-700 flex-1 min-w-0 font-bold">
                      <span class="text-peach-400 mr-1">#{{ i + 1 }}</span>{{ a.title }}
                    </span>
                    <span class="text-[12px] text-sage-500 font-black shrink-0">
                      {{ pctLabel(a.effectiveness) }}
                    </span>
                  </div>
                  <div class="h-2 rounded-full bg-stone-100 overflow-hidden">
                    <div
                      class="h-full bg-gradient-to-r from-sage-300 to-sage-500 rounded-full transition-all duration-500"
                      :style="{ width: `${Math.round(a.effectiveness * 100)}%` }"
                    />
                  </div>
                </li>
              </ul>
            </Card>

            <!-- Card 4: top unhelpful（contrast，不羞辱） -->
            <Card
              v-else-if="current === 'unhelpful'"
              key="unhelpful"
              tone="cream"
              class="space-y-5 min-h-[420px] flex flex-col justify-center"
              data-test="pattern-top-unhelpful"
            >
              <div class="text-center space-y-1">
                <p class="text-3xl">🌧️</p>
                <p class="font-zen text-[11px] tracking-widest text-stone-400 uppercase">04</p>
                <h2 class="font-display font-black text-stone-600 text-2xl">
                  {{ t('pattern_report_top_unhelpful') }}
                </h2>
                <p class="font-zen text-[12px] text-stone-500 leading-relaxed pt-1 max-w-[260px] mx-auto">
                  {{ t('pattern_report_unhelpful_blurb') || '這些對妳這個月沒有特別幫助 — 換個方式試試也沒關係。' }}
                </p>
              </div>
              <ul class="space-y-3">
                <li
                  v-for="(a, i) in latest.top_unhelpful.slice(0, 3)"
                  :key="a.action_key"
                  class="space-y-1.5"
                >
                  <div class="flex items-baseline justify-between gap-2 text-sm font-zen">
                    <span class="text-stone-600 flex-1 min-w-0">
                      <span class="text-stone-400 mr-1">#{{ i + 1 }}</span>{{ a.title }}
                    </span>
                    <span class="text-[12px] text-stone-400 font-bold shrink-0">
                      {{ pctLabel(a.effectiveness) }}
                    </span>
                  </div>
                  <div class="h-2 rounded-full bg-stone-100 overflow-hidden">
                    <div
                      class="h-full bg-stone-300 rounded-full transition-all duration-500"
                      :style="{ width: `${Math.round(a.effectiveness * 100)}%` }"
                    />
                  </div>
                </li>
              </ul>
            </Card>

            <!-- Card 5: vs previous -->
            <Card
              v-else-if="current === 'vs'"
              key="vs"
              tone="cream"
              class="space-y-5 min-h-[420px] flex flex-col justify-center"
              data-test="pattern-vs-previous"
            >
              <div class="text-center space-y-1">
                <p class="text-3xl">📊</p>
                <p class="font-zen text-[11px] tracking-widest text-stone-400 uppercase">05</p>
                <h2 class="font-display font-black text-peach-500 text-2xl">
                  {{ t('pattern_report_vs_previous') }}
                </h2>
              </div>
              <ul class="space-y-4">
                <li
                  v-for="(d, i) in latest.vs_previous.slice(0, 3)"
                  :key="d.symptom + i"
                  class="flex items-center gap-4"
                >
                  <span
                    class="text-4xl font-black shrink-0 w-10 text-center"
                    :class="arrowColor(d.direction)"
                    aria-hidden="true"
                  >
                    {{ arrowFor(d.direction) }}
                  </span>
                  <div class="flex-1 min-w-0">
                    <p class="font-display font-bold text-stone-700 text-base truncate">{{ d.symptom }}</p>
                    <p class="font-zen text-[12px] text-stone-500 mt-0.5">
                      {{
                        d.direction === 'up'
                          ? t('pattern_report_delta_up')
                          : d.direction === 'down'
                          ? t('pattern_report_delta_down')
                          : t('pattern_report_delta_flat')
                      }}
                      <span v-if="d.delta !== 0" class="ml-1 font-bold">
                        {{ d.delta > 0 ? '+' : '' }}{{ d.delta }}
                      </span>
                    </p>
                  </div>
                </li>
              </ul>
            </Card>

            <!-- Card 6: closing -->
            <Card
              v-else-if="current === 'closing'"
              key="closing"
              tone="peach"
              class="space-y-4 min-h-[420px] flex flex-col justify-center text-center"
            >
              <p class="text-5xl">💛</p>
              <h2 class="font-display text-2xl font-black text-white leading-snug px-2">
                {{ t('pattern_report_closing_title') || '謝謝妳這個月的記錄' }}
              </h2>
              <p class="font-zen text-sm text-white/95 leading-relaxed px-2">
                {{ t('pattern_report_closing_blurb') || '朵朵會繼續陪妳，下個週期再見。' }}
              </p>
              <button
                type="button"
                @click="share"
                class="mx-auto mt-2 px-5 py-2.5 rounded-full bg-white text-peach-500 font-display font-bold text-sm shadow-soft active:scale-95"
              >
                {{ t('pattern_report_share') || '分享給朋友' }} →
              </button>
            </Card>
          </Transition>
        </div>

        <!-- Nav -->
        <div class="flex items-center justify-between gap-3">
          <button
            type="button"
            :disabled="isFirst"
            @click="prev"
            class="w-12 h-12 rounded-full bg-white border border-cream-200 flex items-center justify-center text-stone-500 disabled:opacity-30 active:scale-95 transition-transform shadow-soft"
            aria-label="prev"
          >
            ←
          </button>
          <p class="font-zen text-[11px] text-stone-400">
            {{ idx + 1 }} / {{ cardOrder.length }}
          </p>
          <button
            type="button"
            :disabled="isLast"
            @click="next"
            class="w-12 h-12 rounded-full bg-peach-gradient text-white flex items-center justify-center disabled:opacity-30 active:scale-95 transition-transform shadow-soft font-bold"
            aria-label="next"
          >
            →
          </button>
        </div>
      </template>

      <!-- 歷史 list（minimal text rows） -->
      <section v-if="list.length > 0" class="pt-2">
        <h2 class="font-display font-bold text-stone-600 text-sm mb-2">
          {{ t('pattern_report_history_title') }}
        </h2>
        <Card tone="plain" class="bg-white/60">
          <ul class="divide-y divide-stone-100">
            <li
              v-for="row in list"
              :key="row.id"
              class="flex items-center justify-between text-sm font-zen py-2.5 first:pt-0 last:pb-0"
            >
              <span class="text-stone-700">{{ row.month_label }}</span>
              <span class="text-[11px] text-stone-400">{{ fmtDate(row.generated_at) }}</span>
            </li>
          </ul>
        </Card>
      </section>
    </div>
  </div>
</template>

<style scoped>
.card-fade-enter-active,
.card-fade-leave-active {
  transition: opacity 250ms ease, transform 250ms ease;
}
.card-fade-enter-from {
  opacity: 0;
  transform: translateX(20px);
}
.card-fade-leave-to {
  opacity: 0;
  transform: translateX(-20px);
}
</style>
