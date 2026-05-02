<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { CalendarApi, type CyclePrediction, type BodyRhythm, type CycleRecord } from '../api'

const cycles = ref<CycleRecord[]>([])
const prediction = ref<CyclePrediction | null>(null)
const rhythm = ref<BodyRhythm | null>(null)
const loading = ref(true)

async function load() {
  loading.value = true
  const res = await CalendarApi.cycles()
  cycles.value = res.data.data
  prediction.value = res.data.prediction
  rhythm.value = res.data.body_rhythm
  loading.value = false
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
</script>

<template>
  <div class="px-5 pt-8 pb-4 max-w-md mx-auto">
    <header class="mb-5">
      <h1 class="text-2xl font-bold text-brand-700">{{ monthTitle }}</h1>
      <p v-if="rhythm" class="text-sm text-brand-600 mt-1" data-test="phase-label">
        今天是 <span class="font-semibold">{{ phaseLabels[rhythm.phase] }}</span>
        <template v-if="rhythm.cycle_day"> · 週期第 {{ rhythm.cycle_day }} 天</template>
      </p>
    </header>

    <div v-if="loading" class="text-center py-12 text-stone-400">朵朵在算...</div>

    <template v-else>
      <div class="bg-white rounded-3xl shadow-sm p-4 mb-4">
        <div class="grid grid-cols-7 text-xs text-center text-stone-400 mb-2">
          <span v-for="w in ['日','一','二','三','四','五','六']" :key="w">{{ w }}</span>
        </div>
        <div class="grid grid-cols-7 gap-1.5">
          <div
            v-for="(cell, idx) in grid"
            :key="idx"
            class="aspect-square rounded-lg flex items-center justify-center text-sm relative"
            :class="{
              'bg-phase-menstrual/15 text-phase-menstrual': cell.phase === 'menstrual',
              'bg-phase-follicular/15 text-stone-700': cell.phase === 'follicular',
              'bg-phase-ovulation/15 text-phase-ovulation': cell.phase === 'ovulation',
              'bg-phase-luteal/15 text-phase-luteal': cell.phase === 'luteal',
              'ring-2 ring-brand-600 font-bold': cell.isToday,
            }"
          >
            {{ cell.day || '' }}
          </div>
        </div>
      </div>

      <div class="bg-white rounded-3xl shadow-sm p-4 mb-4 text-sm space-y-2">
        <h3 class="font-bold text-brand-700">下次經期預測</h3>
        <p v-if="prediction?.next_period_eta" class="text-stone-700">
          📅 約 <span class="font-semibold">{{ prediction.next_period_eta }}</span>
          · 信心度
          <span :class="prediction.confidence === 'high' ? 'text-green-600' : 'text-amber-500'">
            {{ prediction.confidence === 'high' ? '高' : prediction.confidence === 'low' ? '低（資料還不夠）' : '無' }}
          </span>
        </p>
        <p v-else class="text-stone-500">記錄一次經期後就會開始預測喔。</p>
      </div>

      <div class="flex gap-2 text-xs text-stone-500 px-1">
        <span class="flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-phase-menstrual" />經期</span>
        <span class="flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-phase-ovulation" />排卵</span>
        <span class="flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-phase-luteal" />黃體</span>
      </div>
    </template>
  </div>
</template>
