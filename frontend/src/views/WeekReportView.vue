<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { PremiumApi, type WeekReport } from '../api'

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
  <div class="px-5 pt-8 pb-4 max-w-md mx-auto space-y-4">
    <button @click="router.push('/me')" class="text-sm text-brand-600">← 回我的</button>

    <header>
      <h1 class="text-2xl font-bold text-brand-700">每週朵朵報告</h1>
      <p class="text-sm text-stone-500">Premium 功能 · 一週身體狀態總結</p>
    </header>

    <div v-if="loading" class="text-center py-8 text-stone-400">載入中...</div>

    <div v-else-if="blocked" class="bg-brand-50 rounded-3xl p-6 text-center space-y-3">
      <div class="text-4xl">💎</div>
      <p class="text-stone-700">每週報告是 Premium 功能</p>
      <button @click="router.push('/me/premium')" class="px-5 py-2 bg-brand-600 text-white rounded-full">看看 Premium</button>
    </div>

    <template v-else-if="report">
      <div class="bg-white rounded-3xl shadow-sm p-5 space-y-3 text-sm">
        <p class="text-xs text-stone-400">{{ report.week_start }} 起 · 7 天</p>
        <p class="text-stone-700 leading-relaxed text-base">{{ report.summary.dodo_summary }}</p>
      </div>

      <div class="grid grid-cols-2 gap-3">
        <div class="bg-white rounded-2xl p-4 text-center">
          <div class="text-2xl font-bold text-brand-700">{{ report.summary.cycles_started }}</div>
          <div class="text-xs text-stone-500">本週經期數</div>
        </div>
        <div class="bg-white rounded-2xl p-4 text-center">
          <div class="text-2xl font-bold text-brand-700">{{ report.summary.checkins }}</div>
          <div class="text-xs text-stone-500">朵朵 check-in 次數</div>
        </div>
        <div class="bg-white rounded-2xl p-4 text-center">
          <div class="text-2xl font-bold text-brand-700">{{ report.summary.symptoms_logged }}</div>
          <div class="text-xs text-stone-500">症狀記錄</div>
        </div>
        <div class="bg-white rounded-2xl p-4 text-center">
          <div class="text-2xl font-bold text-brand-700">{{ report.summary.health_samples }}</div>
          <div class="text-xs text-stone-500">健康資料</div>
        </div>
      </div>

      <button @click="regen" class="w-full py-3 rounded-xl bg-white border border-brand-200 text-brand-700">重新計算</button>
    </template>
  </div>
</template>
