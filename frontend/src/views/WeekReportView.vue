<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { PremiumApi, type WeekReport } from '../api'
import Card from '../components/ui/Card.vue'
import Button from '../components/ui/Button.vue'
import Spinner from '../components/ui/Spinner.vue'

const router = useRouter()
const report = ref<WeekReport | null>(null)
const loading = ref(true)
const blocked = ref(false)

onMounted(async () => {
  try {
    const res = await PremiumApi.weekReport()
    report.value = res.data.data
  } catch (e: any) {
    if (e?.response?.status === 402) blocked.value = true
  } finally {
    loading.value = false
  }
})

async function regen() {
  loading.value = true
  try {
    const res = await PremiumApi.generateWeekReport()
    report.value = res.data.data
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="px-5 md:px-8 pt-8 pb-6 max-w-md md:max-w-2xl lg:max-w-3xl mx-auto space-y-4">
    <button @click="router.push('/me')" class="font-zen text-sm text-peach-500 hover:text-peach-400">
      ← 回我的
    </button>

    <header>
      <p class="font-zen text-xs text-stone-500 tracking-widest uppercase">Premium</p>
      <h1 class="font-display text-2xl font-bold text-peach-500 mt-0.5">每週朵朵報告</h1>
      <p class="font-zen text-sm text-stone-500 mt-1">一週身體狀態總結</p>
    </header>

    <Spinner v-if="loading" label="載入中..." />

    <Card v-else-if="blocked" tone="lavender" class="text-center space-y-3">
      <div class="text-4xl">💎</div>
      <p class="font-zen text-stone-700">每週報告是 Premium 功能</p>
      <Button variant="primary" sfx="ui_open" @click="router.push('/me/premium')">看看 Premium</Button>
    </Card>

    <template v-else-if="report">
      <Card tone="cream">
        <p class="text-xs text-stone-400 font-zen">{{ report.week_start }} 起 · 7 天</p>
        <p class="text-stone-700 leading-relaxed text-base font-zen mt-2">
          {{ report.summary.dodo_summary }}
        </p>
      </Card>

      <div class="grid grid-cols-2 gap-3">
        <Card tone="plain" class="text-center !p-4">
          <div class="font-display font-bold text-peach-500 text-2xl">{{ report.summary.cycles_started }}</div>
          <div class="text-[11px] text-stone-500 mt-0.5 font-zen">本週經期數</div>
        </Card>
        <Card tone="plain" class="text-center !p-4">
          <div class="font-display font-bold text-peach-500 text-2xl">{{ report.summary.checkins }}</div>
          <div class="text-[11px] text-stone-500 mt-0.5 font-zen">朵朵 check-in 次數</div>
        </Card>
        <Card tone="plain" class="text-center !p-4">
          <div class="font-display font-bold text-peach-500 text-2xl">{{ report.summary.symptoms_logged }}</div>
          <div class="text-[11px] text-stone-500 mt-0.5 font-zen">症狀記錄</div>
        </Card>
        <Card tone="plain" class="text-center !p-4">
          <div class="font-display font-bold text-peach-500 text-2xl">{{ report.summary.health_samples }}</div>
          <div class="text-[11px] text-stone-500 mt-0.5 font-zen">健康資料</div>
        </Card>
      </div>

      <Button variant="secondary" full sfx="ui_tap" @click="regen">重新計算</Button>
    </template>
  </div>
</template>
