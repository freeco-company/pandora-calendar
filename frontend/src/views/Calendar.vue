<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { CalendarApi, type CyclePrediction, type BodyRhythm, type CycleRecord } from '../api'
import Card from '../components/ui/Card.vue'
import Spinner from '../components/ui/Spinner.vue'
import EmptyState from '../components/ui/EmptyState.vue'
import Character from '../components/Character.vue'
import { getPet, moodForPhase } from '../lib/character'

const cycles = ref<CycleRecord[]>([])
const prediction = ref<CyclePrediction | null>(null)
const rhythm = ref<BodyRhythm | null>(null)
const loading = ref(true)
const error = ref<string | null>(null)
const pet = ref(getPet())

async function load() {
  loading.value = true
  error.value = null
  try {
    const res = await CalendarApi.cycles()
    cycles.value = res.data.data
    prediction.value = res.data.prediction
    rhythm.value = res.data.body_rhythm
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
}

const grid = computed<DayMeta[]>(() => {
  if (!prediction.value) return []
  const cells: DayMeta[] = []
  for (let i = 0; i < startWeekday.value; i++) {
    cells.push({ date: '', day: 0, phase: null, isToday: false })
  }
  for (let d = 1; d <= daysInMonth.value; d++) {
    const date = new Date(today.getFullYear(), today.getMonth(), d)
    const isoDate = date.toISOString().slice(0, 10)
    cells.push({
      date: isoDate,
      day: d,
      phase: phaseFor(date),
      isToday: date.toDateString() === today.toDateString(),
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

const phaseLabels: Record<string, string> = {
  menstrual: '經期',
  follicular: '濾泡期',
  ovulation: '排卵期',
  luteal: '黃體期',
  unknown: '尚未推算',
}

const monthTitle = computed(() => `${today.getFullYear()} 年 ${today.getMonth() + 1} 月`)
const todayMood = computed(() => moodForPhase(rhythm.value?.phase))
</script>

<template>
  <div class="px-5 pt-10 pb-6 max-w-md mx-auto">
    <header class="flex items-start justify-between mb-5">
      <div>
        <p class="font-zen text-xs text-stone-500 tracking-widest uppercase">Today</p>
        <h1 class="font-display text-2xl font-bold text-peach-500 mt-0.5">{{ monthTitle }}</h1>
        <p
          v-if="rhythm"
          class="font-zen text-sm text-stone-600 mt-1"
          data-test="phase-label"
        >
          今天是
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
      <Card tone="plain" class="mb-4">
        <div class="grid grid-cols-7 text-[11px] font-zen text-center text-stone-400 mb-3">
          <span v-for="w in ['日', '一', '二', '三', '四', '五', '六']" :key="w">{{ w }}</span>
        </div>
        <div class="grid grid-cols-7 gap-1.5">
          <div
            v-for="(cell, idx) in grid"
            :key="idx"
            class="aspect-square rounded-xl flex items-center justify-center text-sm font-zen relative transition-all"
            :class="{
              'bg-phase-menstrual/20 text-sakura-500': cell.phase === 'menstrual',
              'bg-phase-follicular/30 text-peach-500': cell.phase === 'follicular',
              'bg-phase-ovulation/25 text-sage-500': cell.phase === 'ovulation',
              'bg-phase-luteal/20 text-lavender-500': cell.phase === 'luteal',
              'ring-2 ring-peach-400 font-bold shadow-soft': cell.isToday,
            }"
          >
            {{ cell.day || '' }}
          </div>
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
      </div>
    </template>
  </div>
</template>
