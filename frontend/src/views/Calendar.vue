<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { CalendarApi, type CyclePrediction, type BodyRhythm, type CycleRecord, type SymptomRecord } from '../api'
import Card from '../components/ui/Card.vue'
import Spinner from '../components/ui/Spinner.vue'
import EmptyState from '../components/ui/EmptyState.vue'
import Character from '../components/Character.vue'
import CalendarGamificationStrip from '../components/CalendarGamificationStrip.vue'
import TodayActionCard from '../components/TodayActionCard.vue'
import { getPet, moodForPhase } from '../lib/character'
import { useTone } from '../composables/useTone'
import { useRouter } from 'vue-router'

const { t } = useTone()
const router = useRouter()

const cycles = ref<CycleRecord[]>([])
const symptoms = ref<SymptomRecord[]>([])
const prediction = ref<CyclePrediction | null>(null)
const rhythm = ref<BodyRhythm | null>(null)
const loading = ref(true)
const error = ref<string | null>(null)
const pet = ref(getPet())

async function load() {
  loading.value = true
  error.value = null
  try {
    const [c, s] = await Promise.all([CalendarApi.cycles(), CalendarApi.symptoms()])
    cycles.value = c.data.data
    symptoms.value = s.data.data
    prediction.value = c.data.prediction
    rhythm.value = c.data.body_rhythm
  } catch (e: any) {
    error.value = e?.response?.data?.message ?? t('calendar_error_load')
  } finally {
    loading.value = false
  }
}

onMounted(load)

const today = new Date()
const monthStart = computed(() => new Date(today.getFullYear(), today.getMonth(), 1))
const daysInMonth = computed(() => new Date(today.getFullYear(), today.getMonth() + 1, 0).getDate())
const startWeekday = computed(() => monthStart.value.getDay())

interface DayMeta {
  date: string
  day: number
  phase: 'menstrual' | 'follicular' | 'ovulation' | 'luteal' | 'unknown' | null
  isToday: boolean
  hasLog: boolean
}

const grid = computed<DayMeta[]>(() => {
  if (!prediction.value) return []
  const cells: DayMeta[] = []
  for (let i = 0; i < startWeekday.value; i++) {
    cells.push({ date: '', day: 0, phase: null, isToday: false, hasLog: false })
  }
  for (let d = 1; d <= daysInMonth.value; d++) {
    const date = new Date(today.getFullYear(), today.getMonth(), d)
    const isoDate = date.toISOString().slice(0, 10)
    cells.push({
      date: isoDate,
      day: d,
      phase: phaseFor(date),
      isToday: date.toDateString() === today.toDateString(),
      hasLog: hasLogOn(isoDate),
    })
  }
  return cells
})

function phaseFor(date: Date): DayMeta['phase'] {
  if (!prediction.value?.latest_cycle_start) return 'unknown'
  const start = new Date(prediction.value.latest_cycle_start)
  const len = prediction.value.avg_cycle_length
  const periodLen = prediction.value.avg_period_length
  const ovulation = len - 14

  const diffDays = Math.floor((date.getTime() - start.getTime()) / 86400000)
  if (diffDays < 0) return null
  const cycleDay = (diffDays % len) + 1

  if (cycleDay <= periodLen) return 'menstrual'
  if (cycleDay >= ovulation - 1 && cycleDay <= ovulation + 1) return 'ovulation'
  if (cycleDay < ovulation - 1) return 'follicular'
  return 'luteal'
}

function hasLogOn(date: string): boolean {
  return cycles.value.some((c) => c.start_date === date) || symptoms.value.some((s) => s.logged_on === date)
}

const phaseLabels = computed<Record<string, string>>(() => ({
  menstrual: t('calendar_phase_menstrual'),
  follicular: t('calendar_phase_follicular'),
  ovulation: t('calendar_phase_ovulation'),
  luteal: t('calendar_phase_luteal'),
  unknown: t('calendar_phase_unknown'),
}))

const monthTitle = computed(() =>
  t('calendar_year_month', { year: today.getFullYear(), month: today.getMonth() + 1 }),
)
const todayMood = computed(() => moodForPhase(rhythm.value?.phase))

// P0-2 倒數天數大字
const daysUntilNext = computed(() => rhythm.value?.days_until_next_period ?? null)
const countdownLabel = computed(() => {
  const d = daysUntilNext.value
  if (d === null) return null
  if (d < 0) return t('countdown_label_late')
  if (d === 0) return t('countdown_label_today')
  if (d <= 7) return t('countdown_label_close')
  return t('countdown_label_normal')
})

// P1-7 click-day modal
const detailDate = ref<string | null>(null)
const detailDay = computed<DayMeta | null>(() => grid.value.find((c) => c.date === detailDate.value) ?? null)
const detailCycle = computed(() => cycles.value.find((c) => c.start_date === detailDate.value) ?? null)
const detailSymptom = computed(() => symptoms.value.find((s) => s.logged_on === detailDate.value) ?? null)

const TAG_LABEL: Record<string, string> = {
  cramp: '經痛', headache: '頭痛', fatigue: '疲倦', bloating: '腹脹',
  breast_tender: '胸脹', acne: '冒痘', mood_swing: '情緒起伏',
  craving_sweet: '想吃甜', insomnia: '失眠', back_pain: '腰痠',
}
const MOOD_LABEL: Record<string, string> = { good: '😊 還不錯', okay: '😐 普普', bad: '😞 不太好' }

function openDay(cell: DayMeta) {
  if (cell.day === 0) return
  detailDate.value = cell.date
}

// streak cells: 連續記錄到今天的最後 N 天加 sparkle ✨
const streakDates = computed<Set<string>>(() => {
  const set = new Set<string>()
  const all = new Set<string>()
  cycles.value.forEach((c) => all.add(c.start_date))
  symptoms.value.forEach((s) => all.add(s.logged_on))
  let cursor = new Date()
  for (let i = 0; i < 60; i++) {
    const iso = cursor.toISOString().slice(0, 10)
    if (!all.has(iso)) break
    set.add(iso)
    cursor.setDate(cursor.getDate() - 1)
  }
  return set
})

function goPet() {
  router.push('/me/journey')
}
</script>

<template>
  <div class="px-5 md:px-8 pt-10 pb-6 max-w-md md:max-w-4xl lg:max-w-5xl mx-auto">
    <!-- 倒數大字 header -->
    <header class="flex items-start justify-between mb-5">
      <div class="flex-1">
        <p class="font-zen text-xs text-stone-500 tracking-widest uppercase">{{ monthTitle }}</p>
        <template v-if="daysUntilNext !== null">
          <p class="font-zen text-[11px] text-stone-500 mt-1">{{ countdownLabel }}</p>
          <h1 class="font-display text-5xl font-bold text-peach-500 mt-0.5 leading-none">
            <template v-if="daysUntilNext < 0">+{{ Math.abs(daysUntilNext) }}</template>
            <template v-else>{{ daysUntilNext }}</template>
            <span class="text-base text-stone-400 ml-2 font-zen">
              {{ daysUntilNext === 0 ? t('calendar_unit_today') : t('calendar_unit_day') }}
            </span>
          </h1>
        </template>
        <template v-else>
          <h1 class="font-display text-2xl font-bold text-peach-500 mt-0.5">{{ monthTitle }}</h1>
        </template>
        <p
          v-if="rhythm"
          class="font-zen text-[12px] text-stone-600 mt-1.5"
          data-test="phase-label"
        >
          {{ t('calendar_phase_now_prefix') }}
          <span class="font-semibold text-peach-500">{{ phaseLabels[rhythm.phase] }}</span>
          <template v-if="rhythm.cycle_day">{{ t('calendar_cycle_day_suffix', { day: rhythm.cycle_day }) }}</template>
        </p>
      </div>
      <!-- 角落寵物 widget（click → /me/journey） -->
      <button
        type="button"
        class="shrink-0 -mt-2 active:scale-95 transition-transform"
        :title="`${pet.nickname}, Lv ${pet.level}`"
        :aria-label="`${pet.nickname}, Lv ${pet.level}`"
        @click="goPet"
        data-test="header-pet"
      >
        <Character
          :species="pet.species"
          :level="pet.level"
          :outfit="pet.outfit"
          :mood="todayMood"
          :size="68"
          :show-halo="false"
          :floaty="true"
          :interactive="true"
          :show-rarity="false"
        />
      </button>
    </header>

    <!-- Gamification strip: pet / streak / quest / milestone -->
    <CalendarGamificationStrip :phase="rhythm?.phase ?? null" />

    <TodayActionCard class="mb-6" :compact="true" />

    <Spinner v-if="loading" :label="t('calendar_loading')" />

    <EmptyState
      v-else-if="error"
      icon="🌸"
      :title="t('calendar_empty_title')"
      :subtitle="error"
    />

    <template v-else>
      <div class="md:grid md:grid-cols-[minmax(0,1fr)_320px] md:gap-6 md:items-start">
      <div class="md:min-w-0">
      <Card tone="plain" class="mb-4">
        <div class="grid grid-cols-7 text-[11px] font-zen text-center text-stone-400 mb-3">
          <span v-for="w in [t('calendar_weekday_sun'), t('calendar_weekday_mon'), t('calendar_weekday_tue'), t('calendar_weekday_wed'), t('calendar_weekday_thu'), t('calendar_weekday_fri'), t('calendar_weekday_sat')]" :key="w">{{ w }}</span>
        </div>
        <div class="grid grid-cols-7 gap-1.5">
          <button
            v-for="(cell, idx) in grid"
            :key="idx"
            @click="openDay(cell)"
            :disabled="cell.day === 0"
            class="aspect-square rounded-xl flex items-center justify-center text-sm font-zen relative transition-all active:scale-95 disabled:cursor-default"
            :data-test="cell.date ? `cal-day-${cell.day}` : undefined"
            :class="{
              'bg-phase-menstrual/20 text-sakura-500': cell.phase === 'menstrual',
              'bg-phase-follicular/30 text-peach-500': cell.phase === 'follicular',
              'bg-phase-ovulation/25 text-sage-500': cell.phase === 'ovulation',
              'bg-phase-luteal/20 text-lavender-500': cell.phase === 'luteal',
              'ring-2 ring-peach-400 font-bold shadow-soft animate-pulse-slow': cell.isToday,
            }"
          >
            {{ cell.day || '' }}
            <span
              v-if="cell.hasLog"
              class="absolute bottom-0.5 right-0.5 w-1.5 h-1.5 rounded-full bg-peach-500"
            />
            <span
              v-if="cell.date && streakDates.has(cell.date)"
              class="absolute top-0.5 right-0.5 text-[10px] leading-none select-none"
              aria-hidden="true"
            >✨</span>
          </button>
        </div>
      </Card>

      <Card tone="cream" class="mb-4">
        <h3 class="font-display font-bold text-peach-500 text-base mb-2">{{ t('calendar_section_next_period') }}</h3>
        <p v-if="prediction?.next_period_eta" class="text-sm text-stone-700 leading-relaxed font-zen">
          {{ t('calendar_eta_prefix') }}
          <span class="font-bold text-peach-500">{{ prediction.next_period_eta }}</span>
          {{ t('calendar_eta_confidence_label') }}
          <span :class="prediction.confidence === 'high' ? 'text-sage-500' : 'text-peach-400'">
            {{
              prediction.confidence === 'high'
                ? t('calendar_confidence_high')
                : prediction.confidence === 'low'
                ? t('calendar_confidence_low')
                : t('calendar_confidence_none')
            }}
          </span>
        </p>
        <p v-else class="text-sm text-stone-500 font-zen">{{ t('calendar_no_prediction') }}</p>
      </Card>

      <div class="flex gap-3 text-[11px] text-stone-500 px-2 font-zen flex-wrap">
        <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-phase-menstrual" />{{ t('calendar_legend_period') }}</span>
        <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-phase-follicular" />{{ t('calendar_legend_follicular') }}</span>
        <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-phase-ovulation" />{{ t('calendar_legend_ovulation') }}</span>
        <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-phase-luteal" />{{ t('calendar_legend_luteal') }}</span>
        <span class="flex items-center gap-1.5"><span class="w-1.5 h-1.5 rounded-full bg-peach-500" />{{ t('calendar_legend_logged') }}</span>
      </div>
      </div>

      <!-- iPad / desktop side panel：永久顯示當天 detail（md+ 才出現） -->
      <aside class="hidden md:block md:sticky md:top-6" data-test="day-detail-side">
        <Card tone="cream" class="space-y-3">
          <header class="flex items-center justify-between">
            <h3 class="font-display text-base font-bold text-peach-500">
              {{ detailDate || t('calendar_detail_picker_hint') }}
            </h3>
          </header>

          <p v-if="detailDay?.phase" class="font-zen text-[12px] text-stone-500">
            {{ t('calendar_detail_phase_prefix') }}
            <span class="font-semibold text-peach-500">{{ phaseLabels[detailDay.phase] }}</span>
          </p>

          <div v-if="detailCycle" class="bg-white rounded-2xl p-3 text-sm font-zen space-y-1">
            <p class="text-peach-500 font-bold">{{ t('calendar_detail_section_period') }}</p>
            <p class="text-stone-600">{{ t('calendar_detail_flow_label') }} {{ detailCycle.peak_flow ?? t('calendar_detail_flow_unfilled') }} / 5</p>
            <p v-if="detailCycle.length_days" class="text-stone-500 text-xs">{{ t('calendar_detail_length_prefix') }} {{ detailCycle.length_days }}{{ t('calendar_detail_length_suffix') }}</p>
          </div>

          <div v-if="detailSymptom" class="bg-white rounded-2xl p-3 text-sm font-zen space-y-1.5">
            <p class="text-peach-500 font-bold">{{ t('calendar_detail_section_body') }}</p>
            <p class="text-stone-600">{{ MOOD_LABEL[detailSymptom.mood ?? ''] || t('calendar_detail_no_mood') }}</p>
            <div v-if="detailSymptom.tags?.length" class="flex flex-wrap gap-1">
              <span
                v-for="t in detailSymptom.tags"
                :key="t"
                class="text-[11px] bg-cream-100 text-peach-500 px-2 py-0.5 rounded-full"
              >
                {{ TAG_LABEL[t] || t }}
              </span>
            </div>
          </div>

          <p
            v-if="detailDate && !detailCycle && !detailSymptom"
            class="text-stone-400 text-[12px] text-center font-zen py-3"
          >
            {{ t('calendar_detail_empty') }}
          </p>
          <p
            v-else-if="!detailDate"
            class="text-stone-400 text-[12px] text-center font-zen py-3"
          >
            {{ t('calendar_detail_hint') }}
          </p>
        </Card>
      </aside>
      </div>
    </template>

    <!-- P1-7 Day detail modal -->
    <Transition name="ach">
      <div
        v-if="detailDate"
        class="fixed inset-0 z-[70] bg-stone-900/40 backdrop-blur-sm flex items-end sm:items-center justify-center p-4 md:hidden"
        @click.self="detailDate = null"
        data-test="day-detail-modal"
      >
        <div class="w-full max-w-sm bg-cream-50 rounded-3xl p-5 shadow-soft-lg space-y-3 animate-pop">
          <header class="flex items-center justify-between">
            <h3 class="font-display text-lg font-bold text-peach-500">{{ detailDate }}</h3>
            <button @click="detailDate = null" class="text-stone-400 text-xl leading-none">×</button>
          </header>

          <p v-if="detailDay?.phase" class="font-zen text-[12px] text-stone-500">
            {{ t('calendar_detail_phase_prefix') }}
            <span class="font-semibold text-peach-500">{{ phaseLabels[detailDay.phase] }}</span>
          </p>

          <div v-if="detailCycle" class="bg-white rounded-2xl p-3 text-sm font-zen space-y-1">
            <p class="text-peach-500 font-bold">{{ t('calendar_detail_section_period') }}</p>
            <p class="text-stone-600">{{ t('calendar_detail_flow_label') }} {{ detailCycle.peak_flow ?? t('calendar_detail_flow_unfilled') }} / 5</p>
            <p v-if="detailCycle.length_days" class="text-stone-500 text-xs">{{ t('calendar_detail_length_prefix') }} {{ detailCycle.length_days }}{{ t('calendar_detail_length_suffix') }}</p>
          </div>

          <div v-if="detailSymptom" class="bg-white rounded-2xl p-3 text-sm font-zen space-y-1.5">
            <p class="text-peach-500 font-bold">{{ t('calendar_detail_section_body') }}</p>
            <p class="text-stone-600">{{ MOOD_LABEL[detailSymptom.mood ?? ''] || t('calendar_detail_no_mood') }}</p>
            <div v-if="detailSymptom.tags?.length" class="flex flex-wrap gap-1">
              <span
                v-for="t in detailSymptom.tags"
                :key="t"
                class="text-[11px] bg-cream-100 text-peach-500 px-2 py-0.5 rounded-full"
              >
                {{ TAG_LABEL[t] || t }}
              </span>
            </div>
          </div>

          <p
            v-if="!detailCycle && !detailSymptom"
            class="text-stone-400 text-[12px] text-center font-zen py-3"
          >
            {{ t('calendar_detail_empty') }}
          </p>
        </div>
      </div>
    </Transition>
  </div>
</template>
