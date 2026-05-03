<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { BbtApi, type BbtRow } from '../api'
import Card from '../components/ui/Card.vue'
import Button from '../components/ui/Button.vue'
import Spinner from '../components/ui/Spinner.vue'
import EmptyState from '../components/ui/EmptyState.vue'

const router = useRouter()
const rows = ref<BbtRow[]>([])
const loading = ref(true)
const saving = ref(false)
const error = ref<string | null>(null)
const measuredOn = ref(new Date().toISOString().slice(0, 10))
const temperature = ref(36.5)

async function load() {
  loading.value = true
  try {
    const r = await BbtApi.list()
    rows.value = r.data.data
  } finally {
    loading.value = false
  }
}
onMounted(load)

async function save() {
  saving.value = true
  error.value = null
  try {
    await BbtApi.store(measuredOn.value, temperature.value)
    await load()
  } catch (e: any) {
    error.value = e?.response?.data?.errors?.temperature_c?.[0] ?? '存檔失敗'
  } finally {
    saving.value = false
  }
}

async function remove(id: number) {
  if (!confirm('確定刪除這筆體溫？')) return
  await BbtApi.destroy(id)
  await load()
}

// SVG sparkline
const W = 320
const H = 140
const points = computed(() => {
  if (rows.value.length === 0) return ''
  const min = 35.5
  const max = 37.5
  const xStep = rows.value.length > 1 ? W / (rows.value.length - 1) : 0
  return rows.value
    .map((r, i) => {
      const t = parseFloat(r.temperature_c)
      const x = i * xStep
      const y = H - ((t - min) / (max - min)) * H
      return `${x.toFixed(1)},${y.toFixed(1)}`
    })
    .join(' ')
})
</script>

<template>
  <div class="px-5 md:px-8 pt-10 pb-12 max-w-md md:max-w-2xl lg:max-w-3xl mx-auto space-y-5">
    <button @click="router.back()" class="text-stone-500 font-zen text-sm">← 返回</button>

    <header>
      <p class="font-zen text-xs text-stone-500 tracking-widest uppercase">BBT</p>
      <h1 class="font-display text-2xl font-bold text-peach-500 mt-0.5">基礎體溫</h1>
      <p class="font-zen text-[12px] text-stone-500 mt-1">每天起床後測一次，雙相曲線能幫朵朵更準預測排卵。</p>
    </header>

    <Card tone="plain" class="space-y-3">
      <h3 class="font-display font-bold text-peach-500 text-sm">記今天的體溫</h3>
      <div class="grid grid-cols-2 gap-3">
        <label class="block text-sm font-zen">
          <span class="text-stone-500 text-[11px]">日期</span>
          <input
            v-model="measuredOn"
            type="date"
            class="mt-1 w-full px-3 py-2 rounded-2xl border border-cream-200 bg-cream-50 text-sm"
          />
        </label>
        <label class="block text-sm font-zen">
          <span class="text-stone-500 text-[11px]">體溫 °C</span>
          <input
            v-model.number="temperature"
            type="number"
            step="0.01"
            min="35.0"
            max="38.5"
            class="mt-1 w-full px-3 py-2 rounded-2xl border border-cream-200 bg-cream-50 text-sm"
          />
        </label>
      </div>
      <p v-if="error" class="text-xs text-sakura-500 font-zen">{{ error }}</p>
      <Button full variant="primary" :loading="saving" @click="save">記下</Button>
    </Card>

    <Card tone="plain">
      <h3 class="font-display font-bold text-peach-500 text-sm mb-3">過去 60 天</h3>
      <Spinner v-if="loading" size="sm" />
      <template v-else-if="rows.length">
        <svg :viewBox="`0 0 ${W} ${H}`" class="w-full h-32">
          <line x1="0" :y1="H * 0.4" :x2="W" :y2="H * 0.4" stroke="#FFCCA8" stroke-dasharray="4 4" stroke-width="1" opacity="0.4" />
          <polyline
            :points="points"
            fill="none"
            stroke="#F97316"
            stroke-width="2"
            stroke-linecap="round"
            stroke-linejoin="round"
          />
          <circle
            v-for="(r, i) in rows"
            :key="r.id"
            :cx="rows.length > 1 ? (i * W) / (rows.length - 1) : W / 2"
            :cy="H - ((parseFloat(r.temperature_c) - 35.5) / (37.5 - 35.5)) * H"
            r="2.5"
            fill="#F97316"
          />
        </svg>
        <p class="text-[11px] text-stone-400 text-center font-zen mt-2">
          虛線 = 36.7°C 排卵後常見高溫線
        </p>
      </template>
      <EmptyState
        v-else
        icon="🌡️"
        title="還沒記錄體溫"
        subtitle="連續 14 天記錄就會出現雙相曲線。"
      />
    </Card>

    <Card tone="plain" v-if="rows.length">
      <h3 class="font-display font-bold text-peach-500 text-sm mb-3">最近紀錄</h3>
      <ul class="text-sm font-zen divide-y divide-cream-200">
        <li
          v-for="r in rows.slice().reverse().slice(0, 14)"
          :key="r.id"
          class="py-2 flex items-center gap-3"
        >
          <span class="text-stone-400 text-xs w-24 shrink-0">{{ r.measured_on }}</span>
          <span class="font-display text-peach-500 font-bold flex-1">{{ r.temperature_c }} °C</span>
          <button
            @click="remove(r.id)"
            class="text-stone-300 hover:text-sakura-500 text-sm"
            aria-label="刪除"
          >×</button>
        </li>
      </ul>
    </Card>
  </div>
</template>
