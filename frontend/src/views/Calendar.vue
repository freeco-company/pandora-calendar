<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { CalendarApi, type CyclePrediction, type BodyRhythm, type CycleRecord, type SymptomRecord } from '../api'
import Card from '../components/ui/Card.vue'
import Spinner from '../components/ui/Spinner.vue'
import EmptyState from '../components/ui/EmptyState.vue'
import Character from '../components/Character.vue'
import { getPet, moodForPhase } from '../lib/character'
import { useTone } from '../composables/useTone'

const { t } = useTone()

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
    error.value = e?.response?.data?.message ?? '載入失敗，朵朵稍後再試試。'
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

const phaseLabels: Record<string, string> = {
  menstrual: '經期',
  follicular: '濾泡期',
  ovulation: '排卵期',
  luteal: '黃體期',
  unknown: '尚未推算',
}

const monthTitle = computed(() => `${today.getFullYear()} 年 ${today.getMonth() + 1} 月`)
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
              {{ daysUntilNext < 0 ? '天' : daysUntilNext === 0 ? '今天' : '天' }}
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
          目前
          <span class="font-semibold text-peach-500">{{ phaseLabels[rhythm.phase] }}</span>
          <template v-if="rhythm.cycle_day"> · 週期第 {{ rhythm.cycle_day }} 天</template>
        </p>
      </div>
      <!-- 角落寵物 widget -->
      <div class="shrink-0 -mt-2">
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
      </div>
    </header>

    <Spinner v-if="loading" label="朵朵在算..." />

    <EmptyState
      v-else-if="error"
      icon="🌸"
      title="暫時讀不到資料"
      :subtitle="error"
    />

    <template v-else>
      <div class="md:grid md:grid-cols-[minmax(0,1fr)_320px] md:gap-6 md:items-start">
      <div class="md:min-w-0">
      <Card tone="plain" class="mb-4">
        <div class="grid grid-cols-7 text-[11px] font-zen text-center text-stone-400 mb-3">
          <span v-for="w in ['日', '一', '二', '三', '四', '五', '六']" :key="w">{{ w }}</span>
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
              'ring-2 ring-peach-400 font-bold shadow-soft': cell.isToday,
            }"
          >
            {{ cell.day || '' }}
            <span
              v-if="cell.hasLog"
              class="absolute bottom-0.5 right-0.5 w-1.5 h-1.5 rounded-full bg-peach-500"
            />
          </button>
        </div>
      </Card>

      <Card tone="cream" class="mb-4">
        <h3 class="font-display font-bold text-peach-500 text-base mb-2">下次經期預測</h3>
        <p v-if="prediction?.next_period_eta" class="text-sm text-stone-700 leading-relaxed font-zen">
          📅 約
          <span class="font-bold text-peach-500">{{ prediction.next_period_eta }}</span>
          · 信心度
          <span :class="prediction.confidence === 'high' ? 'text-sage-500' : 'text-peach-400'">
            {{
              prediction.confidence === 'high'
                ? '高'
                : prediction.confidence === 'low'
                ? '低（資料還不夠）'
                : '無'
            }}
          </span>
        </p>
        <p v-else class="text-sm text-stone-500 font-zen">記錄一次經期後就會開始預測喔。</p>
      </Card>

      <div class="flex gap-3 text-[11px] text-stone-500 px-2 font-zen flex-wrap">
        <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-phase-menstrual" />經期</span>
        <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-phase-follicular" />濾泡</span>
        <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-phase-ovulation" />排卵</span>
        <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-phase-luteal" />黃體</span>
        <span class="flex items-center gap-1.5"><span class="w-1.5 h-1.5 rounded-full bg-peach-500" />當天有記錄</span>
      </div>
      </div>

      <!-- iPad / desktop side panel：永久顯示當天 detail（md+ 才出現） -->
      <aside class="hidden md:block md:sticky md:top-6" data-test="day-detail-side">
        <Card tone="cream" class="space-y-3">
          <header class="flex items-center justify-between">
            <h3 class="font-display text-base font-bold text-peach-500">
              {{ detailDate || '點選日期看記錄' }}
            </h3>
          </header>

          <p v-if="detailDay?.phase" class="font-zen text-[12px] text-stone-500">
            這天是
            <span class="font-semibold text-peach-500">{{ phaseLabels[detailDay.phase] }}</span>
          </p>

          <div v-if="detailCycle" class="bg-white rounded-2xl p-3 text-sm font-zen space-y-1">
            <p class="text-peach-500 font-bold">🌙 經期記錄</p>
            <p class="text-stone-600">流量 {{ detailCycle.peak_flow ?? '未填' }} / 5</p>
            <p v-if="detailCycle.length_days" class="text-stone-500 text-xs">持續 {{ detailCycle.length_days }} 天</p>
          </div>

          <div v-if="detailSymptom" class="bg-white rounded-2xl p-3 text-sm font-zen space-y-1.5">
            <p class="text-peach-500 font-bold">🌸 身體記錄</p>
            <p class="text-stone-600">{{ MOOD_LABEL[detailSymptom.mood ?? ''] || '未記錄心情' }}</p>
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
            這天沒有記錄
          </p>
          <p
            v-else-if="!detailDate"
            class="text-stone-400 text-[12px] text-center font-zen py-3"
          >
            點月曆任何一天，這裡會顯示細節
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
            這天是
            <span class="font-semibold text-peach-500">{{ phaseLabels[detailDay.phase] }}</span>
          </p>

          <div v-if="detailCycle" class="bg-white rounded-2xl p-3 text-sm font-zen space-y-1">
            <p class="text-peach-500 font-bold">🌙 經期記錄</p>
            <p class="text-stone-600">流量 {{ detailCycle.peak_flow ?? '未填' }} / 5</p>
            <p v-if="detailCycle.length_days" class="text-stone-500 text-xs">持續 {{ detailCycle.length_days }} 天</p>
          </div>

          <div v-if="detailSymptom" class="bg-white rounded-2xl p-3 text-sm font-zen space-y-1.5">
            <p class="text-peach-500 font-bold">🌸 身體記錄</p>
            <p class="text-stone-600">{{ MOOD_LABEL[detailSymptom.mood ?? ''] || '未記錄心情' }}</p>
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
            這天沒有記錄
          </p>
        </div>
      </div>
    </Transition>
  </div>
</template>
